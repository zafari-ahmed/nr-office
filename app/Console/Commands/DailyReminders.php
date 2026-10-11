<?php

namespace App\Console\Commands;

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

    protected $description = 'Flag overdue invoices, start scheduled visits and send follow-up, rotation and low-stock reminders';

    public function handle(): int
    {
        $today = today();
        $managers = User::where('is_active', true)->whereIn('role', explode(',', Setting::get('rotation.notify_roles')))->get();

        // Invoices past due date become overdue.
        $overdue = Invoice::whereIn('status', ['sent', 'partially_paid'])->whereDate('due_date', '<', $today)->get();
        foreach ($overdue as $invoice) {
            $invoice->update(['status' => 'overdue']);
            Notification::send($this->accounts(), new AppAlert(
                "Invoice {$invoice->invoice_number} is overdue",
                money($invoice->balance_due).' due on '.$invoice->due_date->format('d M'),
                route('invoices.show', $invoice), 'red', 'receipt'));
        }

        // Scheduled visits whose departure date has arrived are now on site.
        SiteVisit::where('status', 'scheduled')->whereDate('departure_date', '<=', $today)->update(['status' => 'on_site']);

        // Follow-ups due today go to their assignee (or the managers).
        ProjectFollowUp::pending()->with('project', 'assignee')->whereDate('due_date', $today)->get()
            ->each(fn ($f) => Notification::send($f->assignee ? [$f->assignee] : $managers, new AppAlert(
                'Follow-up due today', "{$f->title} · {$f->project->name}", route('projects.show', [$f->project, 'tab' => 'follow-ups']), 'amber', 'phone-call')));

        // Rotation: remind N days before return, then repeat while overdue.
        $remindDays = Setting::int('rotation.remind_days_before');
        $every = max(1, Setting::int('rotation.overdue_reminder_every_days'));
        foreach (SiteVisit::onSite()->with('employee')->get() as $visit) {
            $name = $visit->employee->full_name;
            if ($visit->expected_return_date->isBefore($today)) {
                if (! $visit->last_overdue_reminder_at || $visit->last_overdue_reminder_at->diffInDays(now()) >= $every) {
                    Notification::send($this->withEmployeeUser($managers, $visit), new AppAlert(
                        "{$name} is overdue from site",
                        "{$visit->site_name} · was due back ".$visit->expected_return_date->format('d M'),
                        route('rotation.index'), 'red', 'map-pin'));
                    $visit->update(['last_overdue_reminder_at' => now()]);
                }
            } elseif (! $visit->reminder_sent_at && $visit->expected_return_date->lte($today->copy()->addDays($remindDays))) {
                Notification::send($this->withEmployeeUser($managers, $visit), new AppAlert(
                    "{$name} returns ".$visit->expected_return_date->format('D d M'),
                    "{$visit->site_name} · plan the next person in rotation",
                    route('rotation.index'), 'amber', 'map-pin'));
                $visit->update(['reminder_sent_at' => now()]);
            }
        }

        $low = InventoryItem::lowStock()->count();
        if ($low > 0 && $today->isMonday()) {
            Notification::send($managers, new AppAlert("{$low} items are low on stock", 'Review consumables and reorder.', route('inventory.index', ['tab' => 'consumable', 'low' => 1]), 'amber', 'package'));
        }

        $this->info("Reminders sent. {$overdue->count()} invoices flagged overdue.");

        return self::SUCCESS;
    }

    private function accounts()
    {
        return User::where('is_active', true)->whereIn('role', ['admin', 'accounts'])->get();
    }

    private function withEmployeeUser($users, SiteVisit $visit)
    {
        $own = User::where('employee_id', $visit->employee_id)->where('is_active', true)->get();

        return $users->merge($own)->unique('id');
    }
}
