<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProjectFollowUp extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'due_date' => 'date',
        'snoozed_until' => 'date',
        'completed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->due_date->isBefore(today());
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
