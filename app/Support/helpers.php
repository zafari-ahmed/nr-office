<?php

use App\Models\Setting;

if (! function_exists('money')) {
    /** Format an amount as currency, e.g. "PKR 1,250,000". */
    function money(float|int|string|null $amount, bool $withCurrency = true): string
    {
        $formatted = number_format((float) $amount, fmod((float) $amount, 1) === 0.0 ? 0 : 2);

        return $withCurrency ? Setting::get('finance.currency', 'PKR').' '.$formatted : $formatted;
    }
}

if (! function_exists('money_short')) {
    /** Compact amount for stat cards: 1.25M, 850K. */
    function money_short(float|int|string|null $amount): string
    {
        $amount = (float) $amount;

        return match (true) {
            abs($amount) >= 10_000_000 => round($amount / 10_000_000, 2).' Cr',
            abs($amount) >= 100_000 => round($amount / 100_000, 2).' Lac',
            abs($amount) >= 1_000 => round($amount / 1_000, 1).'K',
            default => number_format($amount),
        };
    }
}

if (! function_exists('status_tone')) {
    /** Map any status to a pill tone: grey draft, blue sent/active, amber pending, red overdue, green done. */
    function status_tone(?string $status): string
    {
        return match ($status) {
            'draft', 'lead', 'scheduled', 'cancelled', 'revised', 'expired', 'resigned', 'terminated' => 'gray',
            'sent', 'active', 'on_site', 'quoted', 'in_office', 'client_replied' => 'blue',
            'pending', 'partially_paid', 'returning_soon', 'on_hold', 'on_leave', 'due_soon', 'low' => 'amber',
            'overdue', 'rejected', 'damaged' => 'red',
            'paid', 'accepted', 'completed', 'approved', 'returned', 'done', 'new', 'good' => 'green',
            default => 'gray',
        };
    }
}

if (! function_exists('status_label')) {
    function status_label(?string $status): string
    {
        return $status ? ucfirst(str_replace('_', ' ', $status)) : '';
    }
}
