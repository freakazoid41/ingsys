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
        Schema::create('sys_notification_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g., "notif-00", "notif-01"
            $table->string('title'); // Notification type title
            $table->text('description')->nullable(); // Description of notification type
            $table->string('category')->nullable(); // Category of notification
            $table->json('metadata')->nullable(); // Additional notification metadata
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
        Schema::dropIfExists('sys_notification_types');
    }
};
