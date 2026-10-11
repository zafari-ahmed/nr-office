<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectFollowUp;
use App\Models\Quotation;
use App\Models\Setting;
use App\Models\SiteVisit;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $today = today();
        $remindDays = Setting::int('rotation.remind_days_before');

        $thisMonth = (float) Expense::where('status', 'approved')
            ->whereBetween('expense_date', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])->sum('amount');
        $lastMonth = (float) Expense::where('status', 'approved')
            ->whereBetween('expense_date', [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()])->sum('amount');

        $onSite = SiteVisit::onSite()->with('employee', 'project')->orderBy('expected_return_date')->get();

        $stats = [
            'active_projects' => Project::where('status', 'active')->count(),
            'quotes_pending' => Quotation::where('status', 'sent')->count(),
            'unpaid_amount' => (float) Invoice::unpaid()->sum('balance_due'),
            'unpaid_count' => Invoice::unpaid()->count(),
            'expenses_this_month' => $thisMonth,
            'expenses_last_month' => $lastMonth,
            'expenses_change' => $lastMonth > 0 ? round(($thisMonth - $lastMonth) / $lastMonth * 100) : null,
            'on_site' => $onSite->count(),
            'leads' => Project::where('status', 'lead')->count(),
            'quotes_pending_value' => (float) Quotation::where('status', 'sent')->sum('total'),
            'overdue_invoices' => Invoice::where('status', 'overdue')->count(),
            'field_staff' => \App\Models\Employee::active()->count(),
            'on_site_overdue' => $onSite->filter(fn ($v) => $v->expected_return_date->isBefore($today))->count(),
            'on_site_soon' => $onSite->filter(fn ($v) => $v->expected_return_date->between($today, $today->copy()->addDays($remindDays)))->count(),
        ];

        // Needs attention: one list, each row with a one-click action.
        $attention = collect();
        $late = fn ($d) => ($n = (int) $d->diffInDays($today, true)).' '.str('day')->plural($n).' overdue';
        ProjectFollowUp::pending()->with('project')->whereDate('due_date', '<', $today)->orderBy('due_date')->limit(6)->get()
            ->each(fn ($f) => $attention->push([
                'type' => 'follow_up', 'tone' => 'red', 'icon' => 'phone',
                'title' => $f->title, 'meta' => $f->project->name, 'date' => $f->due_date, 'label' => 'Follow-up', 'due' => $late($f->due_date),
                'action' => ['label' => 'Mark done', 'method' => 'PATCH', 'url' => route('follow-ups.done', $f)],
            ]));
        Invoice::where('status', 'overdue')->with('project', 'client')->orderBy('due_date')->limit(5)->get()
            ->each(fn ($i) => $attention->push([
                'type' => 'invoice', 'tone' => 'red', 'icon' => 'receipt',
                'title' => $i->invoice_number.' · '.money($i->balance_due), 'meta' => $i->project->name, 'date' => $i->due_date, 'label' => $i->client?->name ?? 'Invoice', 'due' => $late($i->due_date),
                'action' => ['label' => 'Send reminder', 'url' => route('invoices.show', $i)],
            ]));
        Quotation::where('status', 'sent')->with('project')->where('sent_at', '<', now()->subDays(5))->limit(5)->get()
            ->each(fn ($q) => $attention->push([
                'type' => 'quotation', 'tone' => 'amber', 'icon' => 'file-text',
                'title' => $q->label().' awaiting reply', 'meta' => $q->project->name, 'date' => $q->sent_at, 'label' => 'Sent '.$q->sent_at->format('j M'), 'due' => $q->sent_at->diffInDays($today, true) >= 1 ? (int) $q->sent_at->diffInDays($today, true).' days waiting' : 'Today',
                'action' => ['label' => 'Follow up', 'url' => route('quotations.show', $q)],
            ]));
        $onSite->filter(fn ($v) => $v->expected_return_date->lte($today->copy()->addDays($remindDays)))
            ->each(fn ($v) => $attention->push([
                'type' => 'visit', 'tone' => $v->expected_return_date->isBefore($today) ? 'red' : 'amber', 'icon' => 'map-pin',
                'title' => $v->expected_return_date->isBefore($today) ? $v->employee->full_name." hasn't checked back in" : $v->employee->full_name.' due back '.($v->expected_return_date->isTomorrow() ? 'tomorrow' : $v->expected_return_date->format('j M')), 'meta' => $v->site_name, 'date' => $v->expected_return_date,
                'label' => $v->expected_return_date->isBefore($today) ? 'was due '.$v->expected_return_date->format('j M') : 'due back',
                'due' => $v->expected_return_date->isBefore($today) ? $late($v->expected_return_date) : $v->expected_return_date->format('j M'),
                'action' => ['label' => 'Mark returned', 'method' => 'PATCH', 'url' => route('rotation.return', $v)],
            ]));
        InventoryItem::lowStock()->with('category')->limit(5)->get()
            ->each(fn ($item) => $attention->push([
                'type' => 'stock', 'tone' => 'amber', 'icon' => 'package',
                'title' => $item->name.' running low', 'meta' => rtrim(rtrim(number_format($item->quantity, 2), '0'), '.').' left, minimum '.rtrim(rtrim(number_format($item->min_quantity, 2), '0'), '.'), 'date' => null, 'label' => $item->category?->name ?? 'Inventory', 'due' => 'Low stock',
                'action' => ['label' => 'Reorder', 'url' => route('inventory.show', $item)],
            ]));

        // Most urgent first: overdue items by how late they are, undated (stock) last.
        $attention = $attention->sortBy([
            fn ($a, $b) => ($a['tone'] === 'red' ? 0 : 1) <=> ($b['tone'] === 'red' ? 0 : 1),
            fn ($a, $b) => ($a['date']?->timestamp ?? PHP_INT_MAX) <=> ($b['date']?->timestamp ?? PHP_INT_MAX),
        ])->values();

        $upcoming = ProjectFollowUp::pending()->with('project', 'assignee')
            ->whereBetween('due_date', [$today, $today->copy()->addDays(7)])
            ->orderBy('due_date')->get()->groupBy(fn ($f) => $f->project_id);

        // Charts: last 6 months.
        $months = collect(range(5, 0))->map(fn ($i) => $today->copy()->startOfMonth()->subMonthsNoOverflow($i));
        $from = $months->first();
        $heads = ExpenseHead::orderBy('sort_order')->get();
        $expenseRows = Expense::where('status', 'approved')->where('expense_date', '>=', $from)
            ->get(['expense_head_id', 'expense_date', 'amount']);
        $paymentRows = \App\Models\InvoicePayment::where('payment_date', '>=', $from)->get(['payment_date', 'amount']);

        $monthKey = fn (Carbon $d) => $d->format('Y-m');
        $charts = [
            'labels' => $months->map(fn ($m) => $m->format('M'))->values(),
            'heads' => $heads->map(fn ($h) => [
                'label' => $h->name,
                'color' => $h->color,
                'data' => $months->map(fn ($m) => (float) $expenseRows->where('expense_head_id', $h->id)
                    ->filter(fn ($r) => $monthKey($r->expense_date) === $monthKey($m))->sum('amount'))->values(),
            ])->values(),
            'income' => $months->map(fn ($m) => (float) $paymentRows->filter(fn ($r) => $monthKey($r->payment_date) === $monthKey($m))->sum('amount'))->values(),
            'expenses' => $months->map(fn ($m) => (float) $expenseRows->filter(fn ($r) => $monthKey($r->expense_date) === $monthKey($m))->sum('amount'))->values(),
        ];

        $activity = ActivityLog::with('user', 'project')->latest()->limit(10)->get();

        return view('dashboard', compact('stats', 'attention', 'upcoming', 'onSite', 'charts', 'activity'));
    }
}
