<x-layouts.app title="Invoices">
    <x-page-header title="Invoices" subtitle="Bill clients, record payments and chase what's overdue.">
        <x-slot:actions>
            <button class="btn btn-secondary" @click="$store.drawer.open('log-document')"><x-icon name="mail" /> Log outside invoice</button>
            <a href="{{ route('invoices.create') }}" class="btn btn-primary"><x-icon name="plus" /> New invoice</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        @foreach (['draft' => 'Draft', 'sent' => 'Sent', 'partially_paid' => 'Partially paid', 'overdue' => 'Overdue'] as $key => $label)
            <a href="{{ route('invoices.index', ['status' => $key]) }}" @class(['card card-pad hover:border-accent/40', '!border-accent' => request('status') === $key])>
                <div class="flex items-center justify-between"><x-pill :status="$key" :label="$label" /><span class="text-xs text-muted">{{ $summary[$key]->total ?? 0 }}</span></div>
                <div class="text-xl font-semibold mt-2 tabular-nums">{{ money($key === 'draft' ? ($summary[$key]->amount ?? 0) : ($summary[$key]->balance ?? 0)) }}</div>
                <div class="text-xs text-muted">{{ $key === 'draft' ? 'not yet sent' : 'balance due' }}</div>
            </a>
        @endforeach
    </div>

    <div class="flex flex-wrap gap-2 mb-4">
        <a href="{{ route('invoices.index') }}" @class(['chip', 'chip-active' => ! request('status')])>All</a>
        @foreach (['paid', 'cancelled'] as $s)
            <a href="{{ route('invoices.index', ['status' => $s]) }}" @class(['chip', 'chip-active' => request('status') === $s])>{{ \App\Models\Invoice::STATUSES[$s] }} <span class="text-xs opacity-70">{{ $summary[$s]->total ?? 0 }}</span></a>
        @endforeach
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Number</th><th>Project</th><th>Issued</th><th>Due</th><th>Status</th><th class="text-right">Total</th><th class="text-right">Balance</th></tr></thead>
                <tbody>
                @forelse ($invoices as $i)
                    <tr class="cursor-pointer" onclick="location='{{ route('invoices.show', $i) }}'">
                        <td class="font-medium">{{ $i->invoice_number }}</td>
                        <td><div>{{ $i->project->name }}</div><div class="text-xs text-muted">{{ $i->client?->displayName() }}</div></td>
                        <td class="whitespace-nowrap">{{ $i->issue_date->format('d M Y') }}</td>
                        <td @class(['whitespace-nowrap', 'text-red-600 font-medium' => $i->status === 'overdue'])>{{ $i->due_date?->format('d M Y') ?? '—' }}</td>
                        <td><x-pill :status="$i->status" /></td>
                        <td class="text-right tabular-nums">{{ money($i->total) }}</td>
                        <td class="text-right tabular-nums font-medium">{{ $i->balance_due > 0 ? money($i->balance_due) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty icon="receipt" title="No invoices here" text="Create an invoice or convert an accepted quotation."><a href="{{ route('invoices.create') }}" class="btn btn-primary">New invoice</a></x-empty></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $invoices->links() }}
    </div>
</x-layouts.app>
