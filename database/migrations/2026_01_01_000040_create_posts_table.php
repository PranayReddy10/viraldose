<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->string('excerpt', 500)->nullable();
            $table->longText('content')->nullable();
            $table->string('image')->nullable();
            $table->string('image_alt', 200)->nullable();
            $table->string('image_caption', 300)->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_breaking')->default(false);
            $table->boolean('is_slider')->default(false);
            $table->boolean('is_recommended')->default(false);
            $table->boolean('allow_comments')->default(true);
            $table->string('meta_title', 160)->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('meta_keywords', 255)->nullable();
            $table->string('canonical_url')->nullable();
            $table->boolean('noindex')->default(false);
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedSmallInteger('reading_time')->default(1);
            $table->string('source_name', 120)->nullable();
            $table->string('source_url')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['category_id', 'status', 'published_at']);
            $table->index(['is_featured', 'status', 'published_at']);
            $table->index(['is_slider', 'status', 'published_at']);
            $table->index(['is_breaking', 'status', 'published_at']);
            $table->index('views');
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['post_id', 'tag_id']);
        });

        Schema::create('post_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->date('viewed_on');
            $table->unsignedInteger('count')->default(0);
            $table->unique(['post_id', 'viewed_on']);
            $table->index('viewed_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_views');
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('posts');
    }
};
