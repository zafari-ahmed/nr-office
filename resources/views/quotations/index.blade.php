<x-layouts.app title="Quotations">
    <x-page-header title="Quotations" subtitle="Build, send and track quotes. Accepted quotes convert to invoices.">
        <x-slot:actions>
            <button class="btn btn-secondary" @click="$store.drawer.open('log-document')"><x-icon name="mail" /> Log outside quote</button>
            <a href="{{ route('quotations.create') }}" class="btn btn-primary"><x-icon name="plus" /> New quotation</a>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-wrap gap-2 mb-4">
        <a href="{{ route('quotations.index') }}" @class(['chip', 'chip-active' => ! request('status')])>All current</a>
        @foreach (['draft', 'sent', 'accepted', 'rejected', 'expired', 'revised'] as $s)
            <a href="{{ route('quotations.index', ['status' => $s]) }}" @class(['chip', 'chip-active' => request('status') === $s])>
                {{ \App\Models\Quotation::STATUSES[$s] }} <span class="text-xs opacity-70">{{ $counts[$s]->total ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Number</th><th>Title</th><th>Project</th><th>Issued</th><th>Valid until</th><th>Status</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                @forelse ($quotations as $q)
                    <tr class="cursor-pointer" onclick="location='{{ route('quotations.show', $q) }}'">
                        <td class="font-medium whitespace-nowrap">{{ $q->quotation_number }} <span class="pill pill-gray !h-5 before:hidden">Rev {{ $q->revision }}</span></td>
                        <td>{{ $q->title }}</td>
                        <td><div>{{ $q->project->name }}</div><div class="text-xs text-muted">{{ $q->client?->displayName() }}</div></td>
                        <td class="whitespace-nowrap">{{ $q->issue_date->format('d M Y') }}</td>
                        <td @class(['whitespace-nowrap', 'text-red-fg' => $q->status === 'sent' && $q->valid_until?->isPast()])>{{ $q->valid_until?->format('d M Y') ?? '—' }}</td>
                        <td><x-pill :status="$q->status" /></td>
                        <td class="text-right tabular-nums font-medium">{{ money($q->total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty icon="file-text" title="No quotations here" text="Create one with line items and a live preview, or log a quote you sent from Word or Excel."><a href="{{ route('quotations.create') }}" class="btn btn-primary">New quotation</a></x-empty></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $quotations->links() }}
    </div>
</x-layouts.app>
