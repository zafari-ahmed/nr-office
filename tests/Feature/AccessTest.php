<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Expense;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Roles and access rights: what Staff can see, the Access rights screen and the account menu.
 */
class AccessTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->staff = User::where('role', 'staff')->first();
    }

    public function test_staff_only_sees_their_own_profile(): void
    {
        $this->actingAs($this->staff);
        $this->get('/employees')->assertRedirect('/me');
        $this->get('/me')->assertRedirect('/employees/'.$this->staff->employee_id);
        $this->get('/employees/'.$this->staff->employee_id)->assertOk()->assertSee('Edit profile')->assertDontSee('Salary history');
        $other = Employee::whereKeyNot($this->staff->employee_id)->first();
        $this->get('/employees/'.$other->id)->assertForbidden();
        $this->get('/')->assertOk()->assertDontSee('href="'.route('employees.index').'"', false);
    }

    public function test_staff_only_sees_projects_they_are_on_the_team_of(): void
    {
        $mine = Project::whereHas('members', fn ($q) => $q->whereKey($this->staff->employee_id))->get();
        $other = Project::whereDoesntHave('members', fn ($q) => $q->whereKey($this->staff->employee_id))->where('manager_id', '!=', $this->staff->id)->first();
        $this->assertNotEmpty($mine);

        $this->actingAs($this->staff);
        $page = $this->get('/projects')->assertOk();
        $mine->each(fn ($p) => $page->assertSee($p->name));
        $page->assertDontSee($other->name);
        $this->get('/projects/'.$mine->first()->id)->assertOk()->assertDontSee('New quotation');
        $this->get('/projects/'.$other->id)->assertForbidden();
        $this->post('/projects/'.$other->id.'/notes', ['body' => 'x'])->assertForbidden();
        $this->get('/projects/create')->assertForbidden();
    }

    public function test_staff_only_sees_their_own_expenses(): void
    {
        $others = Expense::where('created_by', '!=', $this->staff->id)->where(fn ($q) => $q->whereNull('employee_id')->orWhere('employee_id', '!=', $this->staff->employee_id))->first();
        $this->actingAs($this->staff);
        $this->post('/expenses', ['expense_head_id' => \App\Models\ExpenseHead::where('slug', '!=', 'salary')->value('id'), 'expense_date' => today()->toDateString(), 'amount' => 1234, 'description' => 'Taxi to site', 'payment_method' => 'cash'])->assertSessionHasNoErrors();
        $mine = Expense::where('description', 'Taxi to site')->first();
        $this->assertSame('pending', $mine->status);

        $this->get('/expenses')->assertOk()->assertSee('My expenses')->assertSee('Taxi to site')->assertDontSee($others->description);
        $this->delete('/expenses/'.$others->id)->assertForbidden();
        $this->delete('/expenses/'.$mine->id)->assertRedirect();
    }

    public function test_staff_is_kept_out_of_other_modules(): void
    {
        $this->actingAs($this->staff);
        foreach (['/quotations', '/invoices', '/inventory', '/rotation', '/reports', '/settings', '/settings/company', '/settings/access', '/clients'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->getJson('/search?q=a')->assertOk()->assertJsonMissing(['group' => 'Invoices'])->assertJsonMissing(['group' => 'Employees']);
    }

    public function test_admin_can_change_access_rights(): void
    {
        $this->actingAs(User::where('role', 'admin')->first());
        $this->get('/settings/access')->assertOk()->assertSee('See site rotation');
        $this->put('/settings/access', ['access' => ['staff' => ['rotation.view'], 'manager' => ['projects.view_all'], 'accounts' => []]])->assertSessionHasNoErrors();
        $this->assertSame('rotation.view', Setting::get('access.staff'));

        $this->actingAs($this->staff)->get('/rotation')->assertOk()->assertDontSee('Schedule visit');
        $this->actingAs(User::where('role', 'manager')->first())->get('/invoices')->assertForbidden();

        $this->actingAs(User::where('role', 'admin')->first())->put('/settings/access', ['reset' => 1]);
        $this->actingAs(User::where('role', 'manager')->first())->get('/invoices')->assertOk();
        $this->get('/settings/access')->assertForbidden();
    }

    public function test_only_admin_can_make_admins(): void
    {
        Setting::put('access.manager', 'users.manage,settings.manage');
        $this->actingAs(User::where('role', 'manager')->first());
        $this->post('/settings/users', ['name' => 'X', 'email' => 'x@example.com', 'role' => 'admin', 'password' => 'password123', 'is_active' => 1])->assertSessionHasErrors('role');
        $this->get('/settings/users/'.User::where('role', 'admin')->first()->id.'/edit')->assertForbidden();
    }

    public function test_account_menu_profile_and_password(): void
    {
        $this->actingAs($this->staff);
        $this->get('/')->assertSee('My profile')->assertSee('Change password')->assertSee('Log out');
        $this->get('/account')->assertOk()->assertSee('Edit profile');

        $this->put('/account', ['name' => 'Ahmed R.', 'email' => $this->staff->email, 'phone' => '0300-1111111', 'address' => 'Model Town'])->assertSessionHasNoErrors();
        $this->assertSame('Ahmed R.', $this->staff->fresh()->name);
        $this->assertSame('Model Town', $this->staff->employee->fresh()->address);

        $this->put('/account/password', ['current_password' => 'wrong', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertSessionHasErrors('current_password');
        $this->put('/account/password', ['current_password' => 'password', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('newpass123', $this->staff->fresh()->password));
    }

    public function test_project_team_is_saved_from_the_form(): void
    {
        $this->actingAs(User::where('role', 'admin')->first());
        $project = Project::first();
        $ids = Employee::take(2)->pluck('id')->all();
        $this->put('/projects/'.$project->id, ['name' => $project->name, 'status' => $project->status, 'team' => $ids])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing($ids, $project->members()->pluck('employees.id')->all());
    }
}
