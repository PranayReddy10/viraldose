<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Production-safe seed: admin user, categories, pages and settings.
     * Run `php artisan db:seed --class=DemoContentSeeder` for sample posts.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            CategorySeeder::class,
            PageSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
