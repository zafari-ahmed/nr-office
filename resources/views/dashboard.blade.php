<x-layouts.app title="Dashboard">
    @php
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $stackedChart = ['type' => 'stacked', 'labels' => $charts['labels'], 'series' => $charts['heads']];
        $lineChart = ['type' => 'line', 'labels' => $charts['labels'], 'series' => [
            ['label' => 'Income', 'data' => $charts['income'], 'color' => 'teal', 'fill' => true],
            ['label' => 'Expenses', 'data' => $charts['expenses'], 'color' => 'amber'],
        ]];
        $cur = \App\Models\Setting::get('finance.currency', 'PKR');
        $lastMonthName = now()->subMonthNoOverflow()->format('M');
        $attentionCount = $attention->count();
    @endphp

    <x-page-header :eyebrow="now()->format('l, j F Y')" :title="$greeting.', '.strtok(auth()->user()->name, ' ')"
                   :subtitle="$attentionCount ? $attentionCount.' '.str('thing')->plural($attentionCount).' need your attention today' : 'Nothing needs your attention today'">
        <x-slot:actions>
            <a href="{{ route('rotation.index', ['schedule' => 1]) }}" class="btn btn-secondary"><x-icon name="map-pin" /> Schedule visit</a>
            <a href="{{ route('quotations.create') }}" class="btn btn-primary"><x-icon name="file-plus" /> New quotation</a>
        </x-slot:actions>
    </x-page-header>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3.5">
        <x-stat label="Active projects" :value="$stats['active_projects']" icon="folder-kanban" :href="route('projects.index', ['status' => 'active'])"
                :hint="$stats['leads'].' '.str('lead')->plural($stats['leads']).' in pipeline'" />
        <x-stat label="Quotations pending" :value="$stats['quotes_pending']" icon="file-text" :href="route('quotations.index', ['status' => 'sent'])"
                :hint="$cur.' '.money_short($stats['quotes_pending_value']).' awaiting reply'" />
        <x-stat label="Unpaid invoices" :value="$cur.' '.money_short($stats['unpaid_amount'])" icon="receipt" :href="route('invoices.index', ['status' => 'overdue'])"
                :hint="$stats['overdue_invoices'] ? $stats['overdue_invoices'].' overdue' : $stats['unpaid_count'].' open'" :tone="$stats['overdue_invoices'] ? 'red' : null" />
        <x-stat label="Expenses this month" :value="$cur.' '.money_short($stats['expenses_this_month'])" icon="wallet" :href="route('expenses.index')"
                :trend="$stats['expenses_change']" :hint="'vs '.$cur.' '.money_short($stats['expenses_last_month']).' in '.$lastMonthName" :tone="($stats['expenses_change'] ?? 0) > 0 ? 'red' : 'green'" />
        <x-stat label="Staff on site today" :value="$stats['on_site'].' / '.$stats['field_staff']" icon="hard-hat" :href="route('rotation.index')" class="col-span-2 lg:col-span-1"
                :hint="$stats['on_site_overdue'].' overdue · '.$stats['on_site_soon'].' back soon'" :tone="$stats['on_site_overdue'] ? 'amber' : null" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1.45fr_1fr] gap-4 mt-5 items-start">
        {{-- Needs attention --}}
        <div class="card overflow-hidden">
            <div class="card-head">
                <h2 class="card-title">Needs attention</h2>
                @if ($attentionCount)<span class="count bg-red-bg text-red-fg">{{ $attentionCount }}</span>@endif
                <span class="ml-auto text-[12.5px] text-muted">Sorted by urgency</span>
            </div>
            @forelse ($attention as $row)
                <div class="flex items-center gap-3 px-[18px] py-3 border-b border-line-2 last:border-b-0">
                    <span class="bg-{{ $row['tone'] }}-bg text-{{ $row['tone'] }}-fg grid place-items-center size-8 rounded-lg shrink-0"><x-icon :name="$row['icon']" class="size-4" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="text-[13.5px] font-medium truncate">{{ $row['title'] }}</div>
                        <div class="text-[12.5px] text-muted truncate">{{ $row['label'] }} · {{ $row['meta'] }}</div>
                    </div>
                    <span class="hidden sm:block text-[12.5px] font-medium text-{{ $row['tone'] }}-fg whitespace-nowrap">{{ $row['due'] ?? '' }}</span>
                    @if (($row['action']['method'] ?? null) === 'PATCH')
                        <x-action :url="$row['action']['url']" class="btn btn-secondary btn-sm">{{ $row['action']['label'] }}</x-action>
                    @else
                        <a href="{{ $row['action']['url'] }}" class="btn btn-secondary btn-sm">{{ $row['action']['label'] }}</a>
                    @endif
                </div>
            @empty
                <x-empty icon="check-circle-2" title="Nothing needs attention" text="No overdue follow-ups, invoices or returns. Nice." />
            @endforelse
        </div>

        {{-- Upcoming follow-ups --}}
        <div class="card overflow-hidden">
            <div class="card-head">
                <h2 class="card-title">Upcoming follow-ups</h2>
                <span class="ml-auto text-[12.5px] text-muted">Next 7 days</span>
            </div>
            @forelse ($upcoming as $items)
                @php $p = $items->first()->project; @endphp
                <div class="px-[18px] pt-3.5 pb-1 flex items-baseline gap-2">
                    <a href="{{ route('projects.show', $p) }}" class="text-[13px] font-semibold text-ink-2 hover:text-ink no-underline">{{ $p->name }}</a>
                    <span class="font-mono text-[11px] text-muted">{{ $p->code }}</span>
                </div>
                @foreach ($items as $f)
                    <div class="flex items-center gap-3 px-[18px] py-2">
                        <div class="text-center w-9 shrink-0">
                            <div class="text-[11px] text-muted">{{ $f->due_date->format('D') }}</div>
                            <div class="font-mono text-[16px] font-semibold leading-tight">{{ $f->due_date->format('j') }}</div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-[13.5px] leading-snug">{{ $f->title }}</div>
                            <div class="text-[12px] text-muted">{{ $f->assignee?->name ? strtok($f->assignee->name, ' ') : 'Unassigned' }}</div>
                        </div>
                        <x-action :url="route('follow-ups.done', $f)" class="btn btn-secondary btn-xs"><x-icon name="check" class="!size-3.5" /> Done</x-action>
                        <x-action :url="route('follow-ups.snooze', $f)" data-days="2" class="btn btn-secondary btn-xs" title="Snooze 2 days"><x-icon name="clock" class="!size-3.5" /> Snooze</x-action>
                    </div>
                @endforeach
            @empty
                <x-empty icon="calendar-clock" title="No follow-ups in the next 7 days" text="Add one from any project, or log a sent quotation with a follow-up date.">
                    <button class="btn btn-secondary btn-sm" @click="$store.drawer.open('follow-up')"><x-icon name="plus" /> Add follow-up</button>
                </x-empty>
            @endforelse
            <div class="h-2"></div>
        </div>
    </div>

    {{-- On site now --}}
    <div class="card mt-5 px-[18px] py-4">
        <div class="flex items-center gap-2 mb-3">
            <span class="size-2 rounded-full bg-blue-fg"></span>
            <h2 class="card-title">On site now</h2>
            <span class="text-[13px] text-muted">{{ $onSite->count() }} {{ str('person')->plural($onSite->count()) }}</span>
            <a href="{{ route('rotation.index') }}" class="ml-auto text-[13px] font-medium inline-flex items-center gap-1 no-underline hover:underline">Open rotation <x-icon name="arrow-right" class="size-3.5" /></a>
        </div>
        @if ($onSite->isEmpty())
            <p class="text-[13px] text-muted">Everyone is in the office today.</p>
        @else
            <div class="flex flex-wrap gap-2">
                @foreach ($onSite as $visit)
                    @php $state = $visit->displayStatus(); @endphp
                    <a href="{{ route('employees.show', $visit->employee) }}" @class(['flex items-center gap-2.5 rounded-full border pl-1.5 pr-4 py-1.5 no-underline text-ink hover:no-underline', 'border-red-fg' => $state === 'overdue', 'border-line hover:border-accent/40' => $state !== 'overdue'])>
                        <x-avatar :name="$visit->employee->full_name" :src="$visit->employee->photoUrl()" size="size-[30px] text-[11px]" />
                        <div class="leading-tight">
                            <div class="text-[13px] font-medium">{{ $visit->employee->full_name }}</div>
                            <div class="text-[12px] text-muted">{{ $visit->site_name }} ·
                                @if ($state === 'overdue')
                                    <span class="text-red-fg font-medium">Overdue</span>
                                @else
                                    <span @class(['text-amber-fg font-medium' => $state === 'returning_soon'])>Back {{ $visit->expected_return_date->format('j M') }}</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Charts --}}
    <div class="grid lg:grid-cols-2 gap-4 mt-5">
        <div class="card px-[18px] py-4">
            <div class="flex items-baseline gap-2"><h2 class="card-title">Expenses by head</h2><span class="text-[12.5px] text-muted">Last 6 months</span></div>
            <div class="h-64 mt-2"><canvas data-chart="{{ json_encode($stackedChart) }}"></canvas></div>
        </div>
        <div class="card px-[18px] py-4">
            <div class="flex items-baseline gap-2"><h2 class="card-title">Income vs expenses</h2><span class="text-[12.5px] text-muted">Last 6 months</span></div>
            <div class="h-64 mt-2"><canvas data-chart="{{ json_encode($lineChart) }}"></canvas></div>
        </div>
    </div>

    {{-- Recent activity --}}
    <div class="card mt-5 overflow-hidden">
        <div class="card-head"><h2 class="card-title">Recent activity</h2></div>
        @forelse ($activity as $a)
            <div class="flex items-center gap-3 px-[18px] py-2.5 border-b border-line-2 last:border-b-0">
                <x-avatar :name="$a->user?->name ?? 'System'" size="size-7 text-[10.5px]" />
                <div class="min-w-0 flex-1 text-[13.5px] text-ink-2 truncate">
                    <span class="font-medium text-ink">{{ $a->user?->name ?? 'System' }}</span>
                    {{ $a->description }}
                    @if ($a->project)
                        <a href="{{ route('projects.show', $a->project) }}" class="text-[12.5px] ml-1">{{ $a->project->name }}</a>
                    @endif
                </div>
                <x-icon :name="activity_style($a->action)[0]" class="size-4 text-muted shrink-0" />
                <span class="w-20 text-right text-[12.5px] text-muted shrink-0">{{ $a->created_at->isToday() ? $a->created_at->diffForHumans(short: true) : ($a->created_at->isYesterday() ? 'Yesterday' : $a->created_at->format('j M')) }}</span>
            </div>
        @empty
            <x-empty icon="history" title="No activity yet" />
        @endforelse
    </div>
</x-layouts.app>
