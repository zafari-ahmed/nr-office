<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attrs = []): User
    {
        return User::create(['name' => 'Zafar', 'username' => 'Zafar.Admin', 'email' => 'zafar@example.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true, ...$attrs]);
    }

    public function test_signs_in_with_email(): void
    {
        $this->user();
        $this->post('/login', ['login' => 'zafar@example.com', 'password' => 'secret123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_signs_in_with_username_in_any_case(): void
    {
        $user = $this->user();
        $this->assertSame('zafar.admin', $user->username);
        $this->post('/login', ['login' => ' ZAFAR.admin ', 'password' => 'secret123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_or_inactive_user_is_refused(): void
    {
        $this->user(['is_active' => false]);
        $this->post('/login', ['login' => 'zafar.admin', 'password' => 'secret123'])->assertSessionHasErrors('login');
        $this->post('/login', ['login' => 'nobody', 'password' => 'x'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_admin_sets_a_username_and_it_must_be_unique(): void
    {
        $admin = $this->user();
        $this->actingAs($admin)->post(route('settings.users.store'), [
            'name' => 'Ali', 'username' => 'ali', 'email' => 'ali@example.com', 'role' => 'staff', 'password' => 'password1', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame('ali', User::where('email', 'ali@example.com')->value('username'));

        $this->actingAs($admin)->post(route('settings.users.store'), [
            'name' => 'Ali 2', 'username' => 'ALI', 'email' => 'ali2@example.com', 'role' => 'staff', 'password' => 'password1',
        ])->assertSessionHasErrors('username');
        $this->actingAs($admin)->post(route('settings.users.store'), [
            'name' => 'Bad', 'username' => 'bad@name', 'email' => 'bad@example.com', 'role' => 'staff', 'password' => 'password1',
        ])->assertSessionHasErrors('username');
    }
}
