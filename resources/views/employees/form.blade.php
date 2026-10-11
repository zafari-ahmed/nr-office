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
            <div class="card card-pad space-y-4">
                <h2 class="card-title">Photo</h2>
                <x-avatar :name="$employee->full_name ?? ''" :src="$employee->photoUrl()" size="size-20 text-xl" />
                <input type="file" name="photo" accept="image/*" class="text-sm">
                @error('photo')<p class="error">{{ $message }}</p>@enderror
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
            <x-field label="Notes" name="notes" type="textarea" rows="3" :value="$employee->notes" />
            <div class="flex justify-end gap-2">
                <a href="{{ $employee->exists ? route('employees.show', $employee) : route('employees.index') }}" class="btn btn-secondary">Cancel</a>
                <button class="btn btn-primary"><x-icon name="check" /> Save</button>
            </div>
        </div>
    </form>
</x-layouts.app>
