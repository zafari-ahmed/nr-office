<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Employee extends Model
{
    use HasAttachments, SoftDeletes;

    public const STATUSES = ['active' => 'Active', 'resigned' => 'Resigned', 'terminated' => 'Terminated'];

    public const TYPES = ['permanent' => 'Permanent', 'contract' => 'Contract', 'intern' => 'Intern'];

    protected $guarded = ['id'];

    protected $casts = [
        'date_of_birth' => 'date',
        'joining_date' => 'date',
        'leaving_date' => 'date',
        'is_site_staff' => 'boolean',
    ];

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class)->latest('effective_from');
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(EmployeeLeave::class)->latest('start_date');
    }

    public function siteVisits(): HasMany
    {
        return $this->hasMany(SiteVisit::class)->latest('departure_date');
    }

    public function currentVisit(): HasOne
    {
        return $this->hasOne(SiteVisit::class)->where('status', 'on_site')->latestOfMany('departure_date');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'assigned_employee_id');
    }

    public function salaryExpenses(): HasMany
    {
        return $this->hasMany(Expense::class)->latest('expense_date');
    }

    /** Live status for cards: on_site, on_leave or in_office. */
    public function liveStatus(): string
    {
        if ($this->status !== 'active') {
            return $this->status;
        }
        if ($this->relationLoaded('currentVisit') ? $this->currentVisit : $this->currentVisit()->exists()) {
            return 'on_site';
        }
        $onLeave = $this->leaves()->where('status', 'approved')
            ->whereDate('start_date', '<=', today())->whereDate('end_date', '>=', today())->exists();

        return $onLeave ? 'on_leave' : 'in_office';
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->full_name))->filter()->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
