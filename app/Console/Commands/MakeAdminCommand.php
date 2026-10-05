<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdminCommand extends Command
{
    protected $signature = 'make:admin {email} {--name=Admin} {--password=}';

    protected $description = 'Create an admin user, or reset the password of an existing one, for the admin panel';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->option('password') ?: $this->secret('Password (min 8 chars)');
        if (strlen((string) $password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }
        $existing = User::where('email', $email)->first();
        $user = User::updateOrCreate(
            ['email' => $email],
            array_filter(['name' => $existing ? null : $this->option('name'), 'password' => $password, 'role' => User::ROLE_ADMIN, 'is_active' => true], fn ($v) => $v !== null),
        );
        $this->info($existing ? "Password reset for {$user->email} (role: admin, active)." : "Admin created: {$user->email}");
        $this->line('Log in at '.rtrim((string) config('app.url'), '/').'/admin');

        return self::SUCCESS;
    }
}
