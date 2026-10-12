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
        'name', 'email', 'phone', 'password', 'role', 'employee_id', 'is_active', 'last_login_at',
    ];

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
