<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Project;
use App\Models\SiteVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ready-made reports. Each one returns columns + rows, so the same data feeds the screen and the CSV export.
 */
class ReportController extends Controller
{
    public const REPORTS = [
        'pnl' => ['Income & expenses', 'trending-up', 'Money received against approved expenses, month by month.', true],
        'expenses' => ['Expenses by head', 'wallet', 'Approved spending per head, compared with its budget.', true],
        'receivables' => ['Outstanding invoices', 'receipt', 'Unpaid balances by client, grouped by how late they are.', false],
        'projects' => ['Project summary', 'folder-kanban', 'Value, billing, payments and expenses for every project.', false],
        'rotation' => ['Site visits', 'calendar-range', 'Days each person spent on site, and late returns.', true],
        'inventory' => ['Inventory value', 'package', 'Stock value by category and items running low.', false],
    ];

    public const PERIODS = [
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_quarter' => 'This quarter',
        'fy' => 'This financial year',
        'this_year' => 'This calendar year',
        'last_12' => 'Last 12 months',
        'custom' => 'Custom dates',
    ];

    public function index(Request $request)
    {
        [$type, $from, $to, $period] = $this->filters($request);

        return view('reports.index', [
            'type' => $type,
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'report' => $this->{$type}($from, $to),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$type, $from, $to] = $this->filters($request);
        $report = $this->{$type}($from, $to);
        $name = str($report['title'])->slug().'-'.(self::REPORTS[$type][3] ? $from->format('Ymd').'-'.$to->format('Ymd') : today()->format('Ymd')).'.csv';

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_column($report['columns'], 0));
            foreach ($report['rows'] as $row) {
                fputcsv($out, array_map(fn ($k) => $row[$k] ?? '', array_keys($report['columns'])));
            }
            if ($report['totals']) {
                fputcsv($out, array_map(fn ($k) => $report['totals'][$k] ?? '', array_keys($report['columns'])));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /** @return array{0: string, 1: Carbon, 2: Carbon, 3: string} */
    private function filters(Request $request): array
    {
        $type = array_key_exists($request->query('type'), self::REPORTS) ? $request->query('type') : 'pnl';
        $period = array_key_exists($request->query('period'), self::PERIODS) ? $request->query('period') : 'fy';
        $today = today();

        [$from, $to] = match ($period) {
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$today->copy()->startOfQuarter(), $today->copy()->endOfQuarter()],
            // Pakistan's financial year runs July to June.
            'fy' => [$fy = $today->copy()->month(7)->startOfMonth()->subYears($today->month < 7 ? 1 : 0), $fy->copy()->addYear()->subDay()],
            'this_year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            'last_12' => [$today->copy()->subMonthsNoOverflow(11)->startOfMonth(), $today->copy()->endOfMonth()],
            'custom' => [
                $this->date($request->query('from')) ?? $today->copy()->startOfMonth(),
                $this->date($request->query('to')) ?? $today->copy(),
            ],
        };
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$type, $from->startOfDay(), $to->startOfDay(), $period];
    }

    private function date(?string $value): ?Carbon
    {
        try {
            return $value ? Carbon::createFromFormat('Y-m-d', $value)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** First day of every month in the range (capped at 36). */
    private function months(Carbon $from, Carbon $to): Collection
    {
        $months = collect();
        for ($m = $from->copy()->startOfMonth(); $m->lte($to) && $months->count() < 36; $m->addMonthNoOverflow()) {
            $months->push($m->copy());
        }

        return $months;
    }

    private function pnl(Carbon $from, Carbon $to): array
    {
        $income = InvoicePayment::whereBetween('payment_date', [$from, $to])->get(['payment_date', 'amount'])
            ->groupBy(fn ($p) => $p->payment_date->format('Y-m'))->map->sum('amount');
        $spent = Expense::where('status', 'approved')->whereBetween('expense_date', [$from, $to])->get(['expense_date', 'amount'])
            ->groupBy(fn ($e) => $e->expense_date->format('Y-m'))->map->sum('amount');

        $rows = $this->months($from, $to)->map(fn ($m) => [
            'month' => $m->format('M Y'),
            'income' => (float) ($income[$m->format('Y-m')] ?? 0),
            'expenses' => (float) ($spent[$m->format('Y-m')] ?? 0),
        ])->map(fn ($r) => $r + ['net' => $r['income'] - $r['expenses']]);

        $totals = ['month' => 'Total', 'income' => $rows->sum('income'), 'expenses' => $rows->sum('expenses'), 'net' => $rows->sum('net')];
        $months = max(1, $rows->count());

        return [
            'title' => 'Income & expenses',
            'stats' => [
                ['Received', money_short($totals['income']), money_short($totals['income'] / $months).' a month', 'green'],
                ['Spent', money_short($totals['expenses']), money_short($totals['expenses'] / $months).' a month', 'amber'],
                ['Net', money_short($totals['net']), $totals['income'] > 0 ? round($totals['net'] / $totals['income'] * 100).'% of income kept' : 'No income in this period', $totals['net'] < 0 ? 'red' : 'green'],
            ],
            'chart' => ['type' => 'line', 'labels' => $rows->pluck('month'), 'series' => [
                ['label' => 'Income', 'color' => 'teal', 'data' => $rows->pluck('income'), 'fill' => true],
                ['label' => 'Expenses', 'color' => 'amber', 'data' => $rows->pluck('expenses')],
            ]],
            'columns' => ['month' => ['Month', 'text'], 'income' => ['Income', 'money'], 'expenses' => ['Expenses', 'money'], 'net' => ['Net', 'signed']],
            'rows' => $rows->values()->all(),
            'totals' => $totals,
        ];
    }

    private function expenses(Carbon $from, Carbon $to): array
    {
        $months = $this->months($from, $to);
        $heads = ExpenseHead::orderBy('sort_order')->get();
        $entries = Expense::where('status', 'approved')->whereBetween('expense_date', [$from, $to])->get(['expense_head_id', 'expense_date', 'amount']);
        $total = (float) $entries->sum('amount');

        $rows = $heads->map(function ($head) use ($entries, $months, $total) {
            $mine = $entries->where('expense_head_id', $head->id);
            $budget = (float) $head->monthly_budget * $months->count();
            $amount = (float) $mine->sum('amount');

            return [
                'head' => $head->name,
                'entries' => $mine->count(),
                'amount' => $amount,
                'share' => $total > 0 ? round($amount / $total * 100, 1) : 0,
                'budget' => $budget,
                'used' => $budget > 0 ? round($amount / $budget * 100, 1) : null,
            ];
        })->filter(fn ($r) => $r['entries'] > 0 || $r['budget'] > 0)->values();

        $budget = $rows->sum('budget');
        $top = $rows->sortByDesc('amount')->first();

        return [
            'title' => 'Expenses by head',
            'stats' => [
                ['Spent', money_short($total), $entries->count().' approved entries', 'amber'],
                ['Budget', $budget > 0 ? money_short($budget) : '—', $budget > 0 ? round($total / $budget * 100).'% used' : 'No budgets set', $budget > 0 && $total > $budget ? 'red' : 'green'],
                ['Largest head', $top['head'] ?? '—', $top ? money_short($top['amount']).' · '.$top['share'].'%' : '', 'blue'],
            ],
            'chart' => ['type' => 'stacked', 'labels' => $months->map->format('M y'), 'series' => $heads->map(fn ($h) => [
                'label' => $h->name, 'color' => $h->color,
                'data' => $months->map(fn ($m) => (float) $entries->where('expense_head_id', $h->id)
                    ->filter(fn ($e) => $e->expense_date->format('Y-m') === $m->format('Y-m'))->sum('amount'))->values(),
            ])->values()],
            'columns' => ['head' => ['Head', 'text'], 'entries' => ['Entries', 'int'], 'amount' => ['Spent', 'money'], 'share' => ['Share', 'pct'], 'budget' => ['Budget', 'money'], 'used' => ['Budget used', 'meter']],
            'rows' => $rows->all(),
            'totals' => ['head' => 'Total', 'entries' => $rows->sum('entries'), 'amount' => $total, 'share' => $total > 0 ? 100 : 0, 'budget' => $budget, 'used' => $budget > 0 ? round($total / $budget * 100, 1) : null],
        ];
    }

    private function receivables(): array
    {
        $today = today();
        $buckets = ['current' => 'Not yet due', 'd30' => '1–30 days late', 'd60' => '31–60 days', 'd90' => '61–90 days', 'd90p' => 'Over 90 days'];

        $rows = Invoice::unpaid()->where('balance_due', '>', 0)->with('client', 'project.client')->get()
            ->groupBy(fn ($i) => ($i->client ?? $i->project?->client)?->displayName() ?? 'No client')
            ->map(function ($invoices, $client) use ($today, $buckets) {
                $row = ['client' => $client, 'invoices' => $invoices->count()] + array_fill_keys(array_keys($buckets), 0.0);
                foreach ($invoices as $invoice) {
                    $late = $invoice->due_date && $invoice->due_date->lt($today) ? (int) $invoice->due_date->diffInDays($today) : 0;
                    $bucket = match (true) { $late === 0 => 'current', $late <= 30 => 'd30', $late <= 60 => 'd60', $late <= 90 => 'd90', default => 'd90p' };
                    $row[$bucket] += (float) $invoice->balance_due;
                }

                return $row + ['total' => array_sum(array_intersect_key($row, $buckets))];
            })->sortByDesc('total')->values();

        $totals = ['client' => 'Total', 'invoices' => $rows->sum('invoices')] + collect($buckets)->map(fn ($l, $k) => $rows->sum($k))->all() + ['total' => $rows->sum('total')];
        $late = $totals['total'] - $totals['current'];

        return [
            'title' => 'Outstanding invoices',
            'stats' => [
                ['Outstanding', money_short($totals['total']), $totals['invoices'].' unpaid invoices', 'blue'],
                ['Overdue', money_short($late), $totals['total'] > 0 ? round($late / $totals['total'] * 100).'% of outstanding' : 'Nothing outstanding', $late > 0 ? 'red' : 'green'],
                ['Over 90 days', money_short($totals['d90p']), 'Hardest to collect', $totals['d90p'] > 0 ? 'red' : 'green'],
            ],
            'chart' => null,
            'columns' => ['client' => ['Client', 'text'], 'invoices' => ['Invoices', 'int']] + collect($buckets)->map(fn ($l) => [$l, 'money'])->all() + ['total' => ['Total', 'money']],
            'rows' => $rows->all(),
            'totals' => $totals,
        ];
    }

    private function projects(): array
    {
        $rows = Project::with('client')
            ->withSum(['invoices as invoiced' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'total')
            ->withSum(['invoices as received' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'amount_paid')
            ->withSum(['invoices as outstanding' => fn ($q) => $q->whereNotIn('status', ['cancelled', 'draft'])], 'balance_due')
            ->withSum(['expenses as spent' => fn ($q) => $q->where('status', 'approved')], 'amount')
            ->orderByDesc('created_at')->get()
            ->map(fn ($p) => [
                'code' => $p->code,
                'project' => $p->name,
                'client' => $p->client?->displayName() ?? '—',
                'status' => status_label($p->status),
                'value' => (float) $p->project_value,
                'invoiced' => (float) $p->invoiced,
                'received' => (float) $p->received,
                'outstanding' => (float) $p->outstanding,
                'spent' => (float) $p->spent,
                'margin' => (float) $p->received - (float) $p->spent,
            ]);

        $sum = fn ($k) => $rows->sum($k);

        return [
            'title' => 'Project summary',
            'stats' => [
                ['Project value', money_short($sum('value')), $rows->count().' projects', 'blue'],
                ['Received', money_short($sum('received')), $sum('invoiced') > 0 ? round($sum('received') / $sum('invoiced') * 100).'% of invoiced' : 'Nothing invoiced yet', 'green'],
                ['Received less expenses', money_short($sum('margin')), money_short($sum('spent')).' spent on projects', $sum('margin') < 0 ? 'red' : 'green'],
            ],
            'chart' => null,
            'columns' => ['code' => ['Code', 'mono'], 'project' => ['Project', 'text'], 'client' => ['Client', 'text'], 'status' => ['Status', 'text'], 'value' => ['Value', 'money'], 'invoiced' => ['Invoiced', 'money'], 'received' => ['Received', 'money'], 'outstanding' => ['Outstanding', 'money'], 'spent' => ['Expenses', 'money'], 'margin' => ['Received − expenses', 'signed']],
            'rows' => $rows->all(),
            'totals' => ['code' => 'Total', 'project' => '', 'client' => '', 'status' => ''] + collect(['value', 'invoiced', 'received', 'outstanding', 'spent', 'margin'])->mapWithKeys(fn ($k) => [$k => $sum($k)])->all(),
        ];
    }

    private function rotation(Carbon $from, Carbon $to): array
    {
        $today = today();
        $visits = SiteVisit::whereIn('status', ['on_site', 'returned'])
            ->whereDate('departure_date', '<=', $to)
            ->where(fn ($q) => $q->whereNull('actual_return_date')->orWhereDate('actual_return_date', '>=', $from))
            ->get();

        $rows = Employee::whereIn('id', $visits->pluck('employee_id')->unique())->orderBy('full_name')->get()
            ->map(function ($employee) use ($visits, $from, $to, $today) {
                $mine = $visits->where('employee_id', $employee->id);
                $days = $mine->sum(function ($v) use ($from, $to, $today) {
                    $end = $v->actual_return_date ?? $today->copy()->min($to);
                    $start = $v->departure_date->copy()->max($from);
                    $end = $end->copy()->min($to);

                    return $end->gte($start) ? (int) $start->diffInDays($end) + 1 : 0;
                });
                $late = $mine->filter(fn ($v) => ($v->actual_return_date ?? $today)->gt($v->expected_return_date))->count();
                $current = $mine->firstWhere('status', 'on_site');

                return [
                    'employee' => $employee->full_name,
                    'designation' => $employee->designation ?? '—',
                    'visits' => $mine->count(),
                    'days' => $days,
                    'late' => $late,
                    'now' => $current ? $current->site_name : '—',
                ];
            })->sortByDesc('days')->values();

        return [
            'title' => 'Site visits',
            'stats' => [
                ['Days on site', number_format($rows->sum('days')), $rows->count().' people travelled', 'blue'],
                ['Visits', number_format($rows->sum('visits')), $rows->sum('visits') > 0 ? round($rows->sum('days') / $rows->sum('visits'), 1).' days on average' : 'None in this period', 'gray'],
                ['Late returns', number_format($rows->sum('late')), 'Back after the planned date', $rows->sum('late') > 0 ? 'red' : 'green'],
            ],
            'chart' => null,
            'columns' => ['employee' => ['Employee', 'text'], 'designation' => ['Designation', 'text'], 'visits' => ['Visits', 'int'], 'days' => ['Days on site', 'int'], 'late' => ['Late returns', 'int'], 'now' => ['On site now', 'text']],
            'rows' => $rows->all(),
            'totals' => ['employee' => 'Total', 'designation' => '', 'visits' => $rows->sum('visits'), 'days' => $rows->sum('days'), 'late' => $rows->sum('late'), 'now' => ''],
        ];
    }

    private function inventory(): array
    {
        $items = InventoryItem::with('category')->get();
        $rows = $items->groupBy(fn ($i) => $i->category?->name ?? 'Uncategorised')
            ->map(fn ($group, $category) => [
                'category' => $category,
                'type' => status_label($group->first()->type),
                'items' => $group->count(),
                'value' => (float) $group->sum(fn ($i) => (float) $i->quantity * (float) $i->unit_cost),
                'low' => $group->filter->isLowStock()->count(),
            ])->sortByDesc('value')->values();

        $low = $items->filter->isLowStock();

        return [
            'title' => 'Inventory value',
            'stats' => [
                ['Stock value', money_short($rows->sum('value')), $items->count().' items', 'blue'],
                ['Assets', money_short($rows->where('type', 'Asset')->sum('value')), 'Furniture, equipment, IT', 'gray'],
                ['Low stock', (string) $low->count(), $low->take(3)->pluck('name')->implode(', ') ?: 'Everything stocked', $low->isNotEmpty() ? 'amber' : 'green'],
            ],
            'chart' => null,
            'columns' => ['category' => ['Category', 'text'], 'type' => ['Type', 'text'], 'items' => ['Items', 'int'], 'value' => ['Value', 'money'], 'low' => ['Low stock', 'int']],
            'rows' => $rows->all(),
            'totals' => ['category' => 'Total', 'type' => '', 'items' => $rows->sum('items'), 'value' => $rows->sum('value'), 'low' => $rows->sum('low')],
        ];
    }
}
