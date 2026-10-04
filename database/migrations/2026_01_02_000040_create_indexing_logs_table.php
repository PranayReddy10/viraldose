<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indexing_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->string('url', 500);
            $table->string('provider', 30);   // google_indexing | indexnow | search_console
            $table->string('action', 30);     // URL_UPDATED | URL_DELETED | inspect | sitemap
            $table->string('status', 10);     // ok | error
            $table->text('response')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['post_id', 'created_at']);
            $table->index(['provider', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indexing_logs');
    }
};
