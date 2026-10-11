<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeave extends Model
{
    public const TYPES = ['annual' => 'Annual', 'sick' => 'Sick', 'casual' => 'Casual', 'unpaid' => 'Unpaid'];

    protected $guarded = ['id'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function days(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }
}
