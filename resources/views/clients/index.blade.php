<x-layouts.app title="Clients">
    <x-page-header title="Clients" subtitle="People and companies you work for.">
        <x-slot:actions><a href="{{ route('clients.create') }}" class="btn btn-primary"><x-icon name="plus" /> Add client</a></x-slot:actions>
    </x-page-header>
    <form class="mb-4"><input name="q" value="{{ request('q') }}" placeholder="Search clients" class="input !h-9 !w-72"></form>
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Client</th><th>Contact</th><th>Phone</th><th>City</th><th class="text-right">Projects</th><th></th></tr></thead>
                <tbody>
                @forelse ($clients as $c)
                    <tr>
                        <td><div class="font-medium">{{ $c->displayName() }}</div>@if ($c->company_name)<div class="text-xs text-muted">{{ $c->name }}</div>@endif</td>
                        <td>{{ $c->contact_person ?? '—' }}<div class="text-xs text-muted">{{ $c->email }}</div></td>
                        <td>{{ $c->phone ?? '—' }}</td><td>{{ $c->city ?? '—' }}</td>
                        <td class="text-right"><a href="{{ route('projects.index', ['q' => $c->name]) }}" class="link">{{ $c->projects_count }}</a></td>
                        <td class="text-right"><a href="{{ route('clients.edit', $c) }}" class="btn btn-ghost btn-sm"><x-icon name="pencil" /> Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty icon="building-2" title="No clients yet" /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $clients->links() }}
    </div>
</x-layouts.app>
