@php
    $editing = $document->exists;
    $heading = $editing ? 'Edit '.$document->ref_no : 'New '.($template?->name ?? 'document');
@endphp
<x-layouts.app :title="$heading">
    @include('partials.documents.styles')
    <form method="POST" action="{{ $editing ? route('documents.update', $document) : route('documents.store') }}"
          x-data="{ lhs: @js($letterheadData), pick: @js((string) old('letterhead_id', $document->letterhead_id ?? '')), def: @js($defaultLetterheadId), get cur() { return this.lhs[this.pick || this.def] || {} } }">
        @csrf
        @if ($editing) @method('PUT') @endif
        @unless ($editing)<input type="hidden" name="template_id" value="{{ $template?->id }}">@endunless

        <x-page-header :title="$heading" :subtitle="$editing ? 'Changes apply to the next print. Earlier prints stay in the history as they were.' : 'Write the content between the header and footer, then save or print.'">
            <x-slot:crumbs><a href="{{ route('documents.index') }}">Documents</a> <x-icon name="chevron-right" class="!size-3.5" /> <span>{{ $editing ? $document->ref_no : 'New' }}</span></x-slot:crumbs>
            <x-slot:actions>
                <a href="{{ $editing ? route('documents.show', $document) : route('documents.index') }}" class="btn btn-secondary">Cancel</a>
                <button name="action" value="save" class="btn btn-secondary"><x-icon name="save" /> Save</button>
                <button name="action" value="print" class="btn btn-primary"><x-icon name="printer" /> Save & print</button>
            </x-slot:actions>
        </x-page-header>

        <div class="card card-pad grid sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-5 max-w-[1100px]">
            <x-field label="Subject / title" name="title" :value="$document->title" required class="xl:col-span-2" placeholder="Quotation for interior works" />
            <x-field label="To" name="recipient" :value="$document->recipient" placeholder="Client or company name" />
            <x-field label="Date" name="doc_date" type="date" :value="$document->doc_date" required />
            <x-field label="Project" name="project_id" :options="$projects->mapWithKeys(fn ($p) => [$p->id => $p->name.($p->client ? ' · '.$p->client->name : '')])" :value="$document->project_id" blank="None" />
            @if ($letterheads->count() > 1)
                <div>
                    <label class="label" for="f-letterhead_id">Letterhead</label>
                    <select id="f-letterhead_id" name="letterhead_id" class="input" x-model="pick">
                        <option value="">{{ $template?->letterhead?->name ? 'Template’s ('.$template->letterhead->name.')' : 'Default' }}</option>
                        @foreach ($letterheads as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach
                    </select>
                </div>
            @endif
            @php $tag = fn ($k) => str_repeat('{', 2).$k.str_repeat('}', 2); @endphp
            <p class="sm:col-span-2 xl:col-span-5 text-[12.5px] text-muted -mt-1">
                Fields like <code>{{ $tag('ref') }}</code>, <code>{{ $tag('date') }}</code> and <code>{{ $tag('client') }}</code> in the text are filled in from these details when you print.
                @if ($editing) Reference: <b class="font-mono">{{ $document->ref_no }}</b> @else The reference number is given when you save. @endif
            </p>
        </div>

        @include('partials.documents.editor-sheet', ['value' => old('body', $document->body ?? ''), 'placeholder' => 'Write your document here…'])
        @error('body')<p class="error">{{ $message }}</p>@enderror
    </form>
</x-layouts.app>
