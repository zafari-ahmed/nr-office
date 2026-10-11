<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasLineItems;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasAttachments, HasLineItems, SoftDeletes;

    public const STATUSES = [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'partially_paid' => 'Partially paid',
        'paid' => 'Paid',
        'overdue' => 'Overdue',
        'cancelled' => 'Cancelled',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'sent_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->latest('payment_date');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'sent', 'overdue'], true) && (float) $this->amount_paid === 0.0;
    }

    /** Recompute paid / balance / status from payments. */
    public function refreshPaymentStatus(): void
    {
        $paid = (float) $this->payments()->sum('amount');
        $this->amount_paid = $paid;
        $this->balance_due = max(0, (float) $this->total - $paid);

        if ($this->status !== 'cancelled' && $this->status !== 'draft') {
            $this->status = match (true) {
                $this->balance_due <= 0 => 'paid',
                $paid > 0 => 'partially_paid',
                $this->due_date && $this->due_date->isBefore(today()) => 'overdue',
                default => 'sent',
            };
        }

        $this->save();
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', ['sent', 'partially_paid', 'overdue']);
    }
}
