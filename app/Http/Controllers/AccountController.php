<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * The signed-in person's own account: profile, contact details, photo and password.
 */
class AccountController extends Controller
{
    /** "My profile": the employee profile when the login is linked to one. */
    public function profile(Request $request)
    {
        $employee = $request->user()->employee;

        return $employee ? redirect()->route('employees.show', $employee) : redirect()->route('account.edit');
    }

    public function edit(Request $request)
    {
        return view('account.edit', ['user' => $request->user()->load('employee')]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], ['photo.max' => 'The photo must be 4 MB or smaller.']);

        $user->update(collect($data)->only('name', 'email', 'phone')->all());

        // Contact details and photo live on the linked employee record.
        if ($employee = $user->employee) {
            $employee->update(collect($data)->only('phone', 'address', 'emergency_contact_name', 'emergency_contact_phone')->all());
            if ($request->hasFile('photo')) {
                if ($employee->photo_path) {
                    Storage::disk('public')->delete($employee->photo_path);
                }
                $employee->update(['photo_path' => $request->file('photo')->store('employees/photos', 'public')]);
            }
        }

        return back()->with('success', 'Profile saved.');
    }

    public function password(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:current_password'],
        ], [
            'current_password.current_password' => 'Your current password is not correct.',
            'password.different' => 'Choose a password different from your current one.',
        ]);

        $request->user()->update(['password' => $request->password]);

        return back()->with('success', 'Password changed.');
    }
}
