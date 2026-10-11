<?php

namespace Database\Seeders;

use App\Models\ExpenseHead;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Default settings and the three standard expense heads. Safe to run on production.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Setting::DEFAULTS as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['group' => explode('.', $key)[0], 'value' => $value]);
        }
        Cache::forget('settings.all');

        foreach ([
            ['Salary', 'salary', 'teal', 'users', 900000],
            ['Daily office items', 'daily-office-items', 'amber', 'coffee', 60000],
            ['Office renovation', 'office-renovation', 'grey', 'hammer', 150000],
        ] as $i => [$name, $slug, $color, $icon, $budget]) {
            ExpenseHead::firstOrCreate(['slug' => $slug], ['name' => $name, 'color' => $color, 'icon' => $icon, 'monthly_budget' => $budget, 'sort_order' => $i]);
        }
    }
}
