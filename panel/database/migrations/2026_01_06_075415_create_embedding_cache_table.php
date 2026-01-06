<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('embedding_cache', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key')->unique();
            $table->text('text_content');
            $table->json('embedding');
            $table->timestamps();

            $table->index('cache_key');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('embedding_cache');
    }
};
