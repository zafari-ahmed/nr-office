<x-layouts.app :title="$quotation->label()">
    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4 mb-6">
        <div>
            <a href="{{ route('projects.show', [$quotation->project, 'tab' => 'quotations']) }}" class="text-[13px] text-muted hover:text-ink inline-flex items-center gap-1"><x-icon name="chevron-left" class="size-3.5" /> {{ $quotation->project->name }}</a>
            <div class="flex flex-wrap items-center gap-3 mt-1">
                <h1 class="text-[22px] font-semibold">{{ $quotation->quotation_number }}</h1>
                <span class="pill pill-teal">Rev {{ $quotation->revision }}</span>
                <x-pill :status="$quotation->status" />
            </div>
            <p class="text-muted mt-1">{{ $quotation->title }} · {{ money($quotation->total) }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('quotations.print', $quotation) }}" target="_blank" class="btn btn-secondary"><x-icon name="printer" /> Print / PDF</a>
            @can('quotations.manage')
            @if ($quotation->isEditable())
                <a href="{{ route('quotations.edit', $quotation) }}" class="btn btn-secondary"><x-icon name="pencil" /> Edit</a>
            @endif
            @if (in_array($quotation->status, ['sent', 'rejected', 'accepted', 'expired']) && ! $quotation->invoice_id)
                <x-action :url="route('quotations.revise', $quotation)" method="POST" class="btn btn-secondary"><x-icon name="copy" /> New revision</x-action>
            @endif
            @if ($quotation->status === 'draft')
                <x-action :url="route('quotations.status', $quotation)" data-status="sent" class="btn btn-primary"><x-icon name="send" /> Mark sent</x-action>
            @endif
            @if ($quotation->status === 'sent')
                <x-action :url="route('quotations.status', $quotation)" data-status="rejected" class="btn btn-secondary">Rejected</x-action>
                <x-action :url="route('quotations.status', $quotation)" data-status="accepted" class="btn btn-primary"><x-icon name="check" /> Accepted</x-action>
            @endif
            @if ($quotation->status === 'accepted' && ! $quotation->invoice_id)
                <x-action :url="route('quotations.convert', $quotation)" method="POST" class="btn btn-primary"><x-icon name="arrow-right-left" /> Convert to invoice</x-action>
            @endif
            @endcan
            @if ($quotation->invoice)
                <a href="{{ route('invoices.show', $quotation->invoice) }}" class="btn btn-primary"><x-icon name="receipt" /> {{ $quotation->invoice->invoice_number }}</a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-6">
        @include('partials.doc-preview', ['doc' => $quotation, 'kind' => 'Quotation'])
        <aside class="space-y-4">
            <div class="card card-pad text-[13px] space-y-2">
                <div class="section-title mb-1">Details</div>
                <div class="flex justify-between"><span class="text-muted">Issued</span><span>{{ $quotation->issue_date->format('d M Y') }}</span></div>
                <div class="flex justify-between"><span class="text-muted">Valid until</span><span>{{ $quotation->valid_until?->format('d M Y') ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-muted">Sent</span><span>{{ $quotation->sent_at?->format('d M Y') ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-muted">Created by</span><span>{{ $quotation->creator?->name ?? '—' }}</span></div>
            </div>
            <div class="card card-pad">
                <div class="section-title mb-2">Revisions</div>
                <ol class="space-y-2">
                    @foreach ($quotation->revisions as $rev)
                        <li>
                            <a href="{{ route('quotations.show', $rev) }}" @class(['flex items-center justify-between rounded-lg px-2.5 py-2 text-[13px]', 'bg-accent-soft' => $rev->is($quotation), 'hover:bg-surface-2' => ! $rev->is($quotation)])>
                                <span class="font-medium">Rev {{ $rev->revision }}</span>
                                <span class="text-muted">{{ money($rev->total) }}</span>
                                <x-pill :status="$rev->status" />
                            </a>
                        </li>
                    @endforeach
                </ol>
            </div>
        </aside>
    </div>
</x-layouts.app>
