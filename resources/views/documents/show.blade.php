<x-layouts.app :title="$document->ref_no">
    @include('partials.documents.styles')
    <x-page-header :title="$document->title">
        <x-slot:crumbs><a href="{{ route('documents.index') }}">Documents</a> <x-icon name="chevron-right" class="!size-3.5" /> <span class="font-mono">{{ $document->ref_no }}</span></x-slot:crumbs>
        <x-slot:meta>
            <span>{{ $document->template?->name ?? 'Blank page' }}</span>
            <span>{{ $document->doc_date->format('d M Y') }}</span>
            @if ($document->recipient)<span>To {{ $document->recipient }}</span>@endif
            @if ($document->project)<a href="{{ route('projects.show', $document->project) }}">{{ $document->project->name }}</a>@endif
        </x-slot:meta>
        <x-slot:actions>
            <x-action :url="route('documents.destroy', $document)" method="DELETE" confirm="Delete this document? Its print history goes with it." class="btn btn-icon hover:text-red-fg" title="Delete"><x-icon name="trash-2" /></x-action>
            <x-action :url="route('documents.duplicate', $document)" method="POST" class="btn btn-secondary" title="New document with the same content"><x-icon name="copy" /> Duplicate</x-action>
            <a href="{{ route('documents.edit', $document) }}" class="btn btn-secondary"><x-icon name="pencil" /> Edit</a>
            <a href="{{ route('documents.print', [$document, 'go' => 1]) }}" target="_blank" class="btn btn-primary"><x-icon name="printer" /> Print</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 2xl:grid-cols-[auto_320px] gap-6 items-start">
        <div class="sheet-wrap">@include('partials.documents.sheet', ['letterhead' => $letterhead, 'body' => $document->renderedBody()])</div>

        <div class="space-y-4 2xl:sticky 2xl:top-20">
            <div class="card card-pad text-[13px] space-y-1.5">
                <div class="flex justify-between gap-3"><span class="text-muted">Reference</span><span class="font-mono">{{ $document->ref_no }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Letterhead</span><span>{{ $letterhead?->name ?? 'None (plain paper)' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Made by</span><span>{{ $document->creator?->name ?? '—' }}, {{ $document->created_at->format('d M Y') }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">Last changed</span><span>{{ $document->updated_at->diffForHumans() }}</span></div>
            </div>
            <div class="card overflow-hidden">
                <div class="px-4 py-3 border-b border-line flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2"><x-icon name="history" class="text-ink-2" /><h2 class="card-title !text-[14px]">Print history</h2></div>
                    <span class="text-[12.5px] text-muted">{{ $document->print_count }} {{ \Illuminate\Support\Str::plural('print', $document->print_count) }}</span>
                </div>
                @forelse ($document->prints as $p)
                    <div class="flex items-center gap-2.5 px-4 py-2.5 border-b border-line-2 last:border-b-0">
                        <x-avatar :name="$p->user?->name ?? '?'" size="size-7 text-[10.5px]" />
                        <div class="flex-1 min-w-0 text-[13px]">
                            <div class="font-medium truncate">{{ $p->user?->name ?? 'Someone' }}</div>
                            <div class="text-[12px] text-muted">{{ $p->printed_at->format('d M Y, g:i a') }}@unless ($p->with_letterhead) · plain paper @endunless</div>
                        </div>
                        <a href="{{ route('document-prints.show', $p) }}" target="_blank" class="btn btn-ghost btn-xs" title="See or reprint exactly this version">View</a>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-[13px] text-muted">Not printed yet. Each print is saved here with a copy of what was printed.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.app>
