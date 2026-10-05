<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rss_feeds', function (Blueprint $table) {
            $table->boolean('fetch_full_content')->default(true)->after('import_images');
        });
        Schema::table('posts', function (Blueprint $table) {
            $table->timestamp('content_fetched_at')->nullable()->after('feed_guid');
        });
    }

    public function down(): void
    {
        Schema::table('rss_feeds', function (Blueprint $table) {
            $table->dropColumn('fetch_full_content');
        });
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('content_fetched_at');
        });
    }
};
