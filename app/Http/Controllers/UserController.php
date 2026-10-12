<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
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
        User::create($this->validated($request));

        return redirect()->route('settings.users.index')->with('success', 'User created.');
    }

    public function edit(Request $request, User $user)
    {
        $this->ensureCanTouch($request, $user);
        return view('settings.user-form', ['user' => $user, ...$this->formData()]);
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

    private function formData(): array
    {
        return [
            'roles' => auth()->user()->isAdmin() ? User::ROLES : array_diff_key(User::ROLES, ['admin' => 1]),
            'employees' => Employee::active()->orderBy('full_name')->get(['id', 'full_name']),
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
            'employee_id' => ['nullable', 'exists:employees,id'],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
