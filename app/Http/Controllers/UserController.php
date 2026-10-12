<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use App\Support\Numbering;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        return view('settings.users', [
            'users' => User::with('employee')->orderBy('name')->get(),
            'sections' => SettingsController::SECTIONS,
            'section' => 'users',
        ]);
    }

    public function create()
    {
        return view('settings.user-form', ['user' => new User(['role' => 'staff', 'is_active' => true]), ...$this->formData()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = User::create($data);
        $made = $this->ensureEmployee($user, anyRole: true);

        return redirect()->route('settings.users.index')->with('success', $made ? 'User created, with an employee profile.' : 'User created.');
    }

    public function edit(Request $request, User $user)
    {
        $this->ensureCanTouch($request, $user);
        return view('settings.user-form', ['user' => $user, ...$this->formData($user)]);
    }

    public function update(Request $request, User $user)
    {
        $this->ensureCanTouch($request, $user);
        $data = $this->validated($request, $user);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        abort_if($user->is($request->user()) && ($data['role'] !== 'admin' || ! $data['is_active']) && $user->isAdmin(), 422, 'You cannot remove your own admin access.');
        $user->update($data);
        $this->ensureEmployee($user);

        return redirect()->route('settings.users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 422, 'You cannot delete yourself.');
        $this->ensureCanTouch($request, $user);
        $user->update(['is_active' => false]);

        return back()->with('success', 'User deactivated.');
    }

    /** Only an Admin can change Admin accounts. */
    private function ensureCanTouch(Request $request, User $user): void
    {
        abort_if($user->isAdmin() && ! $request->user()->isAdmin(), 403, 'Only an Admin can change Admin accounts.');
    }

    /**
     * Logins are employees: a new user (any role) without an employee record gets one, so they have a profile, salary
     * and team projects. Existing logins are only filled in for Staff, so an owner's account isn't turned into an employee unasked.
     */
    private function ensureEmployee(User $user, bool $anyRole = false): bool
    {
        if ($user->employee_id || (! $anyRole && $user->role !== 'staff')) {
            return false;
        }
        $employee = Employee::create([
            'employee_code' => Numbering::employee(), 'full_name' => $user->name, 'email' => $user->email, 'phone' => $user->phone,
            'employment_type' => 'permanent', 'status' => 'active', 'joining_date' => today(), 'basic_salary' => 0,
        ]);
        $user->update(['employee_id' => $employee->id]);

        return true;
    }

    private function formData(?User $user = null): array
    {
        return [
            'roles' => auth()->user()->isAdmin() ? User::ROLES : array_diff_key(User::ROLES, ['admin' => 1]),
            // Each employee has at most one login.
            'employees' => Employee::active()->where(fn ($q) => $q->whereDoesntHave('user')->when($user?->employee_id, fn ($w, $id) => $w->orWhere('id', $id)))
                ->orderBy('full_name')->get(['id', 'full_name']),
            'sections' => SettingsController::SECTIONS,
            'section' => 'users',
        ];
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            // Only an Admin can make someone an Admin.
            'role' => ['required', Rule::in(array_keys($request->user()->isAdmin() ? User::ROLES : array_diff_key(User::ROLES, ['admin' => 1])))],
            'employee_id' => ['nullable', 'exists:employees,id', Rule::unique('users')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
