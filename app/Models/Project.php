<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasAttachments, HasFactory, SoftDeletes;

    public const STATUSES = [
        'lead' => 'Lead',
        'quoted' => 'Quoted',
        'active' => 'Active',
        'on_hold' => 'On hold',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'project_value' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /** The project team (employees). */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'project_members')->withPivot('role')->withTimestamps();
    }

    /** Projects this user may open: all of them, or the ones they manage or are on the team of. */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->hasPermission('projects.view_all')) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('manager_id', $user->id)
            ->when($user->employee_id, fn ($q) => $q->orWhereHas('members', fn ($m) => $m->whereKey($user->employee_id))));
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(ProjectFollowUp::class);
    }

    public function nextFollowUp(): HasOne
    {
        return $this->hasOne(ProjectFollowUp::class)->where('status', 'pending')->orderBy('due_date');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ProjectNote::class)->orderByDesc('is_pinned')->latest();
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function documentLogs(): HasMany
    {
        return $this->hasMany(DocumentLog::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function siteVisits(): HasMany
    {
        return $this->hasMany(SiteVisit::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ActivityLog::class)->latest();
    }

    /** Latest revision of each quotation number counts towards "Quoted". */
    public function kpis(): array
    {
        $quoted = $this->quotations()->whereNotIn('status', ['revised', 'rejected'])->sum('total');
        $invoiced = $this->invoices()->where('status', '!=', 'cancelled')->sum('total');
        $received = $this->invoices()->where('status', '!=', 'cancelled')->sum('amount_paid');

        return [
            'quoted' => (float) $quoted,
            'invoiced' => (float) $invoiced,
            'received' => (float) $received,
            'outstanding' => (float) $invoiced - (float) $received,
            'expenses' => (float) $this->expenses()->where('status', 'approved')->sum('amount'),
        ];
    }

    public function outstanding(): float
    {
        return (float) $this->invoices()->whereNotIn('status', ['cancelled', 'draft'])->sum('balance_due');
    }
}
