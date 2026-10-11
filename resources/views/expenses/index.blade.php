<x-layouts.app title="Expenses">
    <x-page-header title="Expenses" :subtitle="$month->format('F Y').' · '.money($monthTotal).' approved'">
        <x-slot:actions>
            <form class="flex items-center gap-1">
                <a href="{{ route('expenses.index', ['month' => $month->copy()->subMonthNoOverflow()->format('Y-m')]) }}" class="btn btn-icon"><x-icon name="chevron-left" /></a>
                <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="input !h-9 !w-40" onchange="this.form.submit()">
                <a href="{{ route('expenses.index', ['month' => $month->copy()->addMonthNoOverflow()->format('Y-m')]) }}" class="btn btn-icon"><x-icon name="chevron-right" /></a>
            </form>
            @if ($pendingCount)
                <a href="{{ route('expenses.index', ['status' => 'pending', 'month' => $month->format('Y-m')]) }}" class="btn btn-secondary">Pending approval <span class="pill pill-amber !h-5 before:hidden">{{ $pendingCount }}</span></a>
            @endif
            <button class="btn btn-primary" @click="$store.drawer.open('expense')"><x-icon name="plus" /> Add expense</button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @foreach ($heads as $head)
            @php
                $spent = (float) $head->spent;
                $pct = $head->monthly_budget > 0 ? round($spent / $head->monthly_budget * 100) : null;
                $barColor = $pct === null ? 'bg-accent' : ($pct >= 100 ? 'bg-red-500' : ($pct >= 80 ? 'bg-amber-500' : 'bg-accent'));
            @endphp
            <a href="{{ route('expenses.index', ['head' => $head->id, 'month' => $month->format('Y-m')]) }}" @class(['card card-pad hover:border-accent/40', '!border-accent' => (string) request('head') === (string) $head->id])>
                <div class="flex items-center gap-2 text-[13px] text-muted"><span class="size-2.5 rounded-full" style="background: {{ ['teal' => '#0d9488', 'indigo' => '#6366f1', 'amber' => '#f59e0b', 'rose' => '#f43f5e', 'sky' => '#0ea5e9', 'violet' => '#8b5cf6', 'emerald' => '#10b981', 'slate' => '#64748b'][$head->color] ?? '#64748b' }}"></span>{{ $head->name }}</div>
                <div class="text-xl font-semibold mt-1.5 tabular-nums">{{ money($spent) }}</div>
                @if ($pct !== null)
                    <div class="bar mt-3"><span class="{{ $barColor }}" style="width: {{ min(100, $pct) }}%"></span></div>
                    <div class="flex justify-between text-xs text-muted mt-1.5"><span>{{ $pct }}% of budget</span><span>{{ money_short($head->monthly_budget) }}</span></div>
                @else
                    <div class="text-xs text-muted mt-3">No budget set</div>
                @endif
            </a>
        @endforeach
    </div>

    <form class="flex flex-wrap gap-2 mb-4">
        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
        <select name="head" class="input !h-9 !w-48" onchange="this.form.submit()">
            <option value="">All heads</option>
            @foreach ($heads as $h)<option value="{{ $h->id }}" @selected((string) request('head') === (string) $h->id)>{{ $h->name }}</option>@endforeach
        </select>
        <select name="status" class="input !h-9 !w-40" onchange="this.form.submit()">
            <option value="">Any status</option>
            @foreach (\App\Models\Expense::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
        </select>
        <select name="project" class="input !h-9 !w-56" onchange="this.form.submit()">
            <option value="">All projects</option>
            @foreach ($projects as $p)<option value="{{ $p->id }}" @selected((string) request('project') === (string) $p->id)>{{ $p->name }}</option>@endforeach
        </select>
        @if (request()->hasAny(['head', 'status', 'project']))<a href="{{ route('expenses.index', ['month' => $month->format('Y-m')]) }}" class="btn btn-ghost btn-sm">Clear</a>@endif
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Date</th><th>Description</th><th>Head</th><th>Project / employee</th><th>Status</th><th class="text-right">Amount</th><th></th></tr></thead>
                <tbody>
                @forelse ($expenses as $e)
                    <tr>
                        <td class="whitespace-nowrap">{{ $e->expense_date->format('d M') }}</td>
                        <td>
                            <div class="font-medium flex items-center gap-1.5">{{ $e->description }}
                                @foreach ($e->attachments as $r)<a href="{{ route('attachments.download', [$r, 'inline' => 1]) }}" target="_blank" title="Receipt" class="text-muted hover:text-accent"><x-icon name="paperclip" class="size-3.5" /></a>@endforeach
                            </div>
                            <div class="text-xs text-muted">{{ $e->vendor }}{{ $e->vendor ? ' · ' : '' }}{{ \App\Models\Expense::METHODS[$e->payment_method] ?? $e->payment_method }} · by {{ $e->creator?->name }}</div>
                        </td>
                        <td>{{ $e->head->name }}</td>
                        <td class="text-[13px]">{{ $e->project?->name ?? '' }}{{ $e->employee ? $e->employee->full_name.($e->payroll_month ? ' · '.$e->payroll_month->format('M Y') : '') : '' }}@if (! $e->project && ! $e->employee)<span class="text-muted">Office</span>@endif</td>
                        <td><x-pill :status="$e->status" /></td>
                        <td class="text-right tabular-nums font-medium">{{ money($e->amount) }}</td>
                        <td class="text-right whitespace-nowrap">
                            @if ($e->status === 'pending' && auth()->user()->hasRole('admin', 'manager', 'accounts'))
                                <x-action :url="route('expenses.approve', $e)" class="btn btn-secondary btn-xs"><x-icon name="check" /> Approve</x-action>
                                <x-action :url="route('expenses.reject', $e)" class="btn btn-ghost btn-xs">Reject</x-action>
                            @endif
                            <x-action :url="route('expenses.destroy', $e)" method="DELETE" confirm="Delete this expense?" class="btn btn-icon !size-7 hover:text-red-600"><x-icon name="trash-2" /></x-action>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty icon="wallet" title="No expenses for this filter" text="Add salaries, daily office items or renovation costs with a receipt photo."><button class="btn btn-primary" @click="$store.drawer.open('expense')">Add expense</button></x-empty></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $expenses->links() }}
    </div>
</x-layouts.app>
