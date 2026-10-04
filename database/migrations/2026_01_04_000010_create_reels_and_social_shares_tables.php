<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->string('source_type', 20);          // upload | url | youtube | instagram
            $table->string('video_path', 500)->nullable(); // stored reference (upload) or direct URL
            $table->string('external_url', 500)->nullable(); // original YouTube / Instagram URL
            $table->string('external_id', 100)->nullable();  // YouTube video id / Instagram shortcode
            $table->string('thumbnail', 500)->nullable();
            $table->string('caption', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'published_at']);
        });

        Schema::create('social_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('network', 20);               // instagram
            $table->string('media_type', 20)->default('image'); // image | reel
            $table->string('status', 20);                // published | processing | failed | manual
            $table->string('creation_id', 100)->nullable();
            $table->string('external_id', 100)->nullable();
            $table->string('permalink', 500)->nullable();
            $table->string('image', 500)->nullable();
            $table->text('caption')->nullable();
            $table->text('response')->nullable();
            $table->timestamps();

            $table->index(['post_id', 'network']);
            $table->index('status');
        });

        Schema::table('ads', function (Blueprint $table) {
            $table->string('device', 10)->default('all')->after('slot');   // all | mobile | desktop
            $table->string('pages', 20)->default('all')->after('device');  // all | home | post | category | reels
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->dropColumn(['device', 'pages']);
        });
        Schema::dropIfExists('social_shares');
        Schema::dropIfExists('reels');
    }
};
