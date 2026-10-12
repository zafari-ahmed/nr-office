<x-layouts.app title="Employees">
    @php
        $live = ['in_office' => ['In office', 'green'], 'on_site' => ['On site', 'blue'], 'on_leave' => ['On leave', 'gray']];
        $onSite = $liveCounts['on_site'] ?? 0;
    @endphp
    <x-page-header title="Employees" :subtitle="$total.' people · '.$onSite.' on site today'">
        <x-slot:actions>
            @can('employees.manage')<a href="{{ route('employees.create') }}" class="btn btn-primary"><x-icon name="user-plus" /> Add employee</a>@endcan
        </x-slot:actions>
    </x-page-header>

    <form class="flex flex-wrap items-center gap-3 mb-4">
        <div class="seg">
            <a href="{{ route('employees.index', array_filter(['q' => request('q'), 'status' => request('status')])) }}" @class(['seg-item', 'seg-active' => ! request('live')])>All <small>{{ $total }}</small></a>
            @foreach ($live as $k => [$l])
                <a href="{{ route('employees.index', array_filter(['live' => $k, 'q' => request('q'), 'status' => request('status')])) }}" @class(['seg-item', 'seg-active' => request('live') === $k])>{{ $l }} <small>{{ $liveCounts[$k] ?? 0 }}</small></a>
            @endforeach
        </div>
        <label class="inline-flex items-center gap-2 text-[13px] text-ink-2">
            <input type="checkbox" name="status" value="all" class="checkbox" @checked(request('status') === 'all') onchange="this.form.submit()"> Include former staff
        </label>
        @if (request('live'))<input type="hidden" name="live" value="{{ request('live') }}">@endif
        <div class="relative ml-auto w-full sm:w-60">
            <x-icon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted size-4" />
            <input name="q" value="{{ request('q') }}" placeholder="Name, designation or CNIC" class="input pl-9">
        </div>
    </form>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3.5">
        @forelse ($employees as $e)
            @php
                [$pillLabel, $pillTone] = $live[$e->live_status] ?? [status_label($e->live_status), 'gray'];
                $visit = $e->live_status === 'on_site' ? $e->currentVisit : null;
                $overdue = $visit && $visit->expected_return_date->isBefore(today());
            @endphp
            <a href="{{ route('employees.show', $e) }}" class="card p-4 flex flex-col no-underline text-ink hover:no-underline hover:border-accent/40 transition">
                <div class="flex items-start justify-between gap-2">
                    <x-avatar :name="$e->full_name" :src="$e->photoUrl()" size="size-[52px] text-[16px]" />
                    <span class="pill pill-{{ $pillTone }}">{{ $pillLabel }}</span>
                </div>
                <div class="text-[15px] font-semibold mt-3 truncate">{{ $e->full_name }}</div>
                <div class="text-[13px] text-ink-2 truncate">{{ $e->designation ?? '—' }}</div>
                <div @class(['flex items-start gap-1.5 mt-3.5 pt-3 border-t border-line text-[12.5px]', 'text-red-fg' => $overdue, 'text-muted' => ! $overdue])>
                    <x-icon name="map-pin" class="size-3.5 mt-0.5 shrink-0" />
                    <span>
                        @if ($visit)
                            {{ $visit->site_name }} · {{ $overdue ? 'overdue' : 'back '.$visit->expected_return_date->format('j M') }}
                        @elseif ($e->live_status === 'on_leave')
                            On leave today
                        @else
                            Head office
                        @endif
                    </span>
                </div>
            </a>
        @empty
            <div class="sm:col-span-2 lg:col-span-3 xl:col-span-4 card"><x-empty icon="users" title="No employees found"><a href="{{ route('employees.create') }}" class="btn btn-primary">Add employee</a></x-empty></div>
        @endforelse
    </div>
</x-layouts.app>
