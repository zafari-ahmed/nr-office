<?php

namespace Tests\Feature;

use App\Models\DocumentLog;
use App\Models\Employee;
use App\Models\ExpenseHead;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Setting;
use App\Models\SiteVisit;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        $this->admin = User::create(['name' => 'Zafar', 'email' => 'z@nr.pk', 'password' => 'password', 'role' => 'admin']);
        $this->actingAs($this->admin);
    }

    private function project(): Project
    {
        $this->post('/projects', ['name' => 'Gulberg Residence', 'status' => 'lead', 'new_client_name' => 'Imran Qureshi', 'project_value' => 500000])->assertRedirect();

        return Project::latest('id')->first();
    }

    public function test_project_gets_code_and_inline_client(): void
    {
        $project = $this->project();
        $this->assertSame('NR-'.now()->year.'-001', $project->code);
        $this->assertSame('Imran Qureshi', $project->client->name);
    }

    public function test_quotation_totals_revision_and_convert_to_invoice(): void
    {
        $project = $this->project();
        $this->post('/quotations', [
            'project_id' => $project->id, 'title' => 'Phase 1', 'issue_date' => today()->toDateString(),
            'discount_type' => 'percent', 'discount_value' => 10, 'tax_rate' => 5, 'action' => 'send',
            'items' => [
                ['description' => 'Tiles', 'quantity' => 100, 'unit' => 'sq ft', 'rate' => 400],
                ['description' => 'Paint', 'quantity' => 2, 'unit' => 'job', 'rate' => 30000],
                ['description' => '', 'quantity' => 1, 'rate' => 0],
            ],
        ])->assertRedirect();

        $quote = Quotation::first();
        $this->assertEquals(100000, $quote->subtotal);
        $this->assertEquals(10000, $quote->discount_amount);
        $this->assertEquals(4500, $quote->tax_amount);
        $this->assertEquals(94500, $quote->total);
        $this->assertSame('sent', $quote->status);
        $this->assertSame('quoted', $project->fresh()->status);
        $this->assertCount(2, $quote->items);

        $this->post("/quotations/{$quote->id}/revise")->assertRedirect();
        $rev = Quotation::where('revision', 2)->first();
        $this->assertSame('revised', $quote->fresh()->status);
        $this->assertSame($quote->quotation_number, $rev->quotation_number);

        $this->patch("/quotations/{$rev->id}/status", ['status' => 'accepted']);
        $this->assertSame('active', $project->fresh()->status);
        $this->post("/quotations/{$rev->id}/convert")->assertRedirect();

        $invoice = Invoice::first();
        $this->assertEquals(94500, $invoice->total);
        $this->assertSame($invoice->id, $rev->fresh()->invoice_id);
    }

    public function test_payments_update_invoice_status(): void
    {
        $project = $this->project();
        $this->post('/invoices', [
            'project_id' => $project->id, 'issue_date' => today()->toDateString(), 'due_date' => today()->addDays(10)->toDateString(),
            'discount_type' => 'amount', 'items' => [['description' => 'Advance', 'quantity' => 1, 'rate' => 200000]], 'action' => 'send',
        ]);
        $invoice = Invoice::first();
        $this->assertSame('sent', $invoice->status);

        $this->post("/invoices/{$invoice->id}/payments", ['payment_date' => today()->toDateString(), 'amount' => 50000, 'method' => 'cash']);
        $this->assertSame('partially_paid', $invoice->fresh()->status);
        $this->assertEquals(150000, $invoice->fresh()->balance_due);

        $this->post("/invoices/{$invoice->id}/payments", ['payment_date' => today()->toDateString(), 'amount' => 150000, 'method' => 'bank_transfer']);
        $this->assertSame('paid', $invoice->fresh()->status);

        $this->post("/invoices/{$invoice->id}/payments", ['payment_date' => today()->toDateString(), 'amount' => 1, 'method' => 'cash'])->assertSessionHasErrors('amount');
    }

    public function test_document_log_creates_follow_up_and_stores_files(): void
    {
        Storage::fake('public');
        $project = $this->project();
        $this->post('/document-logs', [
            'project_id' => $project->id, 'document_type' => 'quotation', 'reference_no' => 'QT-0142', 'sent_date' => today()->toDateString(),
            'sent_to_name' => 'Imran', 'sent_to_email' => 'imran@example.com', 'last_discussion' => 'Wants local tiles',
            'email_subject' => 'Revised quote', 'email_body' => 'Please find attached', 'next_follow_up_date' => today()->addDays(3)->toDateString(),
            'status' => 'sent', 'files' => [UploadedFile::fake()->create('quote.pdf', 120, 'application/pdf')],
        ])->assertRedirect();

        $log = DocumentLog::first();
        $this->assertCount(1, $log->attachments);
        Storage::disk('public')->assertExists($log->attachments->first()->path);
        $this->assertSame(today()->addDays(3)->toDateString(), $log->followUp->due_date->toDateString());
        $this->get("/projects/{$project->id}")->assertSee('Logged')->assertSee('Wants local tiles');
    }

    public function test_site_visit_return_date_and_rotation_suggestion(): void
    {
        $a = Employee::create(['employee_code' => 'EMP-001', 'full_name' => 'Ahmed Raza', 'employment_type' => 'permanent', 'status' => 'active', 'is_site_staff' => true, 'rotation_order' => 1]);
        $b = Employee::create(['employee_code' => 'EMP-002', 'full_name' => 'Bilal Khan', 'employment_type' => 'permanent', 'status' => 'active', 'is_site_staff' => true, 'rotation_order' => 2]);

        $this->get('/rotation')->assertSee('Next in rotation: <strong>Ahmed Raza</strong>', false);

        $this->post('/rotation', ['employee_id' => $a->id, 'site_name' => 'DHA', 'departure_date' => today()->toDateString(), 'planned_days' => 7]);
        $visit = SiteVisit::first();
        $this->assertSame('on_site', $visit->status);
        $this->assertSame(today()->addDays(7)->toDateString(), $visit->expected_return_date->toDateString());
        $this->assertSame('on_site', $a->liveStatus());

        $this->get('/rotation')->assertSee('Next in rotation: <strong>Bilal Khan</strong>', false);

        $visit->update(['expected_return_date' => today()->addDay()]);
        $this->assertSame('returning_soon', $visit->fresh()->displayStatus());
        $visit->update(['expected_return_date' => today()->subDay()]);
        $this->assertSame('overdue', $visit->fresh()->displayStatus());

        $this->artisan('nr:daily-reminders')->assertSuccessful();
        $this->assertSame(1, $this->admin->notifications()->count());

        $this->patch("/rotation/{$visit->id}/return");
        $this->assertSame('returned', $visit->fresh()->status);
    }

    public function test_site_visit_can_be_edited_and_ended_early(): void
    {
        $emp = Employee::create(['employee_code' => 'EMP-001', 'full_name' => 'Ahmed Raza', 'employment_type' => 'permanent', 'status' => 'active', 'is_site_staff' => true]);
        $this->post('/rotation', ['employee_id' => $emp->id, 'site_name' => 'DHA', 'departure_date' => today()->addDays(3)->toDateString(), 'planned_days' => 7]);
        $visit = SiteVisit::first();
        $this->assertSame('scheduled', $visit->status);

        // Change the dates of the scheduled visit: leaving today makes it on site.
        $base = ['employee_id' => $emp->id, 'site_name' => 'DHA Phase 6', 'planned_days' => 5];
        $this->put("/rotation/{$visit->id}", $base + ['departure_date' => today()->subDays(2)->toDateString()])->assertSessionHasNoErrors();
        $visit->refresh();
        $this->assertSame('on_site', $visit->status);
        $this->assertSame('DHA Phase 6', $visit->site_name);
        $this->assertSame(today()->addDays(3)->toDateString(), $visit->expected_return_date->toDateString());

        // End it early, yesterday.
        $this->put("/rotation/{$visit->id}", $base + ['departure_date' => today()->subDays(2)->toDateString(), 'returned_on' => today()->subDay()->toDateString()])->assertSessionHasNoErrors();
        $visit->refresh();
        $this->assertSame('returned', $visit->status);
        $this->assertSame(today()->subDay()->toDateString(), $visit->actual_return_date->toDateString());

        // A return date before departure or in the future is refused.
        $this->put("/rotation/{$visit->id}", $base + ['departure_date' => today()->subDays(2)->toDateString(), 'returned_on' => today()->addDay()->toDateString()])->assertSessionHasErrors('returned_on');
        $this->get('/rotation')->assertOk()->assertSee('Edit site visit');
    }

    public function test_expense_by_staff_needs_approval(): void
    {
        $staff = User::create(['name' => 'Staff', 'email' => 's@nr.pk', 'password' => 'password', 'role' => 'staff']);
        $head = ExpenseHead::where('slug', 'daily-office-items')->first();
        $this->actingAs($staff)->post('/expenses', ['expense_head_id' => $head->id, 'expense_date' => today()->toDateString(), 'amount' => 1500, 'description' => 'Tea', 'payment_method' => 'cash']);
        $expense = $head->expenses()->first();
        $this->assertSame('pending', $expense->status);

        $this->actingAs($staff)->patch("/expenses/{$expense->id}/approve")->assertForbidden();
        $this->actingAs($this->admin)->patch("/expenses/{$expense->id}/approve");
        $this->assertSame('approved', $expense->fresh()->status);
    }

    public function test_salary_expense_accepts_month_input(): void
    {
        $emp = Employee::create(['employee_code' => 'EMP-001', 'full_name' => 'Ahmed', 'employment_type' => 'permanent', 'status' => 'active']);
        $head = ExpenseHead::where('slug', 'salary')->first();
        $this->post('/expenses', ['expense_head_id' => $head->id, 'expense_date' => today()->toDateString(), 'amount' => 95000, 'description' => 'Salary', 'payment_method' => 'bank_transfer', 'employee_id' => $emp->id, 'payroll_month' => '2026-09'])
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-09-01', $head->expenses()->first()->payroll_month->toDateString());
    }

    public function test_inventory_stock_movements(): void
    {
        $this->post('/inventory', ['type' => 'consumable', 'name' => 'A4 paper', 'unit' => 'ream', 'quantity' => 10, 'min_quantity' => 4, 'unit_cost' => 1450]);
        $item = InventoryItem::first();
        $this->post("/inventory/{$item->id}/transactions", ['type' => 'issue', 'quantity' => 7, 'transaction_date' => today()->toDateString()]);
        $this->assertEquals(3, $item->fresh()->quantity);
        $this->assertTrue($item->fresh()->isLowStock());
        $this->post("/inventory/{$item->id}/transactions", ['type' => 'adjustment', 'quantity' => 12, 'transaction_date' => today()->toDateString()]);
        $this->assertEquals(12, $item->fresh()->quantity);
        $this->assertCount(3, $item->transactions);
    }

    public function test_employee_cnic_is_validated(): void
    {
        $this->post('/employees', ['full_name' => 'X', 'cnic' => '12345', 'employment_type' => 'permanent', 'status' => 'active'])->assertSessionHasErrors('cnic');
        $this->post('/employees', ['full_name' => 'X', 'cnic' => '35202-1234567-1', 'employment_type' => 'permanent', 'status' => 'active', 'basic_salary' => 80000])->assertRedirect();
        $this->assertSame('EMP-001', Employee::first()->employee_code);
        $this->assertCount(1, Employee::first()->salaries);
    }

    public function test_settings_are_saved(): void
    {
        $this->put('/settings/rotation', ['rotation' => ['default_stay_days' => 10, 'remind_days_before' => 3, 'overdue_reminder_every_days' => 2, 'notify_roles' => ['admin'], 'count_weekends' => '0']])->assertSessionHasNoErrors();
        $this->assertSame('10', Setting::get('rotation.default_stay_days'));
        $this->assertSame('admin', Setting::get('rotation.notify_roles'));
        $this->assertSame('0', Setting::get('rotation.count_weekends'));
    }

    public function test_return_date_skips_weekends_when_they_are_not_counted(): void
    {
        $friday = \Illuminate\Support\Carbon::parse('2026-10-16');
        $this->assertSame('2026-10-18', \App\Models\SiteVisit::returnDateFor($friday, 2)->toDateString());

        Setting::put('rotation.count_weekends', '0');
        $this->assertSame('2026-10-20', \App\Models\SiteVisit::returnDateFor($friday, 2)->toDateString());
    }
}
