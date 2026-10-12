<?php

namespace App\Http\Controllers;

use App\Models\ExpenseHead;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public const SECTIONS = [
        'company' => ['Company profile', 'building-2'],
        'users' => ['Users & roles', 'shield-check'],
        'rotation' => ['Rotation rules', 'refresh-cw'],
        'notifications' => ['Notifications', 'bell'],
        'expense-heads' => ['Expense heads & budgets', 'wallet'],
        'numbering' => ['Numbering', 'hash'],
        'finance' => ['Taxes & currency', 'percent'],
    ];

    private const RULES = [
        'company' => [
            'company.name' => ['required', 'string', 'max:255'],
            'company.tagline' => ['nullable', 'string', 'max:255'],
            'company.address' => ['nullable', 'string', 'max:500'],
            'company.phone' => ['nullable', 'string', 'max:50'],
            'company.email' => ['nullable', 'email', 'max:255'],
            'company.ntn' => ['nullable', 'string', 'max:50'],
        ],
        'rotation' => [
            'rotation.default_stay_days' => ['required', 'integer', 'min:1', 'max:120'],
            'rotation.remind_days_before' => ['required', 'integer', 'min:0', 'max:30'],
            'rotation.overdue_reminder_every_days' => ['required', 'integer', 'min:1', 'max:30'],
            'rotation.count_weekends' => ['required', 'boolean'],
        ],
        'notifications' => [
            'notify.invoice_overdue' => ['required', 'boolean'],
            'notify.invoice_overdue_roles' => ['nullable', 'array'],
            'notify.invoice_overdue_roles.*' => ['in:admin,manager,accounts,staff'],
            'notify.expense_pending' => ['required', 'boolean'],
            'notify.expense_pending_roles' => ['nullable', 'array'],
            'notify.expense_pending_roles.*' => ['in:admin,manager,accounts,staff'],
            'notify.follow_up_due' => ['required', 'boolean'],
            'notify.follow_up_roles' => ['nullable', 'array'],
            'notify.follow_up_roles.*' => ['in:admin,manager,accounts,staff'],
            'notify.rotation_return' => ['required', 'boolean'],
            'notify.rotation_overdue' => ['required', 'boolean'],
            'rotation.notify_roles' => ['nullable', 'array'],
            'rotation.notify_roles.*' => ['in:admin,manager,accounts,staff'],
            'notify.low_stock' => ['required', 'boolean'],
            'notify.low_stock_roles' => ['nullable', 'array'],
            'notify.low_stock_roles.*' => ['in:admin,manager,accounts,staff'],
            'notify.low_stock_frequency' => ['required', 'in:weekly,daily'],
        ],
        'numbering' => [
            'numbering.project_prefix' => ['required', 'alpha_num', 'max:10'],
            'numbering.quotation_prefix' => ['required', 'alpha_num', 'max:10'],
            'numbering.invoice_prefix' => ['required', 'alpha_num', 'max:10'],
            'numbering.employee_prefix' => ['required', 'alpha_num', 'max:10'],
        ],
        'finance' => [
            'finance.currency' => ['required', 'string', 'max:5'],
            'finance.tax_label' => ['required', 'string', 'max:20'],
            'finance.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'finance.payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'finance.quotation_terms' => ['nullable', 'string', 'max:5000'],
        ],
    ];

    public function show(string $section)
    {
        if ($section === 'users') {
            return redirect()->route('settings.users.index');
        }
        abort_unless(array_key_exists($section, self::SECTIONS), 404);

        return view('settings.show', [
            'section' => $section,
            'sections' => self::SECTIONS,
            'heads' => $section === 'expense-heads' ? ExpenseHead::orderBy('sort_order')->get() : collect(),
            'roles' => User::ROLES,
            'roleHolders' => User::where('is_active', true)->get(['name', 'role'])->groupBy('role')->map(fn ($u) => $u->pluck('name')->implode(', ')),
        ]);
    }

    public function update(Request $request, string $section)
    {
        abort_unless(isset(self::RULES[$section]), 404);
        // Fields are posted as company[name] etc., which match the dotted rule keys.
        $request->validate(self::RULES[$section]);

        foreach (array_keys(self::RULES[$section]) as $key) {
            if (str_contains($key, '*')) {
                continue;
            }
            $value = $request->input($key);
            Setting::put($key, is_array($value) ? implode(',', $value) : (string) ($value ?? ''));
        }

        return back()->with('success', self::SECTIONS[$section][0].' saved.');
    }
}
