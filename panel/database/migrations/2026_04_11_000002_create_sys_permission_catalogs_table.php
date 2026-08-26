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
        Schema::create('sys_permission_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g., "per-04-02"
            $table->string('title'); // Permission title in Turkish
            $table->string('category')->nullable(); // e.g., "Roles", "Users"
            $table->string('subcategory')->nullable(); // e.g., "Create", "Edit", "Delete"
            $table->json('metadata')->nullable(); // Store additional permission metadata
            $table->timestamps();
            $table->index('code');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sys_permission_catalogs');
    }
};
