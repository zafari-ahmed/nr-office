<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModulesTest extends TestCase
{
    use RefreshDatabase;

    private function modules(array $off = []): array
    {
        return ['module' => collect(['projects', 'quotations', 'invoices', 'expenses', 'inventory', 'employees', 'rotation', 'reports'])
            ->mapWithKeys(fn ($m) => [$m => in_array($m, $off, true) ? 0 : 1])->all()];
    }

    public function test_admin_can_switch_a_module_off_and_back_on(): void
    {
        $this->seed(SettingsSeeder::class);
        $admin = User::create(['name' => 'Zafar', 'email' => 'z@nr.pk', 'password' => 'password', 'role' => 'admin']);
        $this->actingAs($admin);

        $this->get('/settings/modules')->assertOk()->assertSee('Quotations');
        $this->put('/settings/modules', $this->modules(['quotations']))->assertRedirect();

        $this->get('/quotations')->assertNotFound();
        $this->get('/quotations/create')->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee(route('quotations.index').'"', false);
        $this->get('/invoices')->assertOk();

        $this->put('/settings/modules', $this->modules())->assertRedirect();
        $this->get('/quotations')->assertOk();
    }

    public function test_only_admin_sees_the_modules_settings(): void
    {
        $this->seed(SettingsSeeder::class);
        $manager = User::create(['name' => 'Kamran', 'email' => 'k@nr.pk', 'password' => 'password', 'role' => 'manager']);
        $this->actingAs($manager);

        $this->get('/settings/modules')->assertForbidden();
        $this->put('/settings/modules', $this->modules(['quotations']))->assertForbidden();
    }
}
