<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Support\Access;
use Illuminate\Http\Request;

/**
 * Settings › Access rights: Admin decides what Manager, Accounts and Staff can see and do.
 */
class AccessController extends Controller
{
    public const ROLES = ['manager', 'accounts', 'staff'];

    public function edit()
    {
        return view('settings.access', [
            'section' => 'access',
            'roles' => array_intersect_key(User::ROLES, array_flip(self::ROLES)),
            'granted' => collect(self::ROLES)->mapWithKeys(fn ($r) => [$r => Access::forRole($r)])->all(),
            'counts' => User::where('is_active', true)->get(['role'])->countBy('role'),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'access' => ['nullable', 'array'],
            'access.*' => ['array'],
            'access.*.*' => ['in:'.implode(',', Access::keys())],
        ]);

        if ($request->boolean('reset')) {
            foreach (self::ROLES as $role) {
                Setting::put("access.{$role}", implode(',', Access::DEFAULTS[$role]));
            }

            return back()->with('success', 'Access rights reset to the defaults.');
        }

        foreach (self::ROLES as $role) {
            Setting::put("access.{$role}", implode(',', $request->input("access.{$role}", [])));
        }

        return back()->with('success', 'Access rights saved.');
    }
}
