<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Access rights per role. Admin always has everything; the other roles are set in Settings › Access rights
 * and stored as `access.{role}` (a comma-separated list of permission keys).
 */
class Access
{
    /** Permission keys grouped by module, with the wording shown on the Access rights screen. */
    public const GROUPS = [
        'Dashboard' => [
            'dashboard.finance' => ['See money figures', 'Income, expenses and unpaid totals, charts and all recent activity on the dashboard.'],
        ],
        'Projects' => [
            'projects.view_all' => ['See all projects', 'Without this, people only see projects they manage or are on the team of.'],
            'projects.manage' => ['Create and edit projects', 'Add projects and clients, change details, status and team.'],
            'projects.delete' => ['Archive projects', 'Remove projects from the list.'],
        ],
        'Quotations & invoices' => [
            'quotations.view' => ['See quotations', 'Quotations and the sent document log.'],
            'quotations.manage' => ['Create and edit quotations', 'Draft, revise, send and convert quotations; log sent documents.'],
            'invoices.view' => ['See invoices', 'Invoices, payments and project money figures.'],
            'invoices.manage' => ['Create invoices and record payments', 'Edit invoices, record and remove payments.'],
        ],
        'Expenses' => [
            'expenses.view_all' => ["See everyone's expenses", 'Without this, people only see expenses they added or that are for them.'],
            'expenses.approve' => ['Approve expenses', 'Approve or reject expenses. Expenses they add are approved straight away.'],
        ],
        'Inventory' => [
            'inventory.view' => ['See inventory', 'Assets and consumables with stock levels.'],
            'inventory.manage' => ['Manage inventory', 'Add items, stock in and out, assign assets.'],
        ],
        'Employees' => [
            'employees.view' => ['See all employees', 'The Employees directory. Without this, people only see their own profile.'],
            'employees.manage' => ['Add and edit employees', 'Employee details, leave, documents and photos.'],
            'employees.salary' => ['See and change salaries', 'Salary history and salary revisions.'],
        ],
        'Site rotation' => [
            'rotation.view' => ['See site rotation', 'Who is on site and the rotation schedule.'],
            'rotation.manage' => ['Schedule and edit visits', 'Schedule, edit, extend, end and cancel site visits.'],
        ],
        'Reports & settings' => [
            'reports.view' => ['See reports', 'All reports and CSV export.'],
            'settings.manage' => ['Change settings', 'Company profile, rotation rules, notifications, expense heads, numbering and taxes.'],
            'users.manage' => ['Manage users', 'Add users, change roles and deactivate accounts.'],
        ],
    ];

    /** Starting rights for each role until Admin changes them. */
    public const DEFAULTS = [
        'manager' => [
            'dashboard.finance', 'projects.view_all', 'projects.manage', 'projects.delete', 'quotations.view', 'quotations.manage',
            'invoices.view', 'invoices.manage', 'expenses.view_all', 'expenses.approve', 'inventory.view', 'inventory.manage',
            'employees.view', 'employees.manage', 'rotation.view', 'rotation.manage', 'reports.view', 'settings.manage',
        ],
        'accounts' => [
            'dashboard.finance', 'projects.view_all', 'quotations.view', 'invoices.view', 'invoices.manage', 'expenses.view_all',
            'expenses.approve', 'inventory.view', 'employees.view', 'employees.salary', 'rotation.view', 'reports.view',
        ],
        'staff' => [],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::GROUPS)));
    }

    /** @return list<string> */
    public static function forRole(string $role): array
    {
        if ($role === 'admin') {
            return self::keys();
        }

        $stored = Setting::get("access.{$role}", implode(',', self::DEFAULTS[$role] ?? []));

        return array_values(array_intersect(self::keys(), explode(',', (string) $stored)));
    }
}
