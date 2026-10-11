<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use SoftDeletes;

    public const CONDITIONS = [
        'new' => 'New',
        'good' => 'Good',
        'fair' => 'Fair',
        'damaged' => 'Damaged',
        'disposed' => 'Disposed',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_until' => 'date',
        'quantity' => 'float',
        'min_quantity' => 'float',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class)->latest('transaction_date')->latest('id');
    }

    public function isLowStock(): bool
    {
        return $this->type === 'consumable' && $this->min_quantity > 0 && $this->quantity <= $this->min_quantity;
    }

    /** Stock bar percentage: current quantity vs 2× the reorder level. */
    public function stockPercent(): int
    {
        $target = max($this->min_quantity * 2, 1);

        return (int) min(100, round($this->quantity / $target * 100));
    }

    public function scopeLowStock($query)
    {
        return $query->where('type', 'consumable')->where('min_quantity', '>', 0)
            ->whereColumn('quantity', '<=', 'min_quantity');
    }
}
