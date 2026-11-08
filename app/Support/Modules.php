<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Modules the admin can switch off in Settings › Modules, stored as `module.{key}` ('1' on, '0' off).
 * A switched-off module disappears from the menu for everyone (admin included), its permissions stop
 * counting, and its pages return 404.
 */
class Modules
{
    /** key => [label, icon, description, route names that belong to it] */
    public const LIST = [
        'projects' => ['Projects & clients', 'folder-kanban', 'Projects, clients, follow-ups and notes.', ['projects.*', 'clients.*', 'follow-ups.*', 'notes.*']],
        'quotations' => ['Quotations', 'file-text', 'Quotations and the sent document log.', ['quotations.*', 'document-logs.*']],
        'invoices' => ['Invoices', 'receipt', 'Invoices and payments.', ['invoices.*', 'payments.*']],
        'expenses' => ['Expenses', 'wallet', 'Expenses and approvals.', ['expenses.*']],
        'inventory' => ['Inventory', 'package', 'Assets, consumables and stock.', ['inventory.*']],
        'employees' => ['Employees', 'users', 'Employee directory, leaves and salaries.', ['employees.*', 'leaves.*', 'salaries.*']],
        'rotation' => ['Site Rotation', 'calendar-range', 'Site visits and return reminders.', ['rotation.*']],
        'reports' => ['Reports', 'bar-chart-3', 'Reports and exports.', ['reports.*']],
    ];

    public static function enabled(string $module): bool
    {
        return ! isset(self::LIST[$module]) || Setting::get("module.$module", '1') !== '0';
    }

    /** The module a permission key or route name belongs to, if any. */
    public static function forRoute(?string $name): ?string
    {
        if (! $name) {
            return null;
        }
        foreach (self::LIST as $key => [, , , $patterns]) {
            foreach ($patterns as $pattern) {
                if (\Illuminate\Support\Str::is($pattern, $name)) {
                    return $key;
                }
            }
        }

        return null;
    }
}
