<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
    }

    private function user(string $role): User
    {
        return User::create(['name' => ucfirst($role), 'email' => "$role@nr.pk", 'password' => 'password', 'role' => $role]);
    }

    public function test_admin_can_set_and_change_the_project_code(): void
    {
        $this->actingAs($this->user('admin'));

        $this->get('/projects/create')->assertOk()->assertSee('name="code"', false);
        $this->post('/projects', ['code' => 'NR-CUSTOM-1', 'name' => 'Gulberg', 'status' => 'lead'])->assertRedirect();
        $project = Project::firstWhere('name', 'Gulberg');
        $this->assertSame('NR-CUSTOM-1', $project->code);

        $this->put("/projects/{$project->id}", ['code' => 'NR-CUSTOM-2', 'name' => 'Gulberg', 'status' => 'lead'])->assertRedirect();
        $this->assertSame('NR-CUSTOM-2', $project->fresh()->code);

        // Saving with its own code again is fine.
        $this->put("/projects/{$project->id}", ['code' => 'NR-CUSTOM-2', 'name' => 'Gulberg', 'status' => 'active'])->assertSessionHasNoErrors();
    }

    public function test_a_code_already_in_use_is_rejected(): void
    {
        $this->actingAs($this->user('admin'));
        $this->post('/projects', ['code' => 'NR-1', 'name' => 'First', 'status' => 'lead']);
        $this->post('/projects', ['code' => 'NR-2', 'name' => 'Second', 'status' => 'lead']);
        $second = Project::firstWhere('name', 'Second');

        $this->post('/projects', ['code' => 'NR-1', 'name' => 'Third', 'status' => 'lead'])->assertSessionHasErrors('code');
        $this->put("/projects/{$second->id}", ['code' => 'NR-1', 'name' => 'Second', 'status' => 'lead'])->assertSessionHasErrors('code');
        $this->assertSame('NR-2', $second->fresh()->code);
    }

    public function test_other_roles_cannot_set_the_code(): void
    {
        $manager = $this->user('manager');
        \App\Models\Setting::put('access.manager', 'projects.manage,projects.view_all');
        $this->actingAs($manager);

        $this->get('/projects/create')->assertOk()->assertDontSee('name="code"', false);
        $this->post('/projects', ['code' => 'HACK-1', 'name' => 'Model Town', 'status' => 'lead'])->assertRedirect();
        $project = Project::firstWhere('name', 'Model Town');
        $this->assertNotSame('HACK-1', $project->code);

        $this->put("/projects/{$project->id}", ['code' => 'HACK-2', 'name' => 'Model Town', 'status' => 'lead']);
        $this->assertNotSame('HACK-2', $project->fresh()->code);
    }
}
