<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
            $table->string('role', 20)->default('author')->index()->after('password');
            $table->string('avatar')->nullable()->after('role');
            $table->text('bio')->nullable()->after('avatar');
            $table->string('website')->nullable()->after('bio');
            $table->string('twitter')->nullable()->after('website');
            $table->boolean('is_active')->default(true)->after('twitter');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['slug', 'role', 'avatar', 'bio', 'website', 'twitter', 'is_active']);
        });
    }
};
