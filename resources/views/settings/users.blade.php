<x-layouts.app title="Users & roles">
    <x-page-header title="Settings" subtitle="Users & roles">
        <x-slot:actions><a href="{{ route('settings.users.create') }}" class="btn btn-primary"><x-icon name="user-plus" /> Add user</a></x-slot:actions>
    </x-page-header>
    <div class="grid grid-cols-1 lg:grid-cols-[230px_minmax(0,1fr)] gap-6">
        @include('settings._nav')
        <div class="space-y-4 min-w-0">
            @if (auth()->user()->isAdmin())
                <p class="text-[13px] text-muted">{{ $adminCount }} Super {{ str('admin')->plural($adminCount) }} listed first. Other roles don't see Super admin accounts.</p>
            @endif
            <div class="card overflow-x-auto">
                <table class="table">
                    <thead><tr><th>User</th><th>Role</th><th>Employee</th><th>Last sign-in</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($users as $u)
                        <tr>
                            <td><div class="flex items-center gap-2.5"><x-avatar :name="$u->name" size="size-8 text-[11px]" /><div><div class="font-medium">{{ $u->name }}</div><div class="text-xs text-muted">{{ $u->username ? '@'.$u->username.' · ' : '' }}{{ $u->email }}</div></div></div></td>
                            <td>@if ($u->isAdmin())<span class="inline-flex items-center gap-1 font-medium"><x-icon name="shield-check" class="!size-3.5 text-accent" /> Super admin</span>@else{{ \App\Models\User::ROLES[$u->role] ?? $u->role }}@endif</td>
                            <td>{{ $u->employee?->full_name ?? '—' }}</td>
                            <td class="text-muted">{{ $u->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                            <td><x-pill :status="$u->is_active ? 'active' : 'cancelled'" :label="$u->is_active ? 'Active' : 'Inactive'" /></td>
                            <td class="text-right"><a href="{{ route('settings.users.edit', $u) }}" class="btn btn-ghost btn-sm"><x-icon name="pencil" /> Edit</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card card-pad text-[13px] grid sm:grid-cols-2 gap-3">
                <div><span class="font-medium">Super admin</span> <span class="text-muted">· everything; only Super admins see other Super admins and branding, and can log in as an employee</span></div>
                <div><span class="font-medium">Manager</span> <span class="text-muted">· projects, quotes, rotation, settings, approvals</span></div>
                <div><span class="font-medium">Accounts</span> <span class="text-muted">· invoices, payments, expenses and salaries</span></div>
                <div><span class="font-medium">Staff</span> <span class="text-muted">· projects, follow-ups, logs; expenses need approval</span></div>
            </div>
        </div>
    </div>
</x-layouts.app>
