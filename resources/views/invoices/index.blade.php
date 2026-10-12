<x-layouts.app title="Invoices">
    @php
        $cur = \App\Models\Setting::get('finance.currency');
        $all = $summary->except('cancelled');
        $unpaid = $summary->only(['sent', 'partially_paid', 'overdue'])->sum('balance');
        $cards = ['' => 'All', 'draft' => 'Draft', 'sent' => 'Sent', 'partially_paid' => 'Partially paid', 'overdue' => 'Overdue', 'paid' => 'Paid'];
        $dot = ['' => 'bg-grey-fg', 'draft' => 'bg-grey-fg', 'sent' => 'bg-blue-fg', 'partially_paid' => 'bg-amber-fg', 'overdue' => 'bg-red-fg', 'paid' => 'bg-green-fg'];
    @endphp
    <x-page-header title="Invoices" :subtitle="$all->sum('total').' invoices · '.$cur.' '.money_short($unpaid).' unpaid'">
        <x-slot:actions>
            <a href="{{ route('document-logs.index') }}" class="btn btn-ghost"><x-icon name="list" /> Sent log</a>
            @can('quotations.manage')<button class="btn btn-secondary" @click="$store.drawer.open('log-document')"><x-icon name="paperclip" /> Log sent document</button>@endcan
            @can('invoices.manage')<a href="{{ route('invoices.create') }}" class="btn btn-primary"><x-icon name="plus" /> New invoice</a>@endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-2.5 mb-4">
        @foreach ($cards as $key => $label)
            @php
                $count = $key === '' ? $all->sum('total') : ($summary[$key]->total ?? 0);
                $amount = $key === '' ? $all->sum('amount') : ($summary[$key]->amount ?? 0);
                $active = (string) request('status') === $key;
            @endphp
            <a href="{{ route('invoices.index', $key ? ['status' => $key] : []) }}" @class(['card px-3.5 py-3 no-underline text-ink hover:no-underline hover:border-accent/40', '!border-accent ring-1 ring-accent' => $active])>
                <div class="flex items-center gap-1.5 text-[13px] text-ink-2"><span class="size-1.5 rounded-full {{ $dot[$key] }}"></span>{{ $label }}<span class="ml-auto text-muted text-[12px]">{{ $count }}</span></div>
                <div class="text-[19px] font-semibold tracking-[-.01em] mt-1 tabular-nums">{{ $cur }} {{ money_short($amount) }}</div>
            </a>
        @endforeach
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Invoice</th><th>Project · Client</th><th>Issued</th><th>Due</th><th class="text-right">Amount</th><th class="text-right">Balance</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($invoices as $i)
                    <tr class="cursor-pointer" onclick="location='{{ route('invoices.show', $i) }}'">
                        <td class="font-mono text-[13px] font-medium whitespace-nowrap">{{ $i->invoice_number }}</td>
                        <td><div class="font-medium">{{ $i->project->name }}</div><div class="text-[12.5px] text-muted">{{ $i->client?->displayName() }}</div></td>
                        <td class="whitespace-nowrap text-ink-2">{{ $i->issue_date->format('j M') }}</td>
                        <td @class(['whitespace-nowrap', 'text-red-fg font-medium' => $i->status === 'overdue', 'text-ink-2' => $i->status !== 'overdue'])>{{ $i->due_date?->format('j M') ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ money($i->total) }}</td>
                        <td class="text-right tabular-nums font-semibold">{{ $i->status === 'draft' || $i->status === 'cancelled' ? '—' : money($i->balance_due) }}</td>
                        <td><x-pill :status="$i->status" /></td>
                        <td class="text-right" onclick="event.stopPropagation()">
                            @if (in_array($i->status, ['sent', 'partially_paid', 'overdue']) && auth()->user()->hasPermission('invoices.manage'))
                                <a href="{{ route('invoices.show', [$i, 'pay' => 1]) }}" class="btn btn-secondary btn-sm"><x-icon name="credit-card" /> Record payment</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty icon="receipt" title="No invoices here" text="Create an invoice or convert an accepted quotation."><a href="{{ route('invoices.create') }}" class="btn btn-primary">New invoice</a></x-empty></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $invoices->links() }}
    </div>
</x-layouts.app>
