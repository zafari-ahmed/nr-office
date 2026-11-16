/*
 * Letterhead documents, done in the browser so the server needs no PDF or Word tools (shared hosting).
 *
 * letterheadCutter: renders page 1 of an uploaded PDF, Word (.docx) or image file, lets the user mark where the
 * header ends and the footer starts, and posts the two bands as PNG files (inputs `header` and `footer`).
 *
 * templateImport: turns an old Word or PDF document into the starting body of a template (Word keeps headings,
 * bold, lists and tables; a PDF gives its text as paragraphs).
 *
 * The heavy libraries are loaded only on these pages.
 */

const PAGE_PX = 2480; // A4 width at 300 dpi
const A4_MM = 210;

const ext = (file) => (file?.name || '').split('.').pop().toLowerCase();

async function pdfjs() {
    const lib = await import('pdfjs-dist/legacy/build/pdf.mjs');
    const worker = await import('pdfjs-dist/legacy/build/pdf.worker.min.mjs?worker&url');
    lib.GlobalWorkerOptions.workerSrc = worker.default;
    return lib;
}

function whiteCanvas(width, height) {
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(width);
    canvas.height = Math.round(height);
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    return canvas;
}

async function renderPdf(file) {
    const lib = await pdfjs();
    const pdf = await lib.getDocument({ data: new Uint8Array(await file.arrayBuffer()) }).promise;
    const page = await pdf.getPage(1);
    const viewport = page.getViewport({ scale: PAGE_PX / page.getViewport({ scale: 1 }).width });
    const canvas = whiteCanvas(viewport.width, viewport.height);
    await page.render({ canvasContext: canvas.getContext('2d'), viewport, background: '#ffffff' }).promise;
    return canvas;
}

async function renderDocx(file) {
    const [{ renderAsync }, { default: html2canvas }] = await Promise.all([import('docx-preview'), import('html2canvas')]);
    const holder = document.createElement('div');
    holder.style.cssText = 'position:fixed;left:-20000px;top:0;background:#fff';
    document.body.appendChild(holder);
    try {
        await renderAsync(file, holder, null, {
            inWrapper: false, breakPages: true, renderHeaders: true, renderFooters: true,
            ignoreLastRenderedPageBreak: false, useBase64URL: true, experimental: true,
        });
        const page = holder.querySelector('section.docx') || holder.firstElementChild;
        if (!page) throw new Error('The Word file has no pages.');
        const shot = await html2canvas(page, { scale: PAGE_PX / page.offsetWidth, backgroundColor: '#ffffff', logging: false, useCORS: true });
        return shot;
    } finally {
        holder.remove();
    }
}

async function renderImage(file) {
    const url = URL.createObjectURL(file);
    try {
        const img = await new Promise((resolve, reject) => {
            const i = new Image();
            i.onload = () => resolve(i);
            i.onerror = () => reject(new Error('This image could not be read.'));
            i.src = url;
        });
        const canvas = whiteCanvas(PAGE_PX, (img.naturalHeight * PAGE_PX) / img.naturalWidth);
        canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
        return canvas;
    } finally {
        URL.revokeObjectURL(url);
    }
}

export async function renderFirstPage(file) {
    const type = ext(file);
    if (type === 'pdf') return renderPdf(file);
    if (type === 'docx') return renderDocx(file);
    if (['png', 'jpg', 'jpeg', 'webp'].includes(type)) return renderImage(file);
    if (type === 'doc') throw new Error('Old .doc files can’t be read. In Word, use File › Save As › PDF (best) or .docx, then upload that.');
    throw new Error('Upload a PDF, Word (.docx) or image file.');
}

/*
 * Guess the header and footer bands: the header is the first block of ink at the top of the page, ending at the
 * first blank gap; the footer is the same from the bottom. Returns millimetres, or null when the page gives no clue.
 */
function detectBands(canvas) {
    const { width, height } = canvas;
    const data = canvas.getContext('2d').getImageData(0, 0, width, height).data;
    const mmPerPx = A4_MM / width;
    const inked = new Uint8Array(height);
    for (let y = 0; y < height; y++) {
        let dark = 0;
        for (let x = 0; x < width; x += 4) {
            const i = (y * width + x) * 4;
            if (data[i] + data[i + 1] + data[i + 2] < 690) dark++;
        }
        inked[y] = dark > 1 ? 1 : 0;
    }
    const gapPx = Math.round(5 / mmPerPx); // 5 mm of white ends a band
    const scan = (from, step, limit) => {
        let y = from;
        while (y !== limit && !inked[y]) y += step; // skip the margin
        if (y === limit) return null;
        let blank = 0;
        for (; y !== limit; y += step) {
            blank = inked[y] ? 0 : blank + 1;
            if (blank >= gapPx) return y - step * Math.round(blank / 2);
        }
        return null;
    };
    const head = scan(0, 1, Math.round(height * 0.45));
    const foot = scan(height - 1, -1, Math.round(height * 0.6));
    return {
        header: head === null ? null : Math.round(head * mmPerPx),
        footer: foot === null ? null : Math.round((height - foot) * mmPerPx),
    };
}

function cropToFile(canvas, top, bottom, name) {
    const h = Math.max(1, Math.round(bottom - top));
    const out = whiteCanvas(canvas.width, h);
    out.getContext('2d').drawImage(canvas, 0, Math.round(top), canvas.width, h, 0, 0, canvas.width, h);
    return new Promise((resolve) => out.toBlob((blob) => resolve(new File([blob], name, { type: 'image/png' })), 'image/png'));
}

function setFile(input, file) {
    const dt = new DataTransfer();
    if (file) dt.items.add(file);
    input.files = dt.files;
}

export function letterheadCutter({ sourceUrl = null, sourceName = '', headerMm = 40, footerMm = 25, sideMm = 20, hasImages = false } = {}) {
    let canvas = null;

    return {
        busy: false,
        error: '',
        preview: null,
        fileName: sourceName,
        pageMm: 297,
        headerMm: Number(headerMm),
        footerMm: Number(footerMm),
        sideMm: Number(sideMm),
        useHeader: true,
        useFooter: true,
        changed: false,
        hasImages,
        dragging: null,
        async init() {
            window.addEventListener('pointermove', (e) => this.drag(e));
            window.addEventListener('pointerup', () => { this.dragging = null; });
            if (sourceUrl) {
                try {
                    const res = await fetch(sourceUrl, { credentials: 'same-origin' });
                    if (res.ok) await this.render(new File([await res.blob()], sourceName), false);
                } catch (e) { /* the saved images still show */ }
            }
        },
        async pick(e) {
            const file = e.target.files[0];
            if (!file) return;
            this.fileName = file.name;
            await this.render(file, true);
        },
        async render(file, fresh) {
            this.busy = true;
            this.error = '';
            try {
                canvas = await renderFirstPage(file);
                this.pageMm = (canvas.height * A4_MM) / canvas.width;
                this.preview = canvas.toDataURL('image/jpeg', 0.9);
                if (fresh) {
                    const guess = detectBands(canvas);
                    if (guess.header) this.headerMm = Math.min(guess.header, 120);
                    if (guess.footer) this.footerMm = Math.min(guess.footer, 100);
                    this.changed = true;
                }
            } catch (e) {
                canvas = null;
                this.preview = null;
                this.error = e.message || 'This file could not be read.';
            } finally {
                this.busy = false;
            }
        },
        pct(mm) { return (mm / this.pageMm) * 100 + '%'; },
        startDrag(which) { this.dragging = which; this.changed = true; },
        drag(e) {
            if (!this.dragging || !this.$refs.page) return;
            const box = this.$refs.page.getBoundingClientRect();
            const mm = Math.max(0, Math.min(1, (e.clientY - box.top) / box.height)) * this.pageMm;
            if (this.dragging === 'header') this.headerMm = Math.round(Math.min(mm, this.pageMm - this.footerMm - 30));
            else this.footerMm = Math.round(Math.min(this.pageMm - mm, this.pageMm - this.headerMm - 30));
        },
        nudge(which, by) {
            this.changed = true;
            this[which] = Math.max(0, Math.min(150, Number(this[which]) + by));
        },
        async submit(form) {
            if (canvas && (this.changed || !this.hasImages)) {
                const px = canvas.width / A4_MM;
                setFile(this.$refs.header, this.useHeader && this.headerMm > 0 ? await cropToFile(canvas, 0, this.headerMm * px, 'header.png') : null);
                setFile(this.$refs.footer, this.useFooter && this.footerMm > 0 ? await cropToFile(canvas, canvas.height - this.footerMm * px, canvas.height, 'footer.png') : null);
                this.$refs.clearHeader.value = this.useHeader ? '0' : '1';
                this.$refs.clearFooter.value = this.useFooter ? '0' : '1';
            }
            form.submit();
        },
    };
}

async function pdfToHtml(file) {
    const lib = await pdfjs();
    const pdf = await lib.getDocument({ data: new Uint8Array(await file.arrayBuffer()) }).promise;
    const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const paragraphs = [];
    for (let n = 1; n <= pdf.numPages; n++) {
        const { items } = await (await pdf.getPage(n)).getTextContent();
        let line = '';
        let lastY = null;
        let lastH = 12;
        let para = [];
        const endLine = () => { if (line.trim()) para.push(line.trim()); line = ''; };
        const endPara = () => { endLine(); if (para.length) paragraphs.push(para.map(esc).join('<br>')); para = []; };
        for (const item of items) {
            const y = item.transform[5];
            const h = item.height || lastH;
            if (lastY !== null && Math.abs(lastY - y) > h * 0.5) {
                if (Math.abs(lastY - y) > lastH * 1.8) endPara(); else endLine();
            }
            line += item.str;
            if (item.hasEOL) endLine();
            lastY = y;
            lastH = h;
        }
        endPara();
    }
    return paragraphs.map((p) => `<p>${p}</p>`).join('');
}

async function docxToHtml(file) {
    const { default: mammoth } = await import('mammoth/mammoth.browser.js');
    const result = await mammoth.convertToHtml({ arrayBuffer: await file.arrayBuffer() }, { ignoreEmptyParagraphs: false });
    return result.value;
}

export function templateImport() {
    return {
        busy: false,
        error: '',
        note: '',
        async pick(e, editorEl, isEmpty) {
            const file = e.target.files[0];
            this.error = '';
            this.note = '';
            if (!file) return;
            const type = ext(file);
            if (type === 'doc') {
                this.note = 'Old .doc files can’t be read here. The file is kept with the template; for the starting text, save it as .docx or PDF and upload that.';
                return;
            }
            if (!isEmpty && !window.confirm('Replace the current template text with the text from this document?')) {
                this.note = 'The file will be kept with the template; the text was not changed.';
                return;
            }
            this.busy = true;
            try {
                const html = type === 'pdf' ? await pdfToHtml(file) : await docxToHtml(file);
                editorEl.dispatchEvent(new CustomEvent('rich-set', { detail: html }));
                this.note = type === 'pdf'
                    ? 'Text taken from the PDF. Tables and bold need tidying by hand; a Word file keeps more of the layout.'
                    : 'Text, headings, lists and tables taken from the Word file. Replace the parts that change with placeholders.';
            } catch (err) {
                this.error = 'This document could not be read: ' + (err.message || err);
            } finally {
                this.busy = false;
            }
        },
    };
}
