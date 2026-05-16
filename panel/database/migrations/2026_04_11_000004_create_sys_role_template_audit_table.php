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
        Schema::create('sys_role_template_audit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_template_id')->constrained('sys_role_templates')->onDelete('cascade');
            $table->enum('action', ['created', 'updated', 'deleted']); // Action type
            $table->json('old_data')->nullable(); // Previous state
            $table->json('new_data')->nullable(); // New state
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->index('role_template_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sys_role_template_audit');
    }
};
