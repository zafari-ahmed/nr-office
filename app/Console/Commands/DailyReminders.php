<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\ProjectFollowUp;
use App\Models\Setting;
use App\Models\SiteVisit;
use App\Models\User;
use App\Notifications\AppAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class DailyReminders extends Command
{
    protected $signature = 'nr:daily-reminders';

    protected $description = 'Flag overdue invoices, start scheduled visits and send follow-up, expense, rotation and low-stock reminders';

    public function handle(): int
    {
        $today = today();
        $managers = $this->usersFor('rotation.notify_roles');

        // Invoices past due date become overdue.
        $overdue = Invoice::whereIn('status', ['sent', 'partially_paid'])->whereDate('due_date', '<', $today)->get();
        foreach ($overdue as $invoice) {
            $invoice->update(['status' => 'overdue']);
            if (! $this->enabled('invoice_overdue')) {
                continue;
            }
            Notification::send($this->usersFor('notify.invoice_overdue_roles'), new AppAlert(
                "Invoice {$invoice->invoice_number} is overdue",
                money($invoice->balance_due).' due on '.$invoice->due_date->format('d M'),
                route('invoices.show', $invoice), 'red', 'receipt'));
        }

        // Scheduled visits whose departure date has arrived are now on site.
        SiteVisit::where('status', 'scheduled')->whereDate('departure_date', '<=', $today)->update(['status' => 'on_site']);

        // Expenses waiting for approval: one digest a day.
        $pending = Expense::where('status', 'pending');
        if ($this->enabled('expense_pending') && ($count = $pending->count()) > 0) {
            Notification::send($this->usersFor('notify.expense_pending_roles'), new AppAlert(
                $count === 1 ? '1 expense waiting for approval' : "{$count} expenses waiting for approval",
                money($pending->sum('amount')).' in total', route('expenses.index', ['status' => 'pending']), 'amber', 'wallet'));
        }

        // Follow-ups due today go to their assignee (or the chosen roles).
        $followUpUsers = $this->usersFor('notify.follow_up_roles');
        ProjectFollowUp::pending()->with('project', 'assignee')->whereDate('due_date', $today)->get()
            ->filter(fn () => $this->enabled('follow_up_due'))
            ->each(fn ($f) => Notification::send($f->assignee ? [$f->assignee] : $followUpUsers, new AppAlert(
                'Follow-up due today', "{$f->title} · {$f->project->name}", route('projects.show', [$f->project, 'tab' => 'follow-ups']), 'amber', 'phone-call')));

        // Rotation: remind N days before return, then repeat while overdue.
        $remindDays = Setting::int('rotation.remind_days_before');
        $every = max(1, Setting::int('rotation.overdue_reminder_every_days'));
        foreach (SiteVisit::onSite()->with('employee')->get() as $visit) {
            $name = $visit->employee->full_name;
            if ($visit->expected_return_date->isBefore($today)) {
                if (! $this->enabled('rotation_overdue')) {
                    continue;
                }
                if (! $visit->last_overdue_reminder_at || $visit->last_overdue_reminder_at->diffInDays(now()) >= $every) {
                    Notification::send($this->withEmployeeUser($managers, $visit), new AppAlert(
                        "{$name} is overdue from site",
                        "{$visit->site_name} · was due back ".$visit->expected_return_date->format('d M'),
                        route('rotation.index'), 'red', 'map-pin'));
                    $visit->update(['last_overdue_reminder_at' => now()]);
                }
            } elseif ($this->enabled('rotation_return') && ! $visit->reminder_sent_at && $visit->expected_return_date->lte($today->copy()->addDays($remindDays))) {
                Notification::send($this->withEmployeeUser($managers, $visit), new AppAlert(
                    "{$name} returns ".$visit->expected_return_date->format('D d M'),
                    "{$visit->site_name} · plan the next person in rotation",
                    route('rotation.index'), 'amber', 'map-pin'));
                $visit->update(['reminder_sent_at' => now()]);
            }
        }

        $low = InventoryItem::lowStock()->count();
        $lowDue = Setting::get('notify.low_stock_frequency') === 'daily' || $today->isMonday();
        if ($low > 0 && $lowDue && $this->enabled('low_stock')) {
            Notification::send($this->usersFor('notify.low_stock_roles'), new AppAlert("{$low} items are low on stock", 'Review consumables and reorder.', route('inventory.index', ['tab' => 'consumable', 'low' => 1]), 'amber', 'package'));
        }

        $this->info("Reminders sent. {$overdue->count()} invoices flagged overdue.");

        return self::SUCCESS;
    }

    private function enabled(string $alert): bool
    {
        return Setting::get("notify.{$alert}") === '1';
    }

    private function usersFor(string $rolesKey)
    {
        return User::where('is_active', true)->whereIn('role', array_filter(explode(',', (string) Setting::get($rolesKey))))->get();
    }

    private function withEmployeeUser($users, SiteVisit $visit)
    {
        $own = User::where('employee_id', $visit->employee_id)->where('is_active', true)->get();

        return $users->merge($own)->unique('id');
    }
}
