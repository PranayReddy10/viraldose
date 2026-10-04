<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('language', 10)->default('en')->index()->after('category_id');
            $table->string('index_status', 40)->nullable()->after('views');      // Google URL Inspection verdict
            $table->string('index_coverage', 120)->nullable()->after('index_status');
            $table->timestamp('index_checked_at')->nullable()->after('index_coverage');
            $table->timestamp('last_crawled_at')->nullable()->after('index_checked_at');
            $table->timestamp('indexing_requested_at')->nullable()->after('last_crawled_at');
            $table->unsignedBigInteger('rss_feed_id')->nullable()->index()->after('legacy_id');
            $table->string('feed_guid', 500)->nullable()->after('rss_feed_id');
        });
        Schema::table('posts', function (Blueprint $table) {
            $table->index(['rss_feed_id', 'feed_guid'], 'posts_feed_guid_index');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_feed_guid_index');
            $table->dropColumn(['language', 'index_status', 'index_coverage', 'index_checked_at', 'last_crawled_at', 'indexing_requested_at', 'rss_feed_id', 'feed_guid']);
        });
    }
};
