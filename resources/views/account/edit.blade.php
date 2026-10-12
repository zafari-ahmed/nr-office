<x-layouts.app title="My account">
    @php $employee = $user->employee; @endphp
    <x-page-header title="My account" :subtitle="\App\Models\User::ROLES[$user->role] ?? $user->role">
        @if ($employee)
            <x-slot:actions><a href="{{ route('employees.show', $employee) }}" class="btn btn-secondary"><x-icon name="user" /> View my profile</a></x-slot:actions>
        @endif
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_minmax(0,380px)] gap-6 max-w-[1120px] items-start">
        <form method="POST" action="{{ route('account.update') }}" enctype="multipart/form-data" class="card overflow-hidden" id="profile">
            @csrf @method('PUT')
            <div class="px-5 py-4 border-b border-line">
                <h2 class="card-title">Edit profile</h2>
                <p class="text-[13px] text-ink-2 mt-0.5">Your name and email are used to sign in and on notifications.</p>
            </div>
            <div class="p-5 space-y-5">
                @if ($employee)
                    <div class="flex items-center gap-4" x-data="{ preview: @js($employee->photoUrl()) }">
                        <button type="button" class="relative group shrink-0 rounded-full" @click="$refs.file.click()" aria-label="Choose photo">
                            <template x-if="preview"><img :src="preview" alt="" class="size-16 rounded-full object-cover border border-line"></template>
                            <template x-if="!preview"><span class="grid place-items-center size-16 rounded-full border-2 border-dashed border-line text-muted group-hover:border-accent group-hover:text-accent"><x-icon name="camera" class="size-5" /></span></template>
                        </button>
                        <div class="space-y-1">
                            <button type="button" class="btn btn-secondary btn-sm" @click="$refs.file.click()"><x-icon name="upload" /> <span x-text="preview ? 'Change photo' : 'Upload photo'"></span></button>
                            <p class="text-[12px] text-muted">JPG, PNG or WebP, up to 4 MB.</p>
                        </div>
                        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="hidden" x-ref="file" @change="const f = $event.target.files[0]; if (f) preview = URL.createObjectURL(f)">
                    </div>
                @endif
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-field label="Full name" name="name" :value="$user->name" required />
                    <x-field label="Email" name="email" type="email" :value="$user->email" required />
                    <x-field label="Phone" name="phone" :value="$user->phone ?? $employee?->phone" placeholder="0300-1234567" />
                </div>
                @if ($employee)
                    <x-field label="Address" name="address" type="textarea" rows="2" :value="$employee->address" />
                    <div class="grid sm:grid-cols-2 gap-4">
                        <x-field label="Emergency contact" name="emergency_contact_name" :value="$employee->emergency_contact_name" />
                        <x-field label="Emergency phone" name="emergency_contact_phone" :value="$employee->emergency_contact_phone" />
                    </div>
                    <p class="text-[12px] text-muted">Job, salary and CNIC details are kept by the office. Ask an Admin if something is wrong.</p>
                @endif
            </div>
            <div class="flex justify-end px-5 py-3 bg-surface-2 border-t border-line"><button class="btn btn-primary">Save profile</button></div>
        </form>

        <form method="POST" action="{{ route('account.password') }}" class="card overflow-hidden" id="password">
            @csrf @method('PUT')
            <div class="px-5 py-4 border-b border-line">
                <h2 class="card-title">Change password</h2>
                <p class="text-[13px] text-ink-2 mt-0.5">At least 8 characters.</p>
            </div>
            <div class="p-5 space-y-4">
                <x-field label="Current password" name="current_password" type="password" required autocomplete="current-password" />
                <x-field label="New password" name="password" type="password" required autocomplete="new-password" />
                <x-field label="Confirm new password" name="password_confirmation" type="password" required autocomplete="new-password" />
            </div>
            <div class="flex justify-end px-5 py-3 bg-surface-2 border-t border-line"><button class="btn btn-primary">Change password</button></div>
        </form>
    </div>
</x-layouts.app>
