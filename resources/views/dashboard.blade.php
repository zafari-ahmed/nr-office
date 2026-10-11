<x-layouts.app title="Dashboard">
    @php
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $stackedChart = ['type' => 'stacked', 'labels' => $charts['labels'], 'series' => $charts['heads']];
        $lineChart = ['type' => 'line', 'labels' => $charts['labels'], 'series' => [
            ['label' => 'Income', 'data' => $charts['income'], 'color' => 'teal', 'fill' => true],
            ['label' => 'Expenses', 'data' => $charts['expenses'], 'color' => 'rose'],
        ]];
    @endphp

    <x-page-header :title="$greeting.', '.strtok(auth()->user()->name, ' ')" :subtitle="now()->format('l, d F Y')">
        <x-slot:actions>
            <button class="btn btn-secondary" @click="$store.drawer.open('log-document')"><x-icon name="mail" /> Log sent document</button>
            <a href="{{ route('quotations.create') }}" class="btn btn-primary"><x-icon name="plus" /> New quotation</a>
        </x-slot:actions>
    </x-page-header>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <x-stat label="Active projects" :value="$stats['active_projects']" icon="folder-kanban" :href="route('projects.index', ['status' => 'active'])" hint="in progress" />
        <x-stat label="Quotations pending" :value="$stats['quotes_pending']" icon="file-text" :href="route('quotations.index', ['status' => 'sent'])" hint="awaiting client reply" />
        <x-stat label="Unpaid invoices" :value="'PKR '.money_short($stats['unpaid_amount'])" icon="receipt" :href="route('invoices.index', ['status' => 'overdue'])" :hint="$stats['unpaid_count'].' invoices open'" />
        <x-stat label="Expenses this month" :value="'PKR '.money_short($stats['expenses_this_month'])" icon="wallet" :href="route('expenses.index')" :trend="$stats['expenses_change']" hint="vs last month" />
        <x-stat label="Staff on site today" :value="$stats['on_site']" icon="hard-hat" :href="route('rotation.index')" hint="across all sites" class="col-span-2 lg:col-span-1" />
    </div>

    {{-- On site now --}}
    @if ($onSite->isNotEmpty())
        <div class="card card-pad mt-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="card-title">On site now</h2>
                <a href="{{ route('rotation.index') }}" class="text-[13px] link">Open rotation</a>
            </div>
            <div class="flex gap-3 overflow-x-auto pb-1">
                @foreach ($onSite as $visit)
                    @php $state = $visit->displayStatus(); @endphp
                    <a href="{{ route('employees.show', $visit->employee) }}" class="flex items-center gap-3 shrink-0 rounded-full border border-line pl-1.5 pr-4 py-1.5 hover:border-accent/40">
                        <x-avatar :name="$visit->employee->full_name" :src="$visit->employee->photoUrl()" />
                        <div class="leading-tight">
                            <div class="text-[13px] font-medium">{{ $visit->employee->full_name }}</div>
                            <div class="text-xs text-muted">{{ $visit->site_name }} · back <span @class(['text-red-600 font-medium' => $state === 'overdue', 'text-amber-600 font-medium' => $state === 'returning_soon'])>{{ $visit->expected_return_date->format('d M') }}</span></div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid lg:grid-cols-2 gap-6 mt-6">
        {{-- Needs attention --}}
        <div class="card">
            <div class="flex items-center justify-between px-5 pt-5 pb-3">
                <h2 class="card-title">Needs attention</h2>
                <span class="pill pill-red">{{ $attention->count() }}</span>
            </div>
            @forelse ($attention as $row)
                <div class="flex items-center gap-3 px-5 py-3 border-t border-line">
                    <span class="pill-{{ $row['tone'] }} grid place-items-center size-9 rounded-lg shrink-0"><x-icon :name="$row['icon']" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="text-[13.5px] font-medium truncate">{{ $row['title'] }}</div>
                        <div class="text-xs text-muted truncate">{{ $row['label'] }} · {{ $row['meta'] }}@if ($row['date']) · {{ $row['date']->format('d M') }}@endif</div>
                    </div>
                    @if (($row['action']['method'] ?? null) === 'PATCH')
                        <x-action :url="$row['action']['url']" class="btn btn-secondary btn-xs">{{ $row['action']['label'] }}</x-action>
                    @else
                        <a href="{{ $row['action']['url'] }}" class="btn btn-secondary btn-xs">{{ $row['action']['label'] }}</a>
                    @endif
                </div>
            @empty
                <x-empty icon="check-circle-2" title="Nothing needs attention" text="No overdue follow-ups, invoices or returns. Nice." />
            @endforelse
        </div>

        {{-- Upcoming follow-ups --}}
        <div class="card">
            <div class="flex items-center justify-between px-5 pt-5 pb-3">
                <h2 class="card-title">Upcoming follow-ups</h2>
                <button class="btn btn-ghost btn-sm" @click="$store.drawer.open('follow-up')"><x-icon name="plus" /> Add</button>
            </div>
            @forelse ($upcoming as $projectName => $items)
                <div class="px-5 pt-3 pb-1 border-t border-line">
                    <div class="section-title">{{ $projectName }}</div>
                </div>
                @foreach ($items as $f)
                    <div class="flex items-center gap-3 px-5 py-2.5">
                        <div class="text-center w-11 shrink-0">
                            <div class="text-[11px] uppercase text-muted">{{ $f->due_date->format('D') }}</div>
                            <div class="font-semibold leading-none">{{ $f->due_date->format('d') }}</div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-[13.5px] truncate">{{ $f->title }}</div>
                            <div class="text-xs text-muted">{{ $f->assignee?->name ?? 'Unassigned' }}</div>
                        </div>
                        <x-action :url="route('follow-ups.snooze', $f)" data-days="2" class="btn btn-ghost btn-xs" title="Snooze 2 days"><x-icon name="alarm-clock" /> Snooze</x-action>
                        <x-action :url="route('follow-ups.done', $f)" class="btn btn-secondary btn-xs"><x-icon name="check" /> Done</x-action>
                    </div>
                @endforeach
            @empty
                <x-empty icon="calendar-clock" title="No follow-ups in the next 7 days" text="Add one from any project, or log a sent quotation with a follow-up date." />
            @endforelse
        </div>
    </div>

    {{-- Charts --}}
    <div class="grid lg:grid-cols-2 gap-6 mt-6">
        <div class="card card-pad">
            <h2 class="card-title">Monthly expenses by head</h2>
            <p class="text-xs text-muted mb-4">Approved expenses, last 6 months</p>
            <div class="h-64"><canvas data-chart="{{ json_encode($stackedChart) }}"></canvas></div>
        </div>
        <div class="card card-pad">
            <h2 class="card-title">Income vs expenses</h2>
            <p class="text-xs text-muted mb-4">Payments received vs approved expenses</p>
            <div class="h-64"><canvas data-chart="{{ json_encode($lineChart) }}"></canvas></div>
        </div>
    </div>

    {{-- Recent activity --}}
    <div class="card mt-6">
        <div class="px-5 pt-5 pb-3"><h2 class="card-title">Recent activity</h2></div>
        @forelse ($activity as $a)
            <div class="flex items-start gap-3 px-5 py-3 border-t border-line">
                <x-avatar :name="$a->user?->name ?? 'System'" size="size-7 text-[10px]" />
                <div class="min-w-0 flex-1 text-[13.5px]">
                    <span class="font-medium">{{ $a->user?->name ?? 'System' }}</span>
                    <span class="text-muted">·</span> {{ $a->description }}
                    @if ($a->project)
                        <a href="{{ route('projects.show', $a->project) }}" class="text-xs link ml-1">{{ $a->project->name }}</a>
                    @endif
                </div>
                <span class="text-xs text-muted shrink-0">{{ $a->created_at->diffForHumans(short: true) }}</span>
            </div>
        @empty
            <x-empty icon="history" title="No activity yet" />
        @endforelse
    </div>
</x-layouts.app>
