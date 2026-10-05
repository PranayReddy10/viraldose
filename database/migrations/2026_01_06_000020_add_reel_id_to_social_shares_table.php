<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_shares', function (Blueprint $table) {
            $table->unsignedBigInteger('post_id')->nullable()->change();
            $table->foreignId('reel_id')->nullable()->after('post_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('social_shares', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reel_id');
        });
    }
};
