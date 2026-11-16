import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import Table from '@tiptap/extension-table';
import TableRow from '@tiptap/extension-table-row';
import TableHeader from '@tiptap/extension-table-header';
import TableCell from '@tiptap/extension-table-cell';
import TextAlign from '@tiptap/extension-text-align';

/*
 * Rich text box: <div x-data="richEditor({ name: 'body', placeholder: '…' })">.
 * Pasted content keeps its headings, bold/italic, lists, links and tables. The HTML is posted in a hidden input
 * and cleaned again on the server (App\Support\RichText).
 * The editor lives outside Alpine's reactive state; Alpine only tracks which buttons are active.
 */
/*
 * Options for letterhead documents: `align: true` adds text alignment, `contentClass` replaces the content box style.
 * Other scripts can replace the content or insert text with the `rich-set` / `rich-insert` events on the editor element.
 */
export function richEditor({ name = 'body', placeholder = '', value = '', align = false, contentClass = 'rich rich-editor-content' } = {}) {
    let editor;

    return {
        name,
        html: value,
        active: {},
        init() {
            editor = new Editor({
                element: this.$refs.editor,
                content: value,
                extensions: [
                    StarterKit.configure({ heading: { levels: [1, 2, 3] } }),
                    Underline,
                    Link.configure({ openOnClick: false, autolink: true, HTMLAttributes: { rel: 'noopener noreferrer', target: '_blank' } }),
                    Placeholder.configure({ placeholder }),
                    Table.configure({ resizable: false }), TableRow, TableHeader, TableCell,
                    ...(align ? [TextAlign.configure({ types: ['heading', 'paragraph', 'tableCell', 'tableHeader'] })] : []),
                ],
                editorProps: { attributes: { class: contentClass } },
                onUpdate: () => this.sync(),
                onSelectionUpdate: () => this.refresh(),
                onTransaction: () => this.refresh(),
            });
            this.sync();
            this.$el.addEventListener('rich-set', (e) => { editor.commands.setContent(e.detail || '', true); });
            this.$el.addEventListener('rich-insert', (e) => { editor.chain().focus().insertContent(e.detail).run(); });
        },
        destroy() { editor?.destroy(); },
        sync() {
            this.html = editor.isEmpty ? '' : editor.getHTML();
            this.refresh();
        },
        refresh() {
            this.active = {
                bold: editor.isActive('bold'), italic: editor.isActive('italic'), underline: editor.isActive('underline'), strike: editor.isActive('strike'),
                h2: editor.isActive('heading', { level: 2 }), h3: editor.isActive('heading', { level: 3 }),
                bullet: editor.isActive('bulletList'), ordered: editor.isActive('orderedList'), quote: editor.isActive('blockquote'), link: editor.isActive('link'),
                table: editor.isActive('table'),
                ...(align ? { left: editor.isActive({ textAlign: 'left' }), center: editor.isActive({ textAlign: 'center' }), right: editor.isActive({ textAlign: 'right' }), justify: editor.isActive({ textAlign: 'justify' }) } : {}),
            };
        },
        run(command, ...args) { editor.chain().focus()[command](...args).run(); },
        link() {
            const current = editor.getAttributes('link').href || '';
            const url = window.prompt('Link address', current || 'https://');
            if (url === null) return;
            if (url.trim() === '' || url.trim() === 'https://') editor.chain().focus().extendMarkRange('link').unsetLink().run();
            else editor.chain().focus().extendMarkRange('link').setLink({ href: url.trim() }).run();
        },
        clear() { editor.commands.clearContent(true); },
        isEmpty() { return editor.isEmpty; },
        table() { editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(); },
    };
}
