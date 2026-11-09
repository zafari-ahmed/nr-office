<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLES = [
        'admin' => 'Owner / Admin',
        'manager' => 'Manager',
        'accounts' => 'Accounts',
        'staff' => 'Staff',
    ];

    protected $fillable = [
        'name', 'username', 'email', 'phone', 'password', 'role', 'employee_id', 'is_active', 'last_login_at',
    ];

    /** Usernames: letters, numbers, dot, dash and underscore (never "@", so they can't be confused with an email). */
    /** Lower-case and trim the posted username before validating, so "Ali" and "ali" count as the same name. */
    public static function normalizeUsername(\Illuminate\Http\Request $request, string $key = 'username'): void
    {
        if ($request->has($key)) {
            $request->merge([$key => filled($request->input($key)) ? strtolower(trim((string) $request->input($key))) : null]);
        }
    }

    public static function usernameRules(?self $ignore = null): array
    {
        return ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', \Illuminate\Validation\Rule::unique('users', 'username')->ignore($ignore)];
    }

    protected function username(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::set(fn ($value) => filled($value) ? strtolower(trim($value)) : null);
    }

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function hasPermission(string $permission): bool
    {
        $module = \App\Support\Modules::forRoute($permission);
        if ($module && ! \App\Support\Modules::enabled($module)) {
            return false;
        }

        return $this->isAdmin() || in_array($permission, \App\Support\Access::forRole($this->role), true);
    }

    public function canSeeProject(Project $project): bool
    {
        return $this->hasPermission('projects.view_all')
            || $project->manager_id === $this->id
            || ($this->employee_id && $project->members()->whereKey($this->employee_id)->exists());
    }

    /** Expenses this person added, or that are for them (e.g. their salary). */
    public function ownsExpense(Expense $expense): bool
    {
        return $expense->created_by === $this->id || ($this->employee_id && $expense->employee_id === $this->employee_id);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))->filter()->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    }
}
