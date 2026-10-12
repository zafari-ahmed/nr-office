<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasAttachments, SoftDeletes;

    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];

    public const METHODS = [
        'cash' => 'Cash',
        'bank_transfer' => 'Bank transfer',
        'cheque' => 'Cheque',
        'card' => 'Card',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'expense_date' => 'date',
        'payroll_month' => 'date',
        'approved_at' => 'datetime',
    ];

    public function head(): BelongsTo
    {
        return $this->belongsTo(ExpenseHead::class, 'expense_head_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Everyone's expenses, or only the ones this person added or that are for them. */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->hasPermission('expenses.view_all')) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('created_by', $user->id)
            ->when($user->employee_id, fn ($q) => $q->orWhere('employee_id', $user->employee_id)));
    }
}
