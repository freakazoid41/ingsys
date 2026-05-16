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
        Schema::create('sys_role_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., "Admin", "Reseller"
            $table->json('permissions')->nullable(); // JSON array of permission codes
            $table->string('description')->nullable();
            $table->string('op_key')->unique();
            $table->boolean('immutable')->default(false); // Role templates are immutable
            $table->timestamps();
            $table->index('op_key');
            $table->index('name');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sys_role_templates');
    }
};
