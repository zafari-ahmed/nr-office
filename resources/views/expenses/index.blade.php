<x-layouts.app title="Expenses">
    @php $cur = \App\Models\Setting::get('finance.currency'); @endphp
    <x-page-header title="Expenses" :subtitle="$month->format('F Y').' · '.money($monthTotal).($budgetTotal > 0 ? ' of '.money($budgetTotal).' budget' : ' approved')">
        <x-slot:actions>
            <form class="flex items-center">
                <a href="{{ route('expenses.index', ['month' => $month->copy()->subMonthNoOverflow()->format('Y-m')]) }}" class="btn btn-icon" title="Previous month"><x-icon name="chevron-left" /></a>
                <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="input !h-[34px] !w-36" onchange="this.form.submit()">
                <a href="{{ route('expenses.index', ['month' => $month->copy()->addMonthNoOverflow()->format('Y-m')]) }}" class="btn btn-icon" title="Next month"><x-icon name="chevron-right" /></a>
            </form>
            @if ($pendingCount)
                <a href="{{ route('expenses.index', ['status' => 'pending', 'month' => $month->format('Y-m')]) }}" class="inline-flex items-center gap-2 h-[34px] pl-1.5 pr-3.5 rounded-full bg-amber-bg text-amber-fg text-[13px] font-medium no-underline hover:no-underline">
                    <span class="grid place-items-center size-[22px] rounded-full bg-amber-fg text-white text-[11.5px] font-semibold">{{ $pendingCount }}</span>
                    Pending approval · {{ money($pendingAmount) }}
                </a>
            @endif
            <button class="btn btn-primary" @click="$store.drawer.open('expense')"><x-icon name="plus" /> Add expense</button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3.5 mb-5">
        @foreach ($heads as $head)
            @php
                $spent = (float) $head->spent;
                $budget = (float) $head->monthly_budget;
                $pct = $budget > 0 ? round($spent / $budget * 100) : null;
                $barColor = $pct === null ? 'bg-accent' : ($pct > 100 ? 'bg-red-fg' : ($pct >= 90 ? 'bg-amber-fg' : 'bg-accent'));
                $pctColor = $pct === null ? 'text-muted' : ($pct > 100 ? 'text-red-fg' : ($pct >= 90 ? 'text-amber-fg' : 'text-muted'));
            @endphp
            <a href="{{ route('expenses.index', ['head' => $head->id, 'month' => $month->format('Y-m')]) }}" @class(['card px-4 py-3.5 no-underline text-ink hover:no-underline hover:border-accent/40', '!border-accent ring-1 ring-accent' => (string) request('head') === (string) $head->id])>
                <div class="flex items-center gap-2.5">
                    <span class="grid place-items-center size-[30px] rounded-lg bg-surface-2 text-ink-2"><x-icon :name="$head->icon ?: 'wallet'" class="size-4" /></span>
                    <span class="text-[14px] font-medium truncate">{{ $head->name }}</span>
                    <span class="ml-auto text-[12px] font-semibold {{ $pctColor }}">{{ $pct !== null ? $pct.'% used' : 'No budget' }}</span>
                </div>
                <div class="text-[22px] font-semibold tracking-[-.01em] mt-3 tabular-nums">{{ money($spent) }}</div>
                @if ($pct !== null)
                    <div class="bar mt-3"><span class="{{ $barColor }}" style="width: {{ min(100, $pct) }}%"></span></div>
                    <div class="flex justify-between text-[12px] text-muted mt-2"><span>of {{ money($budget) }}</span><span>{{ $spent > $budget ? money($spent - $budget).' over' : money($budget - $spent).' left' }}</span></div>
                @endif
            </a>
        @endforeach
        @if (auth()->user()->hasRole('admin', 'manager'))
            <a href="{{ route('settings.show', 'expense-heads') }}" class="rounded-card border border-dashed border-line grid place-items-center min-h-[140px] text-[13.5px] font-medium text-ink-2 no-underline hover:text-ink hover:border-accent/40 hover:no-underline">
                <span class="inline-flex items-center gap-1.5"><x-icon name="plus" class="size-4" /> Add expense head</span>
            </a>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-2 mb-3.5">
        <a href="{{ route('expenses.index', request()->except('head', 'page')) }}" @class(['chip', 'chip-active' => ! request('head')])>All</a>
        @foreach ($heads as $h)
            <a href="{{ route('expenses.index', ['head' => $h->id] + request()->except('head', 'page')) }}" @class(['chip', 'chip-active' => (string) request('head') === (string) $h->id])>{{ $h->name }}</a>
        @endforeach
        <form class="flex gap-2 ml-auto">
            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
            @if (request('head'))<input type="hidden" name="head" value="{{ request('head') }}">@endif
            <select name="status" class="input !h-[30px] !w-36 !text-[12.5px]" onchange="this.form.submit()">
                <option value="">Any status</option>
                @foreach (\App\Models\Expense::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
            </select>
            <select name="project" class="input !h-[30px] !w-48 !text-[12.5px]" onchange="this.form.submit()">
                <option value="">All projects</option>
                @foreach ($projects as $p)<option value="{{ $p->id }}" @selected((string) request('project') === (string) $p->id)>{{ $p->name }}</option>@endforeach
            </select>
        </form>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Date</th><th>Description</th><th>Head</th><th>Project</th><th>Paid by</th><th class="text-right">Amount</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($expenses as $e)
                    @php $payer = $e->employee?->full_name ?? $e->creator?->name; @endphp
                    <tr>
                        <td class="whitespace-nowrap text-ink-2">{{ $e->expense_date->format('j M') }}</td>
                        <td>
                            <div class="font-medium flex items-center gap-1.5">{{ $e->description }}
                                @foreach ($e->attachments as $r)<a href="{{ route('attachments.download', [$r, 'inline' => 1]) }}" target="_blank" title="Receipt" class="text-muted hover:text-accent"><x-icon name="paperclip" class="size-3.5" /></a>@endforeach
                            </div>
                            @if ($e->vendor || $e->payroll_month)<div class="text-[12px] text-muted">{{ $e->vendor }}{{ $e->payroll_month ? 'Payroll '.$e->payroll_month->format('M Y') : '' }}</div>@endif
                        </td>
                        <td class="text-ink-2">{{ $e->head->name }}</td>
                        <td class="text-ink-2">{{ $e->project?->name ?? '—' }}</td>
                        <td>@if ($payer)<span class="inline-flex items-center gap-2 whitespace-nowrap"><x-avatar :name="$payer" size="size-6 text-[9.5px]" />{{ $payer }}</span>@endif</td>
                        <td class="text-right tabular-nums font-semibold whitespace-nowrap">{{ money($e->amount) }}</td>
                        <td><x-pill :status="$e->status" /></td>
                        <td class="text-right whitespace-nowrap">
                            @if ($e->status === 'pending' && auth()->user()->hasRole('admin', 'manager', 'accounts'))
                                <x-action :url="route('expenses.approve', $e)" class="btn btn-secondary btn-sm text-green-fg"><x-icon name="check" /> Approve</x-action>
                                <x-action :url="route('expenses.reject', $e)" class="btn btn-secondary btn-sm !w-[30px] !px-0 text-red-fg" title="Reject"><x-icon name="x" /></x-action>
                            @else
                                <x-action :url="route('expenses.destroy', $e)" method="DELETE" confirm="Delete this expense?" class="btn btn-icon !size-7 !w-7 hover:text-red-fg" title="Delete"><x-icon name="trash-2" class="!size-3.5" /></x-action>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty icon="wallet" title="No expenses for this filter" text="Add salaries, daily office items or renovation costs with a receipt photo."><button class="btn btn-primary" @click="$store.drawer.open('expense')">Add expense</button></x-empty></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $expenses->links() }}
    </div>
</x-layouts.app>
