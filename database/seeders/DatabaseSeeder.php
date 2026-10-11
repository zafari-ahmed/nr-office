<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\DocumentLog;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Setting;
use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class DatabaseSeeder extends Seeder
{
    /**
     * Base settings and expense heads always; sample data (Pakistani names, PKR) unless --class=SettingsSeeder.
     */
    public function run(): void
    {
        $this->call(SettingsSeeder::class);

        $admin = User::updateOrCreate(['email' => 'admin@nrassociates.pk'], [
            'name' => 'Zafar Ahmed', 'password' => 'password', 'role' => 'admin', 'is_active' => true,
        ]);
        Auth::login($admin);

        if (Project::exists()) {
            return;
        }

        $this->sample($admin);
    }

    private function sample(User $admin): void
    {
        $today = today();

        // Employees
        $people = [
            ['Ahmed Raza', 'Site Supervisor', 'Construction', 95000, true, 1],
            ['Bilal Khan', 'Site Engineer', 'Construction', 120000, true, 2],
            ['Usman Tariq', 'Foreman', 'Construction', 70000, true, 3],
            ['Hamza Iqbal', 'Junior Engineer', 'Construction', 65000, true, 4],
            ['Fatima Noor', 'Interior Designer', 'Design', 110000, false, null],
            ['Ayesha Siddiqui', 'Accounts Officer', 'Accounts', 85000, false, null],
            ['Kamran Shah', 'Project Manager', 'Management', 180000, false, null],
            ['Sana Malik', 'Office Assistant', 'Admin', 45000, false, null],
        ];
        $employees = collect($people)->map(function ($p, $i) use ($today) {
            $e = Employee::create([
                'employee_code' => 'EMP-'.str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'full_name' => $p[0], 'father_name' => Arr::random(['Muhammad', 'Abdul', 'Ghulam', 'Rashid', 'Khalid']).' '.explode(' ', $p[0])[1],
                'cnic' => '35202-'.rand(1000000, 9999999).'-'.rand(1, 9),
                'phone' => '0300-'.rand(1000000, 9999999), 'email' => strtolower(str_replace(' ', '.', $p[0])).'@nrassociates.pk',
                'address' => Arr::random(['Johar Town', 'Model Town', 'Gulberg III', 'Township', 'Wapda Town']).', Lahore',
                'designation' => $p[1], 'department' => $p[2], 'employment_type' => 'permanent',
                'joining_date' => $today->copy()->subMonths(rand(6, 60)), 'basic_salary' => $p[3],
                'bank_name' => Arr::random(['Meezan Bank', 'HBL', 'UBL', 'Bank Alfalah']),
                'status' => 'active', 'is_site_staff' => $p[4], 'rotation_order' => $p[5],
                'gender' => in_array($p[0], ['Fatima Noor', 'Ayesha Siddiqui', 'Sana Malik']) ? 'female' : 'male',
            ]);
            $e->salaries()->create(['effective_from' => $e->joining_date, 'basic_salary' => $p[3], 'net_salary' => $p[3], 'reason' => 'Joining salary']);

            return $e;
        });

        $manager = User::create(['name' => 'Kamran Shah', 'email' => 'kamran@nrassociates.pk', 'password' => 'password', 'role' => 'manager', 'employee_id' => $employees[6]->id]);
        User::create(['name' => 'Ayesha Siddiqui', 'email' => 'accounts@nrassociates.pk', 'password' => 'password', 'role' => 'accounts', 'employee_id' => $employees[5]->id]);
        User::create(['name' => 'Ahmed Raza', 'email' => 'ahmed@nrassociates.pk', 'password' => 'password', 'role' => 'staff', 'employee_id' => $employees[0]->id]);
        $employees[4]->leaves()->create(['leave_type' => 'annual', 'start_date' => $today->copy()->subDay(), 'end_date' => $today->copy()->addDays(2), 'reason' => 'Family wedding', 'status' => 'approved']);

        // Clients & projects
        $clients = collect([
            ['Mr. Imran Qureshi', null, 'imran.qureshi@gmail.com', '0321-4455667', 'House 45, Block C, Gulberg III, Lahore'],
            ['Faisal Mehmood', 'Mehmood Textiles (Pvt) Ltd', 'faisal@mehmoodtextiles.pk', '0300-8899001', 'Plot 12, Sundar Industrial Estate, Lahore'],
            ['Dr. Saima Javed', 'Javed Dental Clinic', 'saima@javeddental.pk', '0333-1122334', 'Main Boulevard, DHA Phase 5, Lahore'],
            ['Tariq Aziz', 'Aziz Developers', 'tariq@azizdevelopers.com', '0345-6677889', 'Bahria Town, Lahore'],
            ['Nadia Hussain', null, 'nadia.h@outlook.com', '0302-5566778', 'Street 7, F-7/2, Islamabad'],
        ])->map(fn ($c) => Client::create(['name' => $c[0], 'company_name' => $c[1], 'contact_person' => $c[0], 'email' => $c[2], 'phone' => $c[3], 'address' => $c[4], 'city' => str_contains($c[4], 'Islamabad') ? 'Islamabad' : 'Lahore']));

        $projectData = [
            ['Gulberg Residence Renovation', 0, 'active', 4850000, 'House 45, Block C, Gulberg III', 'Lahore'],
            ['DHA Office Fit-out', 1, 'active', 7600000, 'Office 3, Phase 6 Commercial, DHA', 'Lahore'],
            ['Javed Dental Clinic Interiors', 2, 'quoted', 2950000, 'Main Boulevard, DHA Phase 5', 'Lahore'],
            ['Bahria Town Villa Construction', 3, 'active', 18500000, 'Sector C, Bahria Town', 'Lahore'],
            ['F-7 Apartment Makeover', 4, 'lead', 1200000, 'Street 7, F-7/2', 'Islamabad'],
            ['Sundar Factory Admin Block', 1, 'on_hold', 9800000, 'Plot 12, Sundar Industrial Estate', 'Lahore'],
            ['Model Town Kitchen Remodel', 0, 'completed', 850000, '12-B Model Town', 'Lahore'],
        ];
        $projects = collect($projectData)->map(fn ($p, $i) => Project::create([
            'code' => 'NR-'.$today->year.'-'.str_pad($i + 8, 3, '0', STR_PAD_LEFT),
            'name' => $p[0], 'client_id' => $clients[$p[1]]->id, 'status' => $p[2], 'project_value' => $p[3],
            'site_address' => $p[4], 'city' => $p[5], 'manager_id' => $manager->id,
            'start_date' => $today->copy()->subDays(rand(20, 120)),
            'description' => 'Complete civil, electrical and finishing works as per approved drawings and BOQ.',
        ]));

        // Quotations
        $lines = [
            ['Demolition and debris removal', 1, 'job', 85000],
            ['False ceiling – gypsum board with framing', 1200, 'sq ft', 180],
            ['Porcelain floor tiles 60×60 incl. laying', 1500, 'sq ft', 420],
            ['Electrical rewiring with conduit', 1, 'job', 340000],
            ['Wardrobes – MDF with laminate finish', 6, 'nos', 95000],
            ['Paint – Berger weather coat (2 coats)', 3800, 'sq ft', 55],
        ];
        $qStatuses = ['accepted', 'sent', 'sent', 'accepted', 'draft', 'rejected', 'accepted'];
        $quotes = $projects->map(function ($project, $i) use ($admin, $today, $lines, $qStatuses) {
            $q = Quotation::create([
                'quotation_number' => 'QT-'.$today->year.'-'.str_pad($i + 21, 4, '0', STR_PAD_LEFT), 'revision' => 1,
                'project_id' => $project->id, 'client_id' => $project->client_id, 'title' => $project->name.' – Phase 1',
                'issue_date' => $today->copy()->subDays(30 - $i * 3), 'valid_until' => $today->copy()->addDays($i * 3),
                'status' => $qStatuses[$i], 'discount_type' => 'percent', 'discount_value' => $i % 2 ? 5 : 0, 'tax_rate' => 0,
                'sent_at' => $qStatuses[$i] !== 'draft' ? $today->copy()->subDays(25 - $i * 2) : null,
                'accepted_at' => $qStatuses[$i] === 'accepted' ? $today->copy()->subDays(20) : null,
                'terms' => Setting::get('finance.quotation_terms'), 'created_by' => $admin->id,
            ]);
            $q->syncItems(collect($lines)->shuffle()->take(rand(3, 6))->map(fn ($l) => ['description' => $l[0], 'quantity' => $l[1], 'unit' => $l[2], 'rate' => $l[3]])->all());

            return $q;
        });
        // A revised quote for the dental clinic.
        $rev = $quotes[2]->replicate(['sent_at']);
        $quotes[2]->update(['status' => 'revised']);
        $rev->fill(['revision' => 2, 'parent_id' => $quotes[2]->id, 'status' => 'sent', 'sent_at' => $today->copy()->subDays(8), 'discount_value' => 7])->save();
        $rev->syncItems($quotes[2]->items->map->only('description', 'quantity', 'unit', 'rate')->all());

        // Invoices with payments
        $invoiceSpecs = [[0, 0.5, 1.0, -40], [0, 0.3, 0.0, -10], [1, 0.4, 0.5, -25], [3, 0.25, 1.0, -60], [3, 0.25, 0.0, 5], [6, 1.0, 1.0, -90]];
        foreach ($invoiceSpecs as $n => [$pi, $share, $paidShare, $offset]) {
            $project = $projects[$pi];
            $issue = $today->copy()->addDays($offset);
            $inv = Invoice::create([
                'invoice_number' => 'INV-'.$today->year.'-'.str_pad($n + 31, 4, '0', STR_PAD_LEFT),
                'project_id' => $project->id, 'client_id' => $project->client_id, 'issue_date' => $issue, 'due_date' => $issue->copy()->addDays(15),
                'status' => 'sent', 'sent_at' => $issue, 'discount_type' => 'amount', 'discount_value' => 0, 'tax_rate' => 0, 'created_by' => $admin->id,
            ]);
            $inv->syncItems([['description' => ($n % 2 ? 'Running bill' : 'Mobilisation advance').' – '.$project->name, 'quantity' => 1, 'unit' => 'job', 'rate' => round($project->project_value * $share, -3)]]);
            if ($paidShare > 0) {
                $inv->payments()->create(['payment_date' => $issue->copy()->addDays(7), 'amount' => round($inv->total * $paidShare, -3), 'method' => 'bank_transfer', 'reference_no' => 'IBFT-'.rand(100000, 999999), 'received_by' => $admin->id]);
            }
            $inv->refreshPaymentStatus();
        }

        // Follow-ups
        $fu = [
            [0, 'Confirm tile selection with Mr. Imran', -2], [1, 'Share revised electrical layout', 1], [2, 'Call Dr. Saima about revised quote', 0],
            [3, 'Collect running bill cheque', 3], [4, 'Site measurement visit', 5], [1, 'Furniture vendor quotes due', 6], [3, 'Grey structure inspection', -1],
        ];
        $users = User::pluck('id');
        foreach ($fu as [$pi, $title, $d]) {
            $projects[$pi]->followUps()->create(['title' => $title, 'due_date' => $today->copy()->addDays($d), 'assigned_to' => $users->random(), 'created_by' => $admin->id]);
        }

        // Document log (sent from outside the system)
        $log = DocumentLog::create([
            'project_id' => $projects[5]->id, 'document_type' => 'quotation', 'reference_no' => 'QT-0142', 'amount' => 9800000,
            'sent_date' => $today->copy()->subDays(6), 'sent_to_name' => 'Faisal Mehmood', 'sent_to_email' => 'faisal@mehmoodtextiles.pk',
            'last_discussion' => 'Client wants the admin block split into two phases and asked for local tiles instead of imported.',
            'email_subject' => 'Revised quotation – Sundar Factory Admin Block',
            'email_body' => "Dear Faisal sahib,\n\nPlease find attached the revised quotation for the admin block, split into two phases as discussed.\n\nRegards,\nZafar Ahmed\nNR Associates",
            'next_follow_up_date' => $today->copy()->addDays(2), 'status' => 'sent', 'created_by' => $admin->id,
        ]);
        $log->followUp()->create(['project_id' => $log->project_id, 'title' => 'Follow up on quotation QT-0142', 'due_date' => $log->next_follow_up_date, 'assigned_to' => $admin->id, 'created_by' => $admin->id]);
        ActivityLog::create(['user_id' => $admin->id, 'project_id' => $log->project_id, 'subject_type' => $log->getMorphClass(), 'subject_id' => $log->id, 'action' => 'logged', 'description' => 'Quotation QT-0142 logged as sent to Faisal Mehmood']);

        // Expenses: 6 months
        $heads = ExpenseHead::pluck('id', 'slug');
        for ($m = 5; $m >= 0; $m--) {
            $month = $today->copy()->startOfMonth()->subMonthsNoOverflow($m);
            foreach ($employees as $e) {
                if ($m === 0 && $today->day < 28) {
                    break;
                }
                Expense::create(['expense_head_id' => $heads['salary'], 'employee_id' => $e->id, 'payroll_month' => $month, 'expense_date' => $month->copy()->endOfMonth(), 'amount' => $e->basic_salary, 'description' => 'Salary '.$month->format('M Y').' – '.$e->full_name, 'payment_method' => 'bank_transfer', 'status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => now(), 'created_by' => $admin->id]);
            }
            foreach (range(1, rand(6, 10)) as $k) {
                Expense::create(['expense_head_id' => $heads['daily-office-items'], 'expense_date' => $month->copy()->addDays(rand(0, min(27, $m === 0 ? max(0, $today->day - 1) : 27))), 'amount' => rand(8, 60) * 100, 'description' => Arr::random(['Tea, milk and sugar', 'Printer toner', 'Cleaning supplies', 'Stationery', 'Courier – TCS', 'Water bottles', 'Generator fuel']), 'vendor' => Arr::random(['Al-Fatah', 'Metro', 'Local store', 'TCS']), 'payment_method' => 'cash', 'status' => 'approved', 'approved_by' => $admin->id, 'created_by' => $admin->id]);
            }
            if ($m % 2 === 0) {
                Expense::create(['expense_head_id' => $heads['office-renovation'], 'expense_date' => $month->copy()->addDays(min(10, $today->day - 1)), 'amount' => rand(40, 180) * 1000, 'description' => Arr::random(['Conference room paint', 'New AC installation', 'Reception furniture', 'Washroom fixtures']), 'vendor' => 'Ali Brothers', 'payment_method' => 'cheque', 'status' => 'approved', 'approved_by' => $admin->id, 'created_by' => $admin->id]);
            }
        }
        Expense::create(['expense_head_id' => $heads['daily-office-items'], 'project_id' => $projects[0]->id, 'expense_date' => $today, 'amount' => 6500, 'description' => 'Site samples transport', 'payment_method' => 'cash', 'status' => 'pending', 'created_by' => $manager->id]);
        Expense::create(['expense_head_id' => $heads['office-renovation'], 'expense_date' => $today, 'amount' => 42000, 'description' => 'Office blinds', 'vendor' => 'Decor Point', 'payment_method' => 'cash', 'status' => 'pending', 'created_by' => $manager->id]);

        // Inventory
        $it = InventoryCategory::create(['name' => 'IT equipment', 'type' => 'asset']);
        $furniture = InventoryCategory::create(['name' => 'Furniture', 'type' => 'asset']);
        $tools = InventoryCategory::create(['name' => 'Site tools', 'type' => 'asset']);
        $stationery = InventoryCategory::create(['name' => 'Stationery', 'type' => 'consumable']);
        $pantry = InventoryCategory::create(['name' => 'Pantry', 'type' => 'consumable']);
        $assets = [
            [$it, 'Dell Latitude 5440 laptop', 'NR-LT-001', 285000, 6], [$it, 'HP LaserJet Pro printer', 'NR-PR-001', 95000, null],
            [$it, 'Lenovo ThinkPad E14', 'NR-LT-002', 240000, 1], [$furniture, 'Executive chair', 'NR-FN-004', 38000, null],
            [$tools, 'Bosch laser distance meter', 'NR-TL-002', 42000, 0], [$tools, 'Leica auto level', 'NR-TL-003', 165000, 1],
        ];
        foreach ($assets as [$cat, $name, $tag, $cost, $emp]) {
            $item = InventoryItem::create(['inventory_category_id' => $cat->id, 'type' => 'asset', 'name' => $name, 'asset_tag' => $tag, 'unit' => 'pcs', 'quantity' => 1, 'unit_cost' => $cost, 'condition' => 'good', 'location' => 'Main office', 'assigned_employee_id' => $emp !== null ? $employees[$emp]->id : null, 'purchase_date' => $today->copy()->subMonths(rand(2, 30)), 'warranty_until' => $today->copy()->addMonths(rand(-3, 18))]);
            $item->transactions()->create(['type' => 'purchase', 'quantity' => 1, 'balance_after' => 1, 'transaction_date' => $item->purchase_date, 'notes' => 'Opening stock', 'created_by' => $admin->id]);
        }
        foreach ([[$stationery, 'A4 paper ream', 'ream', 6, 10, 1450], [$stationery, 'Printer toner 26A', 'pcs', 1, 2, 8500], [$pantry, 'Tea (Tapal Danedar 900g)', 'pack', 5, 3, 1650], [$pantry, 'Sugar', 'kg', 2, 5, 160], [$stationery, 'Ball pens (box)', 'box', 8, 2, 450]] as [$cat, $name, $unit, $qty, $min, $cost]) {
            $item = InventoryItem::create(['inventory_category_id' => $cat->id, 'type' => 'consumable', 'name' => $name, 'unit' => $unit, 'quantity' => $qty, 'min_quantity' => $min, 'unit_cost' => $cost, 'location' => 'Store room']);
            $item->transactions()->create(['type' => 'purchase', 'quantity' => $qty, 'balance_after' => $qty, 'transaction_date' => $today->copy()->subDays(20), 'notes' => 'Opening stock', 'created_by' => $admin->id]);
        }

        // Site rotation
        $visits = [
            [0, 3, -5, 7, 'on_site'], [1, 1, -9, 7, 'on_site'], [2, 3, -12, 10, 'on_site'], [3, 0, 3, 7, 'scheduled'],
            [0, 1, -25, 7, 'returned'], [2, 0, -30, 10, 'returned'], [3, 3, -20, 7, 'returned'],
        ];
        foreach ($visits as [$ei, $pi, $startOffset, $days, $status]) {
            $dep = $today->copy()->addDays($startOffset);
            SiteVisit::create([
                'employee_id' => $employees[$ei]->id, 'project_id' => $projects[$pi]->id, 'site_name' => $projects[$pi]->name, 'site_address' => $projects[$pi]->site_address,
                'departure_date' => $dep, 'planned_days' => $days, 'expected_return_date' => $dep->copy()->addDays($days),
                'actual_return_date' => $status === 'returned' ? $dep->copy()->addDays($days) : null, 'status' => $status,
                'purpose' => 'Site supervision', 'created_by' => $admin->id,
            ]);
        }

        // A little recent activity
        foreach ([[3, 'Quotation QT-'.$today->year.'-0024 sent to client'], [0, 'Payment received on INV-'.$today->year.'-0031'], [3, 'Ahmed Raza left for Bahria Town Villa Construction']] as $i => [$pi, $text]) {
            ActivityLog::create(['user_id' => $admin->id, 'project_id' => $projects[$pi]->id, 'action' => 'note', 'description' => $text, 'created_at' => now()->subHours(3 * ($i + 1))]);
        }
    }
}
