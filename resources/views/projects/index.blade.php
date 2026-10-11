<x-layouts.app title="Projects">
    <x-page-header title="Projects" subtitle="Every client job, from first lead to completion.">
        <x-slot:actions>
            <div class="inline-flex rounded-lg border border-line bg-surface p-0.5">
                <a href="{{ route('projects.index', ['view' => 'table'] + request()->except('view', 'page')) }}" @class(['btn btn-sm', 'bg-surface-2 text-ink' => $view !== 'board', 'text-muted' => $view === 'board'])><x-icon name="list" /> Table</a>
                <a href="{{ route('projects.index', ['view' => 'board'] + request()->except('view', 'page')) }}" @class(['btn btn-sm', 'bg-surface-2 text-ink' => $view === 'board', 'text-muted' => $view !== 'board'])><x-icon name="columns-3" /> Board</a>
            </div>
            <a href="{{ route('projects.create') }}" class="btn btn-primary"><x-icon name="plus" /> New project</a>
        </x-slot:actions>
    </x-page-header>

    <form class="flex flex-wrap items-center gap-2 mb-4">
        <input type="hidden" name="view" value="{{ $view }}">
        <div class="relative">
            <x-icon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
            <input name="q" value="{{ request('q') }}" placeholder="Search name or code" class="input !h-9 pl-9 w-64">
        </div>
        @if ($view !== 'board')
            <a href="{{ route('projects.index', request()->except('status', 'page')) }}" @class(['chip', 'chip-active' => ! request('status')])>All</a>
            @foreach (\App\Models\Project::STATUSES as $key => $label)
                <a href="{{ route('projects.index', ['status' => $key] + request()->except('status', 'page')) }}" @class(['chip', 'chip-active' => request('status') === $key])>
                    {{ $label }} <span class="text-xs opacity-70">{{ $counts[$key] ?? 0 }}</span>
                </a>
            @endforeach
        @endif
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
                            <a href="{{ route('projects.show', $p) }}" class="card block p-4 hover:border-accent/40">
                                <div class="text-[11px] text-muted">{{ $p->code }}</div>
                                <div class="font-medium leading-snug">{{ $p->name }}</div>
                                <div class="text-xs text-muted mt-0.5">{{ $p->client?->displayName() }}</div>
                                <div class="flex items-center justify-between mt-3 text-xs">
                                    <span class="font-medium">{{ money($p->project_value) }}</span>
                                    @if ($p->nextFollowUp)
                                        <span @class(['inline-flex items-center gap-1', 'text-red-600' => $p->nextFollowUp->isOverdue(), 'text-muted' => ! $p->nextFollowUp->isOverdue()])>
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
                    <thead><tr><th>Project</th><th>Client</th><th>Status</th><th class="text-right">Value</th><th>Next follow-up</th><th class="text-right">Outstanding</th></tr></thead>
                    <tbody>
                    @forelse ($projects as $p)
                        <tr class="cursor-pointer" onclick="location='{{ route('projects.show', $p) }}'">
                            <td><div class="font-medium">{{ $p->name }}</div><div class="text-xs text-muted">{{ $p->code }}@if ($p->city) · {{ $p->city }}@endif</div></td>
                            <td>{{ $p->client?->displayName() ?? '—' }}</td>
                            <td><x-pill :status="$p->status" :label="\App\Models\Project::STATUSES[$p->status]" /></td>
                            <td class="text-right tabular-nums">{{ money($p->project_value) }}</td>
                            <td>
                                @if ($p->nextFollowUp)
                                    <span @class(['text-red-600 font-medium' => $p->nextFollowUp->isOverdue()])>{{ $p->nextFollowUp->due_date->format('d M Y') }}</span>
                                    <div class="text-xs text-muted truncate max-w-48">{{ $p->nextFollowUp->title }}</div>
                                @else <span class="text-muted">—</span> @endif
                            </td>
                            <td class="text-right tabular-nums {{ $p->outstanding > 0 ? 'text-amber-600 font-medium' : 'text-muted' }}">{{ $p->outstanding > 0 ? money($p->outstanding) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty icon="folder-kanban" title="No projects found" text="Create your first project to start tracking quotes, invoices and follow-ups."><a href="{{ route('projects.create') }}" class="btn btn-primary">New project</a></x-empty></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $projects->links() }}
        </div>
    @endif
</x-layouts.app>
