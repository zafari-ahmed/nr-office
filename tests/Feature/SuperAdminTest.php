<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Impersonation;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Super admin only: other Super admins, salaries, changing an employee's password, "Log in as" in one tab, branding.
 */
class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private User $manager;

    private User $accounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('role', 'admin')->first();
        $this->staff = User::where('role', 'staff')->first();
        $this->manager = User::where('role', 'manager')->first();
        $this->accounts = User::where('role', 'accounts')->first();
    }

    /** A fresh browser tab with nobody signed in (the seeder leaves the admin in the test session). */
    private function asGuest(): void
    {
        $this->flushSession();
        $this->flushHeaders();
        $this->defaultCookies = [];
        $this->actingAsGuest();
    }

    /** Start "Log in as" for the staff member and return the key. */
    private function startImpersonation(): string
    {
        $response = $this->actingAs($this->admin)->post(route('employees.impersonate', $this->staff->employee_id));
        $impersonation = Impersonation::latest('id')->firstOrFail();
        $response->assertRedirect(route('dashboard', ['_as' => $impersonation->key]))
            ->assertCookie(Impersonation::cookieName($impersonation->key), $impersonation->key);
        $this->asGuest();

        return $impersonation->key;
    }

    public function test_only_super_admins_see_super_admin_accounts(): void
    {
        $other = User::create(['name' => 'Second Owner', 'email' => 'owner2@example.com', 'password' => 'password', 'role' => 'admin', 'is_active' => true]);
        Setting::put('access.manager', implode(',', [...\App\Support\Access::DEFAULTS['manager'], 'users.manage']));

        $this->actingAs($this->admin)->get('/settings/users')->assertOk()->assertSee('owner2@example.com')->assertSee('Super admin');
        $this->asGuest();
        $this->actingAs($this->manager)->get('/settings/users')->assertOk()->assertDontSee('owner2@example.com')->assertDontSee($this->admin->email)->assertSee($this->staff->email);
        $this->get(route('settings.users.edit', $other))->assertForbidden();
    }

    public function test_salary_is_only_for_super_admins(): void
    {
        $employee = $this->staff->employee_id;
        // Nobody but a Super admin, even a role that was given the old right.
        Setting::put('access.accounts', implode(',', [...\App\Support\Access::DEFAULTS['accounts'], 'employees.salary']));
        Setting::put('access.manager', implode(',', [...\App\Support\Access::DEFAULTS['manager'], 'employees.salary']));

        foreach ([$this->accounts, $this->manager] as $user) {
            $this->actingAs($user)->get("/employees/{$employee}")->assertOk()->assertDontSee('Salary history');
            $this->get("/employees/{$employee}?tab=salary")->assertOk()->assertDontSee('Salary revisions');
            $this->post("/employees/{$employee}/salaries", ['effective_from' => today()->toDateString(), 'basic_salary' => 1])->assertForbidden();
            $this->asGuest();
        }
        // Not even their own salary.
        $this->actingAs($this->staff)->get("/employees/{$employee}?tab=salary")->assertOk()->assertDontSee('Salary revisions');
        $this->get('/')->assertOk()->assertDontSee(number_format((float) $this->staff->employee->basic_salary, 0, '.', ''));
        $this->asGuest();

        // A manager adding an employee can't set the salary.
        $this->actingAs($this->manager)->get('/employees/create')->assertOk()->assertDontSee('Basic salary');
        $this->post('/employees', ['full_name' => 'New Hire', 'employment_type' => 'permanent', 'status' => 'active', 'basic_salary' => 50000])->assertRedirect();
        $hire = \App\Models\Employee::where('full_name', 'New Hire')->first();
        $this->assertEquals(0, (float) $hire->basic_salary);
        $this->assertSame(0, $hire->salaries()->count());
        $this->asGuest();

        // The Super admin sees the tab with amounts hidden behind the eye, and can add a revision.
        $page = $this->actingAs($this->admin)->get("/employees/{$employee}?tab=salary")->assertOk();
        $page->assertSee('Salary revisions')->assertSee('Show amounts')->assertSee('x-data="{ show: false }"', false)->assertSee('Add salary revision');
        $this->post("/employees/{$employee}/salaries", ['effective_from' => today()->toDateString(), 'basic_salary' => 120000, 'allowances' => 0, 'deductions' => 0, 'reason' => 'Increment'])->assertRedirect();
        $this->assertEquals(120000, (float) $this->staff->employee->fresh()->basic_salary);
        $this->get('/settings/access')->assertOk()->assertSee('Super admin only')->assertDontSee('See and change salaries');
    }

    public function test_super_admin_deletes_a_salary_revision(): void
    {
        $employee = $this->staff->employee;
        $employee->salaries()->delete(); // the seeded joining salary has a random date
        $old = $employee->salaries()->create(['effective_from' => today()->subYear(), 'basic_salary' => 80000, 'allowances' => 0, 'deductions' => 0, 'net_salary' => 80000]);
        $new = $employee->salaries()->create(['effective_from' => today()->subMonth(), 'basic_salary' => 100000, 'allowances' => 0, 'deductions' => 0, 'net_salary' => 100000]);
        $employee->update(['basic_salary' => 100000]);

        Setting::put('access.accounts', implode(',', [...\App\Support\Access::DEFAULTS['accounts'], 'employees.salary']));
        $this->actingAs($this->accounts)->delete(route('salaries.destroy', $new))->assertForbidden();
        $this->asGuest();

        $this->actingAs($this->admin)->get("/employees/{$employee->id}?tab=salary")->assertSee(route('salaries.destroy', $new), false);
        $this->delete(route('salaries.destroy', $new))->assertRedirect(route('employees.show', [$employee, 'tab' => 'salary']));
        $this->assertModelMissing($new);
        // Current salary falls back to the revision still in effect.
        $this->assertEquals(80000, (float) $employee->fresh()->basic_salary);
        $this->assertDatabaseHas('activity_logs', ['action' => 'salary_revision_deleted', 'user_id' => $this->admin->id]);
        $this->assertModelExists($old);
    }

    public function test_super_admin_changes_an_employee_password(): void
    {
        $page = $this->actingAs($this->admin)->get("/employees/{$this->staff->employee_id}")->assertOk();
        $page->assertSee('App login')->assertSee('Change password')->assertSee('Log in as');

        $this->put(route('employees.password', $this->staff->employee_id), ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->put(route('employees.password', $this->staff->employee_id), ['password' => 'new-secret-9', 'password_confirmation' => 'new-secret-9'])
            ->assertRedirect(route('employees.show', $this->staff->employee_id));
        $this->assertTrue(Hash::check('new-secret-9', $this->staff->fresh()->password));
        $this->assertDatabaseHas('activity_logs', ['action' => 'password_changed', 'user_id' => $this->admin->id]);

        $this->asGuest();
        $this->actingAs($this->manager)->get("/employees/{$this->staff->employee_id}")->assertOk()->assertDontSee('Log in as');
        $this->put(route('employees.password', $this->staff->employee_id), ['password' => 'x-secret-99', 'password_confirmation' => 'x-secret-99'])->assertForbidden();
        $this->post(route('employees.impersonate', $this->staff->employee_id))->assertForbidden();
    }

    public function test_log_in_as_runs_only_in_the_tab_that_carries_the_key(): void
    {
        $key = $this->startImpersonation();
        $this->assertDatabaseHas('activity_logs', ['action' => 'impersonation_started', 'user_id' => $this->admin->id]);
        $cookie = [Impersonation::cookieName($key) => $key];

        // The tab: runs as the staff member, with its own session cookie (the admin's session is never written).
        $tab = $this->withCookies($cookie)->get('/?_as='.$key)->assertOk()->assertSee('Logged in as')->assertSee($this->staff->name);
        $tab->assertCookie(Impersonation::sessionCookieName($key));
        $tab->assertCookieMissing(config('session.cookie'));
        $this->assertSame($this->staff->id, auth()->id());
        // Staff can't open the directory; the redirect stays in the tab.
        $this->withCookies($cookie)->get('/employees?_as='.$key)->assertRedirect('/me?_as='.$key);
        // A page opened from the tab without the key (found from the page it came from) gets the key put back.
        $this->withCookies($cookie)->withHeader('Referer', url('/projects?_as='.$key))->get('/expenses')->assertRedirect(url('/expenses').'?_as='.$key);

        // Anything done in the tab records the real Super admin.
        $this->withCookies($cookie)->post('/projects/'.\App\Models\Project::first()->id.'/notes?_as='.$key, ['body' => 'Checked from admin'])->assertRedirect();

        // Another tab (no key): still the admin.
        $this->asGuest();
        $this->actingAs($this->admin)->get('/settings/users')->assertOk()->assertDontSee('Logged in as');
    }

    public function test_a_copied_link_does_not_work_in_another_browser(): void
    {
        $key = $this->startImpersonation();
        $this->get('/?_as='.$key)->assertRedirect(route('impersonation.ended', ['reason' => 'other_browser']));
        $this->assertNull(auth()->user());
        $this->assertNull(Impersonation::where('key', $key)->value('ended_at'), 'The real tab keeps working.');
    }

    public function test_ending_the_tab_is_logged_and_the_key_stops_working(): void
    {
        $key = $this->startImpersonation();
        $cookie = [Impersonation::cookieName($key) => $key];
        $this->withCookies($cookie)->post('/impersonation/end?_as='.$key)->assertRedirect(route('impersonation.ended', ['reason' => 'left']));
        $impersonation = Impersonation::where('key', $key)->first();
        $this->assertNotNull($impersonation->ended_at);
        $this->assertSame('ended', $impersonation->end_reason);
        $this->assertTrue(ActivityLog::where('action', 'impersonation_ended')->exists());

        $this->asGuest();
        $this->withCookies($cookie)->get('/?_as='.$key)->assertRedirect(route('impersonation.ended', ['reason' => 'ended']));
        $this->get(route('impersonation.ended', ['reason' => 'left']))->assertOk()->assertSee('You have left their account');
    }

    public function test_signing_out_inside_the_tab_ends_it_without_signing_out_the_admin(): void
    {
        $key = $this->startImpersonation();
        $cookie = [Impersonation::cookieName($key) => $key];
        $this->withCookies($cookie)->post('/logout?_as='.$key)->assertStatus(307)->assertRedirect(route('impersonation.end', ['_as' => $key]));
    }

    public function test_idle_tabs_and_admin_sign_out_end_impersonation(): void
    {
        $key = $this->startImpersonation();
        Impersonation::where('key', $key)->update(['last_seen_at' => now()->subMinutes(Impersonation::IDLE_MINUTES + 1)]);
        $this->withCookies([Impersonation::cookieName($key) => $key])->get('/?_as='.$key)->assertRedirect(route('impersonation.ended', ['reason' => 'expired']));
        $this->assertSame('expired', Impersonation::where('key', $key)->value('end_reason'));

        $this->asGuest();
        $second = $this->startImpersonation();
        $this->actingAs($this->admin)->post('/logout');
        $this->assertSame('signed_out', Impersonation::where('key', $second)->value('end_reason'));
    }

    public function test_cannot_log_in_as_another_super_admin_or_yourself(): void
    {
        $other = User::create(['name' => 'Second Owner', 'email' => 'owner2@example.com', 'password' => 'password', 'role' => 'admin', 'is_active' => true]);
        $employee = \App\Models\Employee::create(['employee_code' => 'EMP-X', 'full_name' => 'Second Owner', 'employment_type' => 'permanent', 'status' => 'active', 'basic_salary' => 0]);
        $other->update(['employee_id' => $employee->id]);

        $this->actingAs($this->admin)->post(route('employees.impersonate', $employee))->assertForbidden();
        $this->get("/employees/{$employee->id}")->assertOk()->assertDontSee('Log in as');
        $this->assertSame(0, Impersonation::count());
    }

    public function test_super_admin_uploads_branding(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin)->get('/settings/branding')->assertOk()->assertSee('Sidebar icon')->assertSee('Favicon');
        $this->post('/settings/branding', ['logo' => UploadedFile::fake()->image('logo.png', 400, 120)])->assertRedirect();
        $path = Setting::get('brand.logo');
        Storage::disk('public')->assertExists($path);
        $this->get(route('branding.show', 'logo'))->assertOk();
        $this->post('/settings/branding', ['icon' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertSessionHasErrors('icon');

        $this->asGuest();
        $this->get('/login')->assertOk()->assertSee(route('branding.show', 'logo'), false);

        $this->actingAs($this->admin)->delete(route('settings.branding.destroy', 'logo'))->assertRedirect();
        Storage::disk('public')->assertMissing($path);

        $this->asGuest();
        $this->actingAs($this->manager)->get('/settings/branding')->assertForbidden();
        $this->post('/settings/branding', ['logo' => UploadedFile::fake()->image('logo.png')])->assertForbidden();
    }
}
