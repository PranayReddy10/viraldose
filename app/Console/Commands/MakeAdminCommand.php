<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdminCommand extends Command
{
    protected $signature = 'make:admin {email} {--name=Admin} {--password=}';

    protected $description = 'Create an admin user (or promote an existing one) for the admin panel';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->option('password') ?: $this->secret('Password (min 8 chars)');
        if (strlen((string) $password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }
        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $this->option('name'), 'password' => $password, 'role' => User::ROLE_ADMIN, 'is_active' => true],
        );
        $this->info("Admin ready: {$user->email}");

        return self::SUCCESS;
    }
}
