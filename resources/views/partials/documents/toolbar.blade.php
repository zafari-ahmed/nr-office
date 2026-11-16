{{-- Toolbar for a richEditor({ align: true }) used for letterhead documents and templates. --}}
@php
    $tools = [
        ['bold', 'toggleBold', 'bold', 'Bold (Ctrl+B)'], ['italic', 'toggleItalic', 'italic', 'Italic (Ctrl+I)'], ['underline', 'toggleUnderline', 'underline', 'Underline (Ctrl+U)'], null,
        ['h2', 'toggleHeading', 'heading-2', 'Heading', ['level' => 2]], ['h3', 'toggleHeading', 'heading-3', 'Subheading', ['level' => 3]], null,
        ['left', 'setTextAlign', 'align-left', 'Align left', 'left'], ['center', 'setTextAlign', 'align-center', 'Centre', 'center'], ['right', 'setTextAlign', 'align-right', 'Align right', 'right'], ['justify', 'setTextAlign', 'align-justify', 'Justify', 'justify'], null,
        ['bullet', 'toggleBulletList', 'list', 'Bulleted list'], ['ordered', 'toggleOrderedList', 'list-ordered', 'Numbered list'],
    ];
@endphp
<div class="rich-toolbar bg-surface">
    @foreach ($tools as $t)
        @if ($t === null)
            <span class="w-px h-5 bg-line mx-1"></span>
        @else
            <button type="button" class="rich-btn" :class="active.{{ $t[0] }} && 'is-active'" title="{{ $t[3] }}" @click="run('{{ $t[1] }}'{{ isset($t[4]) ? ', '.json_encode($t[4]) : '' }})"><x-icon :name="$t[2]" /></button>
        @endif
    @endforeach
    <button type="button" class="rich-btn" title="Horizontal line" @click="run('setHorizontalRule')"><x-icon name="minus" /></button>
    <span class="w-px h-5 bg-line mx-1"></span>
    <button type="button" class="rich-btn" title="Insert table" @click="table()"><x-icon name="table" /></button>
    <template x-if="active.table">
        <span class="inline-flex items-center gap-0.5">
            <button type="button" class="btn btn-ghost btn-xs" @click="run('addRowAfter')">+ Row</button>
            <button type="button" class="btn btn-ghost btn-xs" @click="run('addColumnAfter')">+ Column</button>
            <button type="button" class="btn btn-ghost btn-xs" @click="run('deleteRow')">− Row</button>
            <button type="button" class="btn btn-ghost btn-xs" @click="run('deleteColumn')">− Column</button>
            <button type="button" class="btn btn-ghost btn-xs hover:text-red-fg" @click="run('deleteTable')">Delete table</button>
        </span>
    </template>
    <span class="w-px h-5 bg-line mx-1"></span>
    <div x-data="{ open: false }" class="relative">
        <button type="button" class="btn btn-ghost btn-xs" @click="open = !open" title="Insert a field that is filled in when printing"><x-icon name="braces" class="!size-3.5" /> Insert field</button>
        <div x-cloak x-show="open" @click.outside="open = false" class="absolute left-0 top-full mt-1 z-30 w-56 card p-1 shadow-lg">
            @foreach (\App\Models\DocumentTemplate::PLACEHOLDERS as $key => $label)
                @php $tag = str_repeat('{', 2).$key.str_repeat('}', 2); @endphp
                <button type="button" class="w-full flex items-center justify-between gap-2 rounded-md px-2.5 py-1.5 text-left text-[13px] hover:bg-surface-2" @click="$el.closest('[x-data^=richEditor]').dispatchEvent(new CustomEvent('rich-insert', { detail: {{ \Illuminate\Support\Js::from($tag) }} })); open = false">
                    <span>{{ $label }}</span><code class="text-[11.5px] text-muted">{{ $tag }}</code>
                </button>
            @endforeach
        </div>
    </div>
    <span class="flex-1"></span>
    <button type="button" class="rich-btn" title="Undo (Ctrl+Z)" @click="run('undo')"><x-icon name="undo-2" /></button>
    <button type="button" class="rich-btn" title="Redo (Ctrl+Y)" @click="run('redo')"><x-icon name="redo-2" /></button>
</div>
