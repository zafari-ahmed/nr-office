<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use App\Support\Activity;
use App\Support\Numbering;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        // Without the directory, Employees opens your own profile.
        if (! $request->user()->hasPermission('employees.view')) {
            return redirect()->route('account.profile');
        }
        $employees = Employee::with('currentVisit')
            ->when($request->status !== 'all', fn ($q) => $q->active())
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('full_name', 'like', "%$s%")->orWhere('designation', 'like', "%$s%")->orWhere('cnic', 'like', "%$s%")))
            ->orderBy('full_name')->get();

        $employees->each(fn ($e) => $e->live_status = $e->liveStatus());
        $liveCounts = $employees->countBy('live_status');
        $total = $employees->count();
        if ($request->live) {
            $employees = $employees->where('live_status', $request->live)->values();
        }

        return view('employees.index', compact('employees', 'liveCounts', 'total'));
    }

    public function create()
    {
        return view('employees.form', ['employee' => new Employee([
            'employee_code' => Numbering::employee(), 'employment_type' => 'permanent', 'status' => 'active', 'joining_date' => today(),
        ])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['employee_code'] = Numbering::employee();
        $employee = Employee::create(collect($data)->except('photo', 'login')->all());
        $this->savePhoto($request, $employee);
        $this->syncLogin($request, $employee, $data['login'] ?? null);
        if ($employee->basic_salary > 0) {
            $employee->salaries()->create([
                'effective_from' => $employee->joining_date ?? today(), 'basic_salary' => $employee->basic_salary,
                'net_salary' => $employee->basic_salary, 'reason' => 'Joining salary', 'created_by' => $request->user()->id,
            ]);
        }
        Activity::log('created', "Employee {$employee->full_name} added", $employee);

        return redirect()->route('employees.show', $employee)->with('success', 'Employee added.');
    }

    public function show(Request $request, Employee $employee)
    {
        $this->ensureCanSee($request, $employee);
        $employee->load('salaries', 'leaves', 'siteVisits.project', 'assets', 'attachments', 'user', 'salaryExpenses.head');

        return view('employees.show', [
            'employee' => $employee,
            'liveStatus' => $employee->liveStatus(),
            'tab' => $request->query('tab', 'profile'),
            'canSeeSalary' => $request->user()->hasPermission('employees.salary'),
        ]);
    }

    public function edit(Employee $employee)
    {
        $employee->load('user');

        return view('employees.form', compact('employee'));
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $this->validated($request, $employee);
        $employee->update(collect($data)->except('photo', 'basic_salary', 'login')->all());
        $this->syncLogin($request, $employee, $data['login'] ?? null);
        if ($request->boolean('remove_photo') && ! $request->hasFile('photo')) {
            $this->deletePhoto($employee);
        }
        $this->savePhoto($request, $employee);

        return redirect()->route('employees.show', $employee)->with('success', 'Employee updated.');
    }

    public function uploadDocuments(Request $request, Employee $employee)
    {
        $request->validate(['files' => ['required', 'array'], 'files.*' => ['file', 'max:20480']]);
        $employee->attachFiles($request->file('files'), 'document');

        return redirect()->route('employees.show', [$employee, 'tab' => 'documents'])->with('success', 'Documents uploaded.');
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'Employee archived.');
    }

    public function photo(Employee $employee)
    {
        abort_unless($employee->photo_path && Storage::disk('public')->exists($employee->photo_path), 404);

        return Storage::disk('public')->response($employee->photo_path, null, ['Cache-Control' => 'private, max-age=31536000']);
    }

    /** Upload or replace the photo straight from the profile page. */
    public function updatePhoto(Request $request, Employee $employee)
    {
        abort_unless($request->user()->hasPermission('employees.manage') || $this->isSelf($request, $employee), 403);
        $request->validate(['photo' => self::PHOTO_RULES], ['photo.max' => 'The photo must be 4 MB or smaller.']);
        $this->savePhoto($request, $employee);

        return back()->with('success', 'Photo updated.');
    }

    public function destroyPhoto(Employee $employee)
    {
        $this->deletePhoto($employee);

        return back()->with('success', 'Photo removed.');
    }

    private function isSelf(Request $request, Employee $employee): bool
    {
        return $request->user()->employee_id !== null && $request->user()->employee_id === $employee->id;
    }

    /** Your own profile, or anyone's with "See all employees". */
    private function ensureCanSee(Request $request, Employee $employee): void
    {
        abort_unless($request->user()->hasPermission('employees.view') || $this->isSelf($request, $employee), 403, 'You can only open your own profile.');
    }

    private const PHOTO_RULES = ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];

    private function savePhoto(Request $request, Employee $employee): void
    {
        if ($request->hasFile('photo')) {
            $this->deletePhoto($employee);
            $employee->update(['photo_path' => $request->file('photo')->store('employees/photos', 'public')]);
        }
    }

    private function deletePhoto(Employee $employee): void
    {
        if ($employee->photo_path) {
            Storage::disk('public')->delete($employee->photo_path);
            $employee->update(['photo_path' => null]);
        }
    }

    /**
     * Login details from the employee form ("Can sign in"). Null when the person editing can't manage users,
     * otherwise ['enabled' => bool, ...]. An employee and their login are one person: staff sign in as their employee record.
     */
    private function validatedLogin(Request $request, ?Employee $employee): ?array
    {
        if (! $request->user()->hasPermission('users.manage') || ! $request->has('login_enabled')) {
            return null;
        }
        $user = $employee?->user;
        if ($user?->isAdmin() && ! $request->user()->isAdmin()) {
            return null; // Only an Admin can change Admin accounts.
        }
        if (! $request->boolean('login_enabled')) {
            return ['enabled' => false];
        }
        $roles = $request->user()->isAdmin() ? User::ROLES : array_diff_key(User::ROLES, ['admin' => 1]);
        $login = $request->validate([
            'login_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'login_role' => ['required', Rule::in(array_keys($roles))],
            'login_password' => [$user ? 'nullable' : 'required', Password::min(8)],
        ], ['login_email.required' => 'Enter the email they will sign in with.', 'login_password.required' => 'Set a password for their first sign-in.']);

        return ['enabled' => true, 'email' => $login['login_email'], 'role' => $login['login_role'], 'password' => $login['login_password'] ?? null];
    }

    private function syncLogin(Request $request, Employee $employee, ?array $login): void
    {
        $user = $employee->user()->first();
        $current = $employee->status === 'active';

        if ($login === null) {
            // No login changes from this form; still keep the name, phone and access in step with the employee.
            $user?->update(['name' => $employee->full_name, 'phone' => $employee->phone] + ($current || $user->is($request->user()) ? [] : ['is_active' => false]));

            return;
        }
        if (! $login['enabled']) {
            if ($user && ! $user->is($request->user())) {
                $user->update(['is_active' => false]);
            }

            return;
        }
        $fields = ['name' => $employee->full_name, 'phone' => $employee->phone, 'email' => $login['email'], 'role' => $login['role'], 'is_active' => $current]
            + ($login['password'] ? ['password' => $login['password']] : []);
        if ($user) {
            if ($user->is($request->user())) {
                unset($fields['role'], $fields['is_active']); // Never lock yourself out from here.
            }
            $user->update($fields);
        } else {
            User::create([...$fields, 'employee_id' => $employee->id]);
        }
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'cnic' => ['nullable', 'regex:/^\d{5}-\d{7}-\d$/', Rule::unique('employees')->ignore($employee)],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'designation' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['required', Rule::in(array_keys(Employee::TYPES))],
            'joining_date' => ['nullable', 'date'],
            'leaving_date' => ['nullable', 'date'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_no' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(array_keys(Employee::STATUSES))],
            'rotation_order' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], ['cnic.regex' => 'CNIC must look like 35202-1234567-1.', 'photo.max' => 'The photo must be 4 MB or smaller.']);

        $data['is_site_staff'] = $request->boolean('is_site_staff');
        $data['basic_salary'] ??= 0;
        $data['login'] = $this->validatedLogin($request, $employee);

        return $data;
    }
}
