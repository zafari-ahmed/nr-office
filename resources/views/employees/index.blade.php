<x-layouts.app title="Employees">
    <x-page-header title="Employees" :subtitle="$employees->count().' people'">
        <x-slot:actions>
            <a href="{{ route('employees.create') }}" class="btn btn-primary"><x-icon name="user-plus" /> Add employee</a>
        </x-slot:actions>
    </x-page-header>

    <form class="flex flex-wrap items-center gap-2 mb-5">
        <div class="relative">
            <x-icon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
            <input name="q" value="{{ request('q') }}" placeholder="Name, designation or CNIC" class="input !h-9 pl-9 w-64">
        </div>
        @foreach (['' => 'Everyone', 'in_office' => 'In office', 'on_site' => 'On site', 'on_leave' => 'On leave'] as $k => $l)
            <a href="{{ route('employees.index', array_filter(['live' => $k, 'q' => request('q')])) }}" @class(['chip', 'chip-active' => request('live', '') === $k])>{{ $l }}</a>
        @endforeach
        <a href="{{ route('employees.index', ['status' => 'all']) }}" @class(['chip', 'chip-active' => request('status') === 'all'])>Include former</a>
    </form>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        @forelse ($employees as $e)
            <a href="{{ route('employees.show', $e) }}" class="card card-pad hover:border-accent/40 transition">
                <div class="flex items-start gap-3">
                    <x-avatar :name="$e->full_name" :src="$e->photoUrl()" size="size-12 text-sm" />
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold truncate">{{ $e->full_name }}</div>
                        <div class="text-[13px] text-muted truncate">{{ $e->designation ?? '—' }}</div>
                        <div class="text-xs text-muted">{{ $e->employee_code }}</div>
                    </div>
                </div>
                <div class="flex items-center justify-between mt-4">
                    <x-pill :status="$e->live_status" :label="['in_office' => 'In office', 'on_site' => 'On site', 'on_leave' => 'On leave'][$e->live_status] ?? status_label($e->live_status)" />
                    @if ($e->live_status === 'on_site' && $e->currentVisit)
                        <span class="text-xs text-muted truncate ml-2">{{ $e->currentVisit->site_name }} · back {{ $e->currentVisit->expected_return_date->format('d M') }}</span>
                    @elseif ($e->phone)
                        <span class="text-xs text-muted">{{ $e->phone }}</span>
                    @endif
                </div>
            </a>
        @empty
            <div class="sm:col-span-2 lg:col-span-3 xl:col-span-4 card"><x-empty icon="users" title="No employees found"><a href="{{ route('employees.create') }}" class="btn btn-primary">Add employee</a></x-empty></div>
        @endforelse
    </div>
</x-layouts.app>
