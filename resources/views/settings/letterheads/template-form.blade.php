@php
    $lhData = $letterheads->mapWithKeys(fn ($l) => [$l->id => ['header' => $l->imageUrl('header'), 'footer' => $l->imageUrl('footer'), 'header_mm' => $l->header_mm, 'footer_mm' => $l->footer_mm, 'side_mm' => $l->side_mm]]);
    $defaultId = $letterheads->firstWhere('is_default', true)?->id ?? $letterheads->first()?->id;
@endphp
<x-layouts.app :title="$template->exists ? 'Edit template' : 'New template'">
    @include('partials.documents.styles')
    <x-page-header title="Settings" :subtitle="$template->exists ? 'Edit template · '.$template->name : 'New document template'" />
    <div class="grid grid-cols-1 lg:grid-cols-[230px_minmax(0,1fr)] gap-6">
        @include('settings._nav')
        <form method="POST" enctype="multipart/form-data" action="{{ $template->exists ? route('settings.templates.update', $template) : route('settings.templates.store') }}" class="min-w-0 space-y-5"
              x-data="{ lhs: @js($lhData), pick: @js((string) old('letterhead_id', $template->letterhead_id ?? '')), def: @js($defaultId), get cur() { return this.lhs[this.pick || this.def] || {} } }">
            @csrf
            @if ($template->exists) @method('PUT') @endif
            <div class="card card-pad grid sm:grid-cols-2 xl:grid-cols-4 gap-4 items-start">
                <x-field label="Template name" name="name" :value="$template->name" required placeholder="Quotation, Purchase order, Letter…" />
                <x-field label="Reference prefix" name="ref_prefix" :value="$template->ref_prefix" required hint="PO gives numbers like PO-{{ now()->year }}-0001." />
                <div>
                    <label class="label" for="f-letterhead_id">Letterhead</label>
                    <select id="f-letterhead_id" name="letterhead_id" class="input" x-model="pick">
                        <option value="">Default letterhead</option>
                        @foreach ($letterheads as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach
                    </select>
                    @if ($letterheads->isEmpty())<p class="hint"><a href="{{ route('settings.letterheads.create') }}">Upload your letterhead</a> to see it here.</p>@endif
                </div>
                <label class="flex items-center gap-2 text-[13.5px] text-ink-2 sm:pt-7"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="checkbox" @checked(old('is_active', $template->is_active))> Show when making a new document</label>
            </div>

            <div x-data="templateImport()" class="card card-pad">
                <div class="flex flex-wrap items-center gap-4">
                    <span class="grid place-items-center size-10 rounded-lg bg-accent-soft text-accent-ink shrink-0"><x-icon name="file-up" /></span>
                    <div class="flex-1 min-w-[220px]">
                        <div class="text-[14px] font-medium">Start from an old document</div>
                        <div class="text-[12.5px] text-muted">Upload a Word (.docx) or PDF {{ $template->name ? strtolower($template->name) : 'document' }} you sent before. Its text and layout fill the page below; the file is kept with the template.
                            @if ($template->sample_name) Current: <a href="{{ route('settings.templates.sample', $template) }}">{{ $template->sample_name }}</a>.@endif</div>
                    </div>
                    <label class="btn btn-secondary cursor-pointer"><x-icon name="upload" /> Upload old document
                        <input type="file" name="sample" accept=".docx,.pdf,.doc" class="sr-only" @change="pick($event, document.getElementById('doc-editor'), document.querySelector('#doc-editor .doc-editor')?.innerText.trim() === '')">
                    </label>
                </div>
                <p x-cloak x-show="busy" class="hint flex items-center gap-1.5 mt-2"><x-icon name="loader-circle" class="!size-3.5 animate-spin" /> Reading the document…</p>
                <p x-cloak x-show="note" class="hint mt-2" x-text="note"></p>
                <p x-cloak x-show="error" class="error mt-2" x-text="error"></p>
                @error('sample')<p class="error mt-2">{{ $message }}</p>@enderror
            </div>

            <div>
                <div class="flex flex-wrap items-end justify-between gap-2 mb-2">
                    <div>
                        <div class="text-[14px] font-medium">Template text</div>
                        <div class="text-[12.5px] text-muted">Use <b>Insert field</b> for parts filled in automatically, such as the reference number, date and client. Everything else can be changed on each document.</div>
                    </div>
                </div>
                @include('partials.documents.editor-sheet', ['value' => old('body', $template->body ?? ''), 'placeholder' => 'Type the standard text for this document, or upload an old one above…'])
                @error('body')<p class="error">{{ $message }}</p>@enderror
            </div>

            <div class="flex justify-end gap-2 sticky bottom-0 py-3 bg-bg/90 backdrop-blur border-t border-line">
                <a href="{{ route('settings.letterheads.index') }}" class="btn btn-secondary">Cancel</a>
                <button class="btn btn-primary">Save template</button>
            </div>
        </form>
    </div>
</x-layouts.app>
