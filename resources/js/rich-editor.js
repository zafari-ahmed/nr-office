import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import Table from '@tiptap/extension-table';
import TableRow from '@tiptap/extension-table-row';
import TableHeader from '@tiptap/extension-table-header';
import TableCell from '@tiptap/extension-table-cell';

/*
 * Rich text box: <div x-data="richEditor({ name: 'body', placeholder: '…' })">.
 * Pasted content keeps its headings, bold/italic, lists, links and tables. The HTML is posted in a hidden input
 * and cleaned again on the server (App\Support\RichText).
 * The editor lives outside Alpine's reactive state; Alpine only tracks which buttons are active.
 */
export function richEditor({ name = 'body', placeholder = '', value = '' } = {}) {
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
                ],
                editorProps: { attributes: { class: 'rich rich-editor-content' } },
                onUpdate: () => this.sync(),
                onSelectionUpdate: () => this.refresh(),
                onTransaction: () => this.refresh(),
            });
            this.sync();
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
    };
}
