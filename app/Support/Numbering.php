<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Setting;

/**
 * Generates human-friendly document numbers: NR-2026-014, QT-2026-0007, INV-2026-0012, EMP-008.
 */
class Numbering
{
    public static function project(): string
    {
        return self::next(Project::withTrashed(), 'code', Setting::get('numbering.project_prefix').'-'.now()->year.'-', 3);
    }

    public static function quotation(): string
    {
        return self::next(Quotation::withTrashed(), 'quotation_number', Setting::get('numbering.quotation_prefix').'-'.now()->year.'-', 4);
    }

    public static function invoice(): string
    {
        return self::next(Invoice::withTrashed(), 'invoice_number', Setting::get('numbering.invoice_prefix').'-'.now()->year.'-', 4);
    }

    public static function employee(): string
    {
        return self::next(Employee::withTrashed(), 'employee_code', Setting::get('numbering.employee_prefix').'-', 3);
    }

    private static function next($query, string $column, string $prefix, int $pad): string
    {
        $last = $query->where($column, 'like', $prefix.'%')->pluck($column)
            ->map(fn ($v) => (int) substr($v, strlen($prefix)))->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), $pad, '0', STR_PAD_LEFT);
    }
}
