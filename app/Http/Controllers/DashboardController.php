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
        ];

        // Needs attention: one list, each row with a one-click action.
        $attention = collect();
        ProjectFollowUp::pending()->with('project')->whereDate('due_date', '<', $today)->orderBy('due_date')->limit(6)->get()
            ->each(fn ($f) => $attention->push([
                'type' => 'follow_up', 'tone' => 'red', 'icon' => 'phone-call',
                'title' => $f->title, 'meta' => $f->project->name, 'date' => $f->due_date, 'label' => 'Overdue follow-up',
                'action' => ['label' => 'Done', 'method' => 'PATCH', 'url' => route('follow-ups.done', $f)],
            ]));
        Invoice::where('status', 'overdue')->with('project')->orderBy('due_date')->limit(5)->get()
            ->each(fn ($i) => $attention->push([
                'type' => 'invoice', 'tone' => 'red', 'icon' => 'receipt',
                'title' => $i->invoice_number.' · '.money($i->balance_due), 'meta' => $i->project->name, 'date' => $i->due_date, 'label' => 'Invoice overdue',
                'action' => ['label' => 'Record payment', 'url' => route('invoices.show', $i).'#payment'],
            ]));
        Quotation::where('status', 'sent')->with('project')->where('sent_at', '<', now()->subDays(5))->limit(5)->get()
            ->each(fn ($q) => $attention->push([
                'type' => 'quotation', 'tone' => 'amber', 'icon' => 'file-text',
                'title' => $q->label().' awaiting reply', 'meta' => $q->project->name, 'date' => $q->sent_at, 'label' => 'Quote awaiting reply',
                'action' => ['label' => 'Open', 'url' => route('quotations.show', $q)],
            ]));
        $onSite->filter(fn ($v) => $v->expected_return_date->lte($today->copy()->addDays($remindDays)))
            ->each(fn ($v) => $attention->push([
                'type' => 'visit', 'tone' => $v->expected_return_date->isBefore($today) ? 'red' : 'amber', 'icon' => 'map-pin',
                'title' => $v->employee->full_name.' due back', 'meta' => $v->site_name, 'date' => $v->expected_return_date,
                'label' => $v->expected_return_date->isBefore($today) ? 'Overdue on site' : 'Returning soon',
                'action' => ['label' => 'Mark returned', 'method' => 'PATCH', 'url' => route('rotation.return', $v)],
            ]));
        InventoryItem::lowStock()->limit(5)->get()
            ->each(fn ($item) => $attention->push([
                'type' => 'stock', 'tone' => 'amber', 'icon' => 'package',
                'title' => $item->name.' low ('.rtrim(rtrim(number_format($item->quantity, 2), '0'), '.').' '.$item->unit.' left)', 'meta' => 'Reorder at '.$item->min_quantity, 'date' => null, 'label' => 'Low stock',
                'action' => ['label' => 'Restock', 'url' => route('inventory.show', $item)],
            ]));

        $upcoming = ProjectFollowUp::pending()->with('project', 'assignee')
            ->whereBetween('due_date', [$today, $today->copy()->addDays(7)])
            ->orderBy('due_date')->get()->groupBy(fn ($f) => $f->project->name);

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
