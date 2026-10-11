<?php

namespace App\Models\Concerns;

/**
 * Shared totals engine for quotations and invoices.
 */
trait HasLineItems
{
    /**
     * Replace the line items and recalculate totals.
     *
     * @param  array<int, array{description:string, quantity:float|string, unit:?string, rate:float|string}>  $items
     */
    public function syncItems(array $items): void
    {
        $this->items()->delete();

        foreach (array_values($items) as $i => $item) {
            if (blank($item['description'] ?? null)) {
                continue;
            }
            $qty = (float) ($item['quantity'] ?? 1);
            $rate = (float) ($item['rate'] ?? 0);
            $this->items()->create([
                'sort_order' => $i,
                'description' => $item['description'],
                'quantity' => $qty,
                'unit' => $item['unit'] ?? null,
                'rate' => $rate,
                'amount' => round($qty * $rate, 2),
            ]);
        }

        $this->recalculate();
    }

    public function recalculate(): void
    {
        $subtotal = (float) $this->items()->sum('amount');
        $discount = $this->discount_type === 'percent'
            ? round($subtotal * (float) $this->discount_value / 100, 2)
            : min((float) $this->discount_value, $subtotal);
        $taxable = $subtotal - $discount;
        $tax = round($taxable * (float) $this->tax_rate / 100, 2);

        $this->forceFill([
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total' => $taxable + $tax,
        ]);

        if ($this instanceof \App\Models\Invoice) {
            $this->balance_due = max(0, $this->total - (float) $this->amount_paid);
        }

        $this->save();
    }
}
