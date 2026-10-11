<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value'];

    public const DEFAULTS = [
        'company.name' => 'NR Associates',
        'company.tagline' => 'Architecture · Interiors · Construction',
        'company.address' => 'Office 12, Main Boulevard, Gulberg III, Lahore',
        'company.phone' => '+92 42 3577 0000',
        'company.email' => 'info@nrassociates.pk',
        'company.ntn' => '',
        'finance.currency' => 'PKR',
        'finance.tax_rate' => '0',
        'finance.tax_label' => 'GST',
        'finance.payment_terms_days' => '15',
        'finance.quotation_terms' => "50% advance, balance on completion.\nQuotation valid for 30 days.",
        'numbering.project_prefix' => 'NR',
        'numbering.quotation_prefix' => 'QT',
        'numbering.invoice_prefix' => 'INV',
        'numbering.employee_prefix' => 'EMP',
        'rotation.default_stay_days' => '7',
        'rotation.remind_days_before' => '2',
        'rotation.overdue_reminder_every_days' => '1',
        'rotation.notify_roles' => 'admin,manager',
        'rotation.count_weekends' => '1',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever('settings.all', fn () => static::pluck('value', 'key')->all());

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['group' => explode('.', $key)[0], 'value' => $value]);
        Cache::forget('settings.all');
    }

    public static function int(string $key): int
    {
        return (int) static::get($key);
    }
}
