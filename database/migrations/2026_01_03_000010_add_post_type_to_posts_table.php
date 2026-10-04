<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('post_type', 20)->default('article')->index()->after('language');
            $table->string('video_url', 500)->nullable()->after('image_caption');
            $table->string('audio_url', 500)->nullable()->after('video_url');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['post_type', 'video_url', 'audio_url']);
        });
    }
};
