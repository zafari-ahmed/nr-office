<?php

namespace Tests\Feature;

use App\Models\DocumentLog;
use App\Models\Employee;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every screen renders for an admin against the sample data.
 */
class PagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('role', 'admin')->first());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        auth()->logout();
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Welcome back');
    }

    public function test_all_pages_render(): void
    {
        $project = Project::first();
        $urls = [
            '/', '/projects', '/projects?view=board', '/projects/create', "/projects/{$project->id}", "/projects/{$project->id}/edit",
            '/clients', '/clients/create',
            '/quotations', '/quotations?status=sent', '/quotations/create', '/quotations/'.Quotation::first()->id, '/quotations/'.Quotation::first()->id.'/print',
            '/invoices', '/invoices/create', '/invoices/'.Invoice::first()->id, '/invoices/'.Invoice::first()->id.'/print',
            '/document-logs', '/document-logs/'.DocumentLog::first()->id,
            '/expenses', '/expenses?month='.now()->subMonth()->format('Y-m'),
            '/inventory', '/inventory?tab=consumable', '/inventory/'.InventoryItem::first()->id,
            '/employees', '/employees/create', '/employees/'.Employee::first()->id,
            '/rotation', '/notifications', '/search?q=gul',
            '/settings/company', '/settings/rotation', '/settings/numbering', '/settings/finance', '/settings/expense-heads',
            '/settings/users', '/settings/users/create',
        ];
        // Every project page, including ones whose only history is a logged document.
        foreach (Project::pluck('id') as $id) {
            $urls[] = "/projects/{$id}";
        }
        foreach (array_keys(\App\Http\Controllers\ReportController::REPORTS) as $report) {
            $urls[] = "/reports?type={$report}";
            $urls[] = "/reports?type={$report}&period=custom&from=2020-01-01&to=2030-12-31";
        }
        $urls[] = '/reports?type=pnl&period=this_month';
        $urls[] = '/settings/notifications';
        $urls[] = '/rotation?schedule=1';
        $urls[] = '/invoices/'.Invoice::first()->id.'?pay=1';
        foreach (['overview', 'follow-ups', 'quotations', 'invoices', 'expenses', 'site-visits', 'files', 'notes'] as $tab) {
            $urls[] = "/projects/{$project->id}?tab={$tab}";
        }
        foreach (['profile', 'salary', 'visits', 'assets', 'documents'] as $tab) {
            $urls[] = '/employees/'.Employee::first()->id."?tab={$tab}";
        }

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_reports_export_csv(): void
    {
        $csv = $this->get('/reports/export?type=projects')->assertOk()->streamedContent();
        $this->assertStringStartsWith('Code,Project,Client', $csv);
        $this->assertStringContainsString(Project::first()->code, $csv);
    }

    public function test_staff_cannot_open_reports(): void
    {
        $this->actingAs(User::where('role', 'staff')->first());
        $this->get('/reports')->assertForbidden();
        $this->get('/')->assertDontSee('Reports');
    }

    public function test_search_returns_json(): void
    {
        $this->getJson('/search?q=Gulberg')->assertOk()->assertJsonFragment(['group' => 'Projects']);
    }

    public function test_staff_cannot_open_settings(): void
    {
        $this->actingAs(User::where('role', 'staff')->first());
        $this->get('/settings/company')->assertForbidden();
    }
}
