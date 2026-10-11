<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteVisit extends Model
{
    public const STATUSES = [
        'scheduled' => 'Scheduled',
        'on_site' => 'On site',
        'returning_soon' => 'Returning soon',
        'overdue' => 'Overdue',
        'returned' => 'Returned',
        'cancelled' => 'Cancelled',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'departure_date' => 'date',
        'expected_return_date' => 'date',
        'actual_return_date' => 'date',
        'reminder_sent_at' => 'datetime',
        'last_overdue_reminder_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Stored status plus the derived "returning soon" / "overdue" states. */
    public function displayStatus(): string
    {
        if ($this->status !== 'on_site') {
            return $this->status;
        }
        if ($this->expected_return_date->isBefore(today())) {
            return 'overdue';
        }
        if ($this->expected_return_date->lte(today()->addDays(Setting::int('rotation.remind_days_before')))) {
            return 'returning_soon';
        }

        return 'on_site';
    }

    public function scopeOnSite($query)
    {
        return $query->where('status', 'on_site');
    }

    public function endDate()
    {
        return $this->actual_return_date ?? $this->expected_return_date;
    }
}
