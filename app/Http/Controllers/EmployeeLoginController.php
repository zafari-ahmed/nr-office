<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Impersonation;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/** Super admin tools on an employee's login: set a new password, and "Log in as" them in a separate tab. */
class EmployeeLoginController extends Controller
{
    public function password(Request $request, Employee $employee)
    {
        $user = $employee->user;
        abort_unless($user, 404, 'This employee has no login.');
        abort_if($user->is($request->user()), 422, 'Change your own password from My account.');
        $request->validate(['password' => ['required', 'confirmed', Password::min(8)]], [], ['password' => 'new password']);

        $user->forceFill(['password' => $request->input('password'), 'remember_token' => Str::random(60)])->save();
        Activity::log('password_changed', "Login password changed for {$user->name}", $employee);

        return redirect()->route('employees.show', $employee)->with('success', "Password changed. {$user->name} signs in with the new password from now on.");
    }

    /** Opens in a new tab (the form posts with target="_blank"); only that tab runs as the employee. */
    public function start(Request $request, Employee $employee)
    {
        $user = $employee->user;
        abort_unless($user && $user->is_active, 422, 'This employee has no active login.');
        abort_if($user->isAdmin(), 403, 'You cannot log in as another Super admin.');
        abort_if($user->is($request->user()), 422, 'This is your own account.');

        $impersonation = Impersonation::create([
            'key' => Str::random(40), 'impersonator_id' => $request->user()->id, 'user_id' => $user->id,
            'ip_address' => $request->ip(), 'started_at' => now(), 'last_seen_at' => now(),
        ]);
        Activity::log('impersonation_started', "{$request->user()->name} logged in as {$user->name}", $employee, null, ['impersonation_id' => $impersonation->id]);

        return redirect()->route('dashboard', ['_as' => $impersonation->key])
            // Session-only, encrypted cookie: the key works only in this browser, and only until it closes.
            ->withCookie(cookie(Impersonation::cookieName($impersonation->key), $impersonation->key, 0, '/', null, null, true, false, 'lax'));
    }

    public function end(Request $request)
    {
        $impersonation = $request->attributes->get('impersonation');
        if (! $impersonation) {
            return redirect()->route('dashboard');
        }
        $impersonation->end('ended');
        $request->session()->invalidate();

        return redirect()->route('impersonation.ended', ['reason' => 'left'])
            ->withCookie(cookie()->forget(Impersonation::cookieName($impersonation->key)))
            ->withCookie(cookie()->forget(Impersonation::sessionCookieName($impersonation->key)));
    }

    public function ended(Request $request)
    {
        return response()->view('impersonation.ended', ['reason' => (string) $request->query('reason', 'ended')]);
    }
}
