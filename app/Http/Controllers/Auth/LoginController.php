<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required'],
        ], ['login.required' => 'Enter your username or email.']);

        // One box for both: anything with an "@" is an email, otherwise a username.
        $login = trim($data['login']);
        $field = str_contains($login, '@') ? 'email' : 'username';
        $value = $field === 'username' ? strtolower($login) : $login;

        if (! Auth::attempt([$field => $value, 'password' => $data['password'], 'is_active' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['login' => 'These credentials do not match an active account.']);
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
