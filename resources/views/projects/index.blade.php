<x-layouts.app title="Projects">
    @php $total = $counts->sum(); @endphp
    <x-page-header title="Projects" :subtitle="$total.' '.str('project')->plural($total).' · '.\App\Models\Setting::get('finance.currency').' '.money_short($totalValue).' total value'">
        <x-slot:actions>
            <a href="{{ route('clients.index') }}" class="btn btn-ghost"><x-icon name="building-2" /> Clients</a>
            <div class="seg">
                <a href="{{ route('projects.index', ['view' => 'table'] + request()->except('view', 'page')) }}" @class(['seg-item', 'seg-active' => $view !== 'board'])>Table</a>
                <a href="{{ route('projects.index', ['view' => 'board'] + request()->except('view', 'page')) }}" @class(['seg-item', 'seg-active' => $view === 'board'])>Board</a>
            </div>
            <a href="{{ route('projects.create') }}" class="btn btn-primary"><x-icon name="plus" /> New project</a>
        </x-slot:actions>
    </x-page-header>

    <form class="flex flex-wrap items-center gap-3 mb-3.5">
        <input type="hidden" name="view" value="{{ $view }}">
        @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
        @if ($view !== 'board')
            <div class="seg overflow-x-auto max-w-full">
                <a href="{{ route('projects.index', request()->except('status', 'page')) }}" @class(['seg-item', 'seg-active' => ! request('status')])>All <small>{{ $total }}</small></a>
                @foreach (collect(\App\Models\Project::STATUSES)->except('cancelled') as $key => $label)
                    <a href="{{ route('projects.index', ['status' => $key] + request()->except('status', 'page')) }}" @class(['seg-item', 'seg-active' => request('status') === $key])>{{ $label }} <small>{{ $counts[$key] ?? 0 }}</small></a>
                @endforeach
            </div>
        @endif
        <div class="relative ml-auto w-full sm:w-64">
            <x-icon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted size-4" />
            <input name="q" value="{{ request('q') }}" placeholder="Filter by name, client or code" class="input pl-9">
        </div>
    </form>

    @if ($view === 'board')
        <div class="flex gap-4 overflow-x-auto pb-4">
            @foreach (collect(\App\Models\Project::STATUSES)->except('cancelled') as $key => $label)
                @php $column = $projects->where('status', $key); @endphp
                <div class="w-72 shrink-0">
                    <div class="flex items-center justify-between px-1 mb-2">
                        <x-pill :status="$key" :label="$label" />
                        <span class="text-xs text-muted">{{ $column->count() }}</span>
                    </div>
                    <div class="space-y-2.5">
                        @forelse ($column as $p)
                            <a href="{{ route('projects.show', $p) }}" class="card block p-4 no-underline text-ink hover:border-accent/40 hover:no-underline">
                                <div class="font-mono text-[11px] text-muted">{{ $p->code }}</div>
                                <div class="font-medium leading-snug">{{ $p->name }}</div>
                                <div class="text-xs text-muted mt-0.5">{{ $p->client?->displayName() }}</div>
                                <div class="flex items-center justify-between mt-3 text-xs">
                                    <span class="font-medium tabular-nums">{{ money($p->project_value) }}</span>
                                    @if ($p->nextFollowUp)
                                        <span @class(['inline-flex items-center gap-1', 'text-red-fg' => $p->nextFollowUp->isOverdue(), 'text-muted' => ! $p->nextFollowUp->isOverdue()])>
                                            <x-icon name="calendar-clock" class="size-3.5" />{{ $p->nextFollowUp->due_date->format('d M') }}
                                        </span>
                                    @endif
                                </div>
                            </a>
                        @empty
                            <div class="rounded-card border border-dashed border-line py-8 text-center text-xs text-muted">No projects</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Project</th><th>Client</th><th>Status</th><th class="text-right">Value</th><th>Next follow-up</th><th class="text-right">Outstanding</th><th>Lead</th></tr></thead>
                    <tbody>
                    @forelse ($projects as $p)
                        <tr class="cursor-pointer" onclick="location='{{ route('projects.show', $p) }}'">
                            <td><div class="font-medium">{{ $p->name }}</div><div class="font-mono text-[11.5px] text-muted mt-0.5">{{ $p->code }}</div></td>
                            <td class="text-ink-2">{{ $p->client?->displayName() ?? '—' }}</td>
                            <td><x-pill :status="$p->status" :label="\App\Models\Project::STATUSES[$p->status]" /></td>
                            <td class="text-right tabular-nums font-medium">{{ money($p->project_value) }}</td>
                            <td>
                                @if ($p->nextFollowUp)
                                    @php $fu = $p->nextFollowUp->due_date; @endphp
                                    <span @class(['font-medium text-red-fg' => $fu->isPast() && ! $fu->isToday(), 'text-amber-fg' => $fu->between(today(), today()->addDays(2)), 'text-ink-2' => $fu->gt(today()->addDays(2))]) title="{{ $p->nextFollowUp->title }}">{{ $fu->format('j M') }}</span>
                                @else <span class="text-muted">—</span> @endif
                            </td>
                            <td class="text-right tabular-nums {{ $p->outstanding > 0 ? 'text-ink' : 'text-muted' }}">{{ $p->outstanding > 0 ? money($p->outstanding) : '—' }}</td>
                            <td>@if ($p->manager)<x-avatar :name="$p->manager->name" size="size-[26px] text-[10px]" />@else <span class="text-muted">—</span> @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty icon="folder-kanban" title="No projects found" text="Create your first project to start tracking quotes, invoices and follow-ups."><a href="{{ route('projects.create') }}" class="btn btn-primary">New project</a></x-empty></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $projects->links() }}
        </div>
    @endif
</x-layouts.app>
