{{-- Letterhead sheet: A4, header and footer images on every printed page, content in between. --}}
<style>
    .lh-sheet { --lh-head: 40mm; --lh-foot: 25mm; --lh-side: 20mm; position: relative; width: 210mm; min-height: 297mm; margin: 0 auto; background: #fff; color: #1a1d23; box-shadow: 0 1px 3px rgba(16,24,40,.08), 0 8px 24px rgba(16,24,40,.06); padding-bottom: var(--lh-foot); }
    .lh-head img, .lh-foot img { display: block; width: 100%; height: auto; }
    .lh-head { height: var(--lh-head); overflow: hidden; }
    .lh-foot { position: absolute; left: 0; right: 0; bottom: 0; height: var(--lh-foot); overflow: hidden; display: flex; align-items: flex-end; }
    .lh-foot img { width: 100%; }
    .lh-flow { width: 100%; border-collapse: collapse; }
    .lh-flow > thead, .lh-flow > tfoot { display: none; }
    .lh-flow > tbody > tr > td, .lh-flow > thead > tr > td, .lh-flow > tfoot > tr > td { padding: 0; border: 0; }
    .lh-content { padding: 6mm var(--lh-side) 8mm; }
    .lh-plain .lh-head img, .lh-plain .lh-foot img { visibility: hidden; }

    .doc-body { font-family: 'Geist', Arial, sans-serif; font-size: 10.5pt; line-height: 1.5; color: #1a1d23; overflow-wrap: break-word; }
    .doc-body > :first-child { margin-top: 0; }
    .doc-body p { margin: 0 0 5pt; min-height: 1em; }
    .doc-body h1 { font-size: 16pt; font-weight: 700; margin: 10pt 0 6pt; }
    .doc-body h2 { font-size: 13pt; font-weight: 650; margin: 9pt 0 5pt; }
    .doc-body h3 { font-size: 11.5pt; font-weight: 650; margin: 8pt 0 4pt; }
    .doc-body ul { list-style: disc; padding-left: 18pt; margin: 0 0 6pt; }
    .doc-body ol { list-style: decimal; padding-left: 18pt; margin: 0 0 6pt; }
    .doc-body li > p { margin: 0; }
    .doc-body blockquote { border-left: 2pt solid #c8ccd4; padding-left: 8pt; color: #4a4f5a; margin: 0 0 6pt; }
    .doc-body hr { border: 0; border-top: .75pt solid #9aa0aa; margin: 8pt 0; }
    .doc-body a { color: inherit; text-decoration: underline; }
    .doc-body table { width: 100%; border-collapse: collapse; margin: 4pt 0 8pt; table-layout: auto; }
    .doc-body th, .doc-body td { border: .75pt solid #8a909a; padding: 3.5pt 6pt; vertical-align: top; text-align: left; }
    .doc-body th { background: #f1f3f5; font-weight: 650; }
    .doc-body th > p, .doc-body td > p { margin: 0; }
    .doc-body tr { break-inside: avoid; }
    .doc-body .placeholder-chip, .doc-body mark { background: #fff4c2; }

    .doc-editor { outline: none; min-height: 150mm; }
    .doc-editor p.is-editor-empty:first-child::before { content: attr(data-placeholder); float: left; height: 0; color: #9aa0aa; pointer-events: none; }
    .doc-editor .selectedCell { background: #e8f0fe; }
    .sheet-wrap { overflow-x: auto; padding: 4px 2px 24px; }
    @media (min-width: 1100px) { .sheet-wrap { overflow: visible; } }

    @media print {
        @page { size: A4; margin: 0; }
        html, body { background: #fff !important; }
        .lh-sheet { width: 210mm; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
        .lh-head { position: fixed; top: 0; left: 0; width: 210mm; }
        .lh-foot { position: fixed; bottom: 0; left: 0; width: 210mm; }
        .lh-flow > thead { display: table-header-group; }
        .lh-flow > tfoot { display: table-footer-group; }
        .lh-flow > thead td > div { height: calc(var(--lh-head) + 5mm); }
        .lh-flow > tfoot td > div { height: calc(var(--lh-foot) + 5mm); }
        .lh-content { padding: 0 var(--lh-side); }
        .doc-body .placeholder-chip, .doc-body mark { background: none; }
    }
</style>
