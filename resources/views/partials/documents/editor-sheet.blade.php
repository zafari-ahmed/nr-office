{{-- The rich editor shown on the letterhead, header and footer from the letterhead picked in `lh` (Alpine, see documents.form). --}}
<div class="sheet-wrap">
    <div class="lh-sheet" :style="`--lh-head: ${cur.header_mm ?? 20}mm; --lh-foot: ${cur.footer_mm ?? 15}mm; --lh-side: ${cur.side_mm ?? 20}mm`">
        <div class="lh-head"><template x-if="cur.header"><img :src="cur.header" alt=""></template></div>
        <div id="doc-editor" x-data="richEditor({ name: 'body', placeholder: @js($placeholder), value: @js($value), align: true, contentClass: 'doc-body doc-editor' })" class="lh-content">
            <div class="sticky top-14 z-20 -mx-[var(--lh-side)] -mt-[6mm] mb-[6mm] border-y border-line bg-surface shadow-sm no-print">@include('partials.documents.toolbar')</div>
            <div x-ref="editor"></div>
            <input type="hidden" :name="name" :value="html">
        </div>
        <div class="lh-foot"><template x-if="cur.footer"><img :src="cur.footer" alt=""></template></div>
    </div>
</div>
