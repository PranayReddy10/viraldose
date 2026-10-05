<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public const DEFAULT_PASSWORD = 'ChangeMe123!';

    public function run(): void
    {
        $email = trim((string) env('ADMIN_EMAIL')) ?: 'admin@viraldose.in';
        if (User::where('email', $email)->exists()) {
            return;
        }

        // A blank ADMIN_PASSWORD in .env must not become an empty password.
        $password = trim((string) env('ADMIN_PASSWORD'));
        $generated = false;
        if (strlen($password) < 8) {
            $password = app()->isProduction() ? Str::password(14, symbols: false) : self::DEFAULT_PASSWORD;
            $generated = app()->isProduction();
        }

        User::create([
            'name' => trim((string) env('ADMIN_NAME')) ?: 'ViralDose Editor',
            'email' => $email,
            'password' => $password,
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->command?->newLine();
        $this->command?->warn('============================================================');
        $this->command?->warn(" Admin login created: {$email}");
        $this->command?->warn(' Password: '.$password.($generated ? '  (generated – ADMIN_PASSWORD was empty)' : ''));
        $this->command?->warn(' Change it after first login (My profile), or run:');
        $this->command?->warn("   php artisan make:admin {$email} --password='NewStrongPassword'");
        $this->command?->warn('============================================================');
    }
}
