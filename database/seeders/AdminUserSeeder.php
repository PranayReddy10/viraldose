<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@viraldose.in');
        if (User::where('email', $email)->exists()) {
            return;
        }
        User::create([
            'name' => env('ADMIN_NAME', 'ViralDose Editor'),
            'email' => $email,
            'password' => env('ADMIN_PASSWORD', 'ChangeMe123!'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $this->command?->warn("Admin user created: {$email} — change the password after first login.");
    }
}
