<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('image', 1000)->nullable()->change();
            $table->string('source_url', 1000)->nullable()->change();
            $table->string('canonical_url', 500)->nullable()->change();
            $table->string('video_url', 1000)->nullable()->change();
            $table->string('audio_url', 1000)->nullable()->change();
            $table->string('image_fetch_error', 255)->nullable()->after('image_caption');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('image_fetch_error');
        });
    }
};
