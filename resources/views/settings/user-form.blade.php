<x-layouts.app :title="$user->exists ? 'Edit user' : 'Add user'">
    <x-page-header title="Settings" :subtitle="$user->exists ? 'Edit '.$user->name : 'Add user'" />
    <div class="grid grid-cols-1 lg:grid-cols-[230px_minmax(0,1fr)] gap-6">
        @include('settings._nav')
        <form method="POST" action="{{ $user->exists ? route('settings.users.update', $user) : route('settings.users.store') }}" class="card card-pad space-y-4 max-w-xl">
            @csrf
            @if ($user->exists) @method('PUT') @endif
            <x-field label="Name" name="name" :value="$user->name" required />
            <div class="grid sm:grid-cols-2 gap-4">
                <x-field label="Email" name="email" type="email" :value="$user->email" required />
                <x-field label="Phone" name="phone" :value="$user->phone" />
                <x-field label="Role" name="role" :options="$roles" :value="$user->role" required />
                <x-field label="Employee profile" name="employee_id" :options="$employees->pluck('full_name', 'id')" blank="— Create a new employee profile —" :value="$user->employee_id" hint="Every login is an employee. Leave blank to add them to Employees." />
            </div>
            <x-field label="{{ $user->exists ? 'New password' : 'Password' }}" name="password" type="password" :required="! $user->exists" :hint="$user->exists ? 'Leave blank to keep the current password' : 'At least 8 characters'" autocomplete="new-password" />
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" class="checkbox" @checked(old('is_active', $user->is_active ?? true))> Active (can sign in)</label>
            <div class="flex justify-end gap-2 pt-2">
                <a href="{{ route('settings.users.index') }}" class="btn btn-secondary">Cancel</a>
                <button class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</x-layouts.app>
