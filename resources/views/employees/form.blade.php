<x-layouts.app :title="$employee->exists ? 'Edit employee' : 'Add employee'">
    <x-page-header :title="$employee->exists ? 'Edit '.$employee->full_name : 'Add employee'" :subtitle="'Employee code '.$employee->employee_code" />

    <form method="POST" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}" enctype="multipart/form-data" class="grid lg:grid-cols-3 gap-6">
        @csrf
        @if ($employee->exists) @method('PUT') @endif
        <div class="lg:col-span-2 space-y-6">
            <div class="card card-pad space-y-4">
                <h2 class="card-title">Personal</h2>
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-field label="Full name" name="full_name" :value="$employee->full_name" required />
                    <x-field label="Father's name" name="father_name" :value="$employee->father_name" />
                    <x-field label="CNIC" name="cnic" :value="$employee->cnic" placeholder="35202-1234567-1" pattern="\d{5}-\d{7}-\d" />
                    <x-field label="Date of birth" name="date_of_birth" type="date" :value="$employee->date_of_birth" />
                    <x-field label="Gender" name="gender" :options="['male' => 'Male', 'female' => 'Female', 'other' => 'Other']" blank="—" :value="$employee->gender" />
                    <x-field label="Phone" name="phone" :value="$employee->phone" placeholder="0300-1234567" />
                    <x-field label="Email" name="email" type="email" :value="$employee->email" />
                </div>
                <x-field label="Address" name="address" type="textarea" rows="2" :value="$employee->address" />
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-field label="Emergency contact" name="emergency_contact_name" :value="$employee->emergency_contact_name" />
                    <x-field label="Emergency phone" name="emergency_contact_phone" :value="$employee->emergency_contact_phone" />
                </div>
            </div>
            <div class="card card-pad space-y-4">
                <h2 class="card-title">Job</h2>
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-field label="Designation" name="designation" :value="$employee->designation" placeholder="Site Supervisor" />
                    <x-field label="Department" name="department" :value="$employee->department" placeholder="Construction" />
                    <x-field label="Employment type" name="employment_type" :options="\App\Models\Employee::TYPES" :value="$employee->employment_type" />
                    <x-field label="Status" name="status" :options="\App\Models\Employee::STATUSES" :value="$employee->status" />
                    <x-field label="Joining date" name="joining_date" type="date" :value="$employee->joining_date" />
                    <x-field label="Leaving date" name="leaving_date" type="date" :value="$employee->leaving_date" />
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_site_staff" value="0"><input type="checkbox" name="is_site_staff" value="1" class="checkbox" @checked(old('is_site_staff', $employee->is_site_staff))> Part of the site visit rotation</label>
                <x-field label="Rotation order" name="rotation_order" type="number" min="1" :value="$employee->rotation_order" hint="Lower numbers are suggested first when waits are equal" class="sm:w-1/2" />
            </div>
        </div>
        <div class="space-y-6">
            <div class="card card-pad" x-data="{ preview: @js($employee->photoUrl()), removed: false,
                    pick(e) { const f = e.target.files[0]; if (f) { this.preview = URL.createObjectURL(f); this.removed = false } },
                    clear() { this.$refs.file.value = ''; this.preview = null; this.removed = true } }">
                <h2 class="card-title mb-4">Photo</h2>
                <div class="flex items-center gap-4">
                    <button type="button" class="relative group shrink-0 rounded-full" @click="$refs.file.click()" aria-label="Choose photo">
                        <template x-if="preview"><img :src="preview" alt="" class="size-20 rounded-full object-cover border border-line"></template>
                        <template x-if="!preview">
                            <span class="grid place-items-center size-20 rounded-full border-2 border-dashed border-line text-muted group-hover:border-accent group-hover:text-accent transition"><x-icon name="camera" class="size-6" /></span>
                        </template>
                        <span x-show="preview" class="absolute inset-0 grid place-items-center rounded-full bg-gray-900/45 text-white opacity-0 group-hover:opacity-100 transition"><x-icon name="camera" class="size-5" /></span>
                    </button>
                    <div class="space-y-1.5">
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="btn btn-secondary btn-sm" @click="$refs.file.click()"><x-icon name="upload" /> <span x-text="preview ? 'Change photo' : 'Upload photo'"></span></button>
                            <button type="button" x-show="preview" class="btn btn-ghost btn-sm text-red-fg" @click="clear()">Remove</button>
                        </div>
                        <p class="text-[12px] text-muted">JPG, PNG or WebP, up to 4 MB.</p>
                    </div>
                </div>
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="hidden" x-ref="file" @change="pick">
                <input type="hidden" name="remove_photo" :value="removed ? 1 : 0">
                @error('photo')<p class="error mt-2">{{ $message }}</p>@enderror
            </div>
            <div class="card card-pad space-y-4">
                <h2 class="card-title">Salary & bank</h2>
                @unless ($employee->exists)
                    <x-field label="Basic salary (PKR)" name="basic_salary" type="number" step="0.01" min="0" :value="$employee->basic_salary" />
                @else
                    <p class="text-xs text-muted">Change salary from the profile's Salary history tab so revisions are kept.</p>
                @endunless
                <x-field label="Bank" name="bank_name" :value="$employee->bank_name" placeholder="Meezan Bank" />
                <x-field label="Account / IBAN" name="bank_account_no" :value="$employee->bank_account_no" />
            </div>
            @can('users.manage')
                @php $login = $employee->user; $locked = $login?->isAdmin() && ! auth()->user()->isAdmin(); @endphp
                <div class="card card-pad space-y-4" x-data="{ on: {{ old('login_enabled', $login?->is_active ? 1 : 0) ? 'true' : 'false' }} }">
                    <div>
                        <h2 class="card-title">App login</h2>
                        <p class="text-[12.5px] text-ink-2 mt-0.5">Lets this employee sign in. Staff see only their own profile, team projects and their own expenses.</p>
                    </div>
                    @if ($locked)
                        <p class="text-[13px] text-muted">Signs in as {{ $login->email }} (Owner / Admin). Only an Admin can change this login.</p>
                    @else
                        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="login_enabled" value="0"><input type="checkbox" name="login_enabled" value="1" class="checkbox" x-model="on"> Can sign in to the app</label>
                        <template x-if="on">
                            <div class="space-y-4">
                                <x-field label="Login email" name="login_email" type="email" :value="$login?->email ?? $employee->email" required hint="Usually their work email" />
                                <x-field label="Role" name="login_role" :options="auth()->user()->isAdmin() ? \App\Models\User::ROLES : array_diff_key(\App\Models\User::ROLES, ['admin' => 1])" :value="$login?->role ?? 'staff'" required />
                                <x-field :label="$login ? 'New password' : 'Password'" name="login_password" type="password" :required="! $login" :hint="$login ? 'Leave blank to keep their current password' : 'At least 8 characters. Share it with them; they can change it from their profile.'" autocomplete="new-password" />
                            </div>
                        </template>
                        @if ($login && ! $login->is_active)<p class="text-[12.5px] text-muted" x-show="!on">Login {{ $login->email }} is switched off.</p>@endif
                    @endif
                </div>
            @endcan
            <x-field label="Notes" name="notes" type="textarea" rows="3" :value="$employee->notes" />
            <div class="flex justify-end gap-2">
                <a href="{{ $employee->exists ? route('employees.show', $employee) : route('employees.index') }}" class="btn btn-secondary">Cancel</a>
                <button class="btn btn-primary"><x-icon name="check" /> Save</button>
            </div>
        </div>
    </form>
</x-layouts.app>
