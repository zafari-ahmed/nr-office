<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    public const TYPES = [
        'purchase' => 'Purchase / stock in',
        'issue' => 'Issue / stock out',
        'return' => 'Return',
        'adjustment' => 'Adjustment (set count)',
        'assign' => 'Assign',
        'unassign' => 'Unassign',
        'dispose' => 'Dispose',
    ];

    protected $guarded = ['id'];

    protected $casts = ['transaction_date' => 'date'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
