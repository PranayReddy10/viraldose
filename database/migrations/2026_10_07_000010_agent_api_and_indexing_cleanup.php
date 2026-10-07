<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Who created the post: null = admin panel, "agent" = the content API, "feed" = RSS import.
            $table->string('created_via', 20)->nullable()->after('rss_feed_id')->index();
        });
        DB::table('posts')->whereNotNull('rss_feed_id')->update(['created_via' => 'feed']);

        // Copied RSS articles are the main reason pages end up "Crawled - currently not indexed".
        // Stop importing; feeds can be re-enabled by hand (they will then only create drafts).
        DB::table('rss_feeds')->update(['is_active' => false, 'auto_publish' => false]);

        // Google's Indexing API only supports JobPosting / BroadcastEvent pages; pinging it for news does nothing.
        DB::table('settings')->updateOrInsert(['key' => 'google_auto_index'], ['value' => '0', 'updated_at' => now(), 'created_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['created_via']);
            $table->dropColumn('created_via');
        });
    }
};
