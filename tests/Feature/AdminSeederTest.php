<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        unset($_SERVER['ADMIN_PASSWORD'], $_ENV['ADMIN_PASSWORD'], $_SERVER['ADMIN_EMAIL'], $_ENV['ADMIN_EMAIL']);
        parent::tearDown();
    }

    public function test_blank_admin_password_falls_back_to_the_documented_default(): void
    {
        $_SERVER['ADMIN_PASSWORD'] = $_ENV['ADMIN_PASSWORD'] = '';

        $this->seed(AdminUserSeeder::class);

        $admin = User::where('email', 'admin@viraldose.in')->firstOrFail();
        $this->assertTrue(Hash::check(AdminUserSeeder::DEFAULT_PASSWORD, $admin->password));
        $this->assertFalse(Hash::check('', $admin->password));
        $this->post('/admin/login', ['email' => 'admin@viraldose.in', 'password' => AdminUserSeeder::DEFAULT_PASSWORD])->assertRedirect('/admin');
    }

    public function test_configured_admin_password_is_used(): void
    {
        $_SERVER['ADMIN_PASSWORD'] = $_ENV['ADMIN_PASSWORD'] = 'MySecret!2026';
        $_SERVER['ADMIN_EMAIL'] = $_ENV['ADMIN_EMAIL'] = 'boss@viraldose.in';

        $this->seed(AdminUserSeeder::class);

        $this->post('/admin/login', ['email' => 'boss@viraldose.in', 'password' => 'MySecret!2026'])->assertRedirect('/admin');
    }

    public function test_make_admin_command_resets_password_and_creates_admins(): void
    {
        $user = User::factory()->create(['email' => 'editor@viraldose.in', 'role' => 'author', 'is_active' => false]);

        $this->artisan('make:admin', ['email' => 'editor@viraldose.in', '--password' => 'Reset12345'])->assertSuccessful();
        $user->refresh();
        $this->assertTrue(Hash::check('Reset12345', $user->password));
        $this->assertSame('admin', $user->role);
        $this->assertTrue($user->is_active);

        $this->artisan('make:admin', ['email' => 'new@viraldose.in', '--password' => 'short'])->assertFailed();
        $this->artisan('make:admin', ['email' => 'new@viraldose.in', '--name' => 'New Admin', '--password' => 'LongEnough1'])->assertSuccessful();
        $this->assertDatabaseHas('users', ['email' => 'new@viraldose.in', 'role' => 'admin', 'name' => 'New Admin']);
    }
}
