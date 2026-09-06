<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_reads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('op_key', 20);        // tedarik-01..07
            $table->string('target_qnid', 36);   // order or file qnid
            $table->timestamp('read_at');
            $table->unique(['user_id', 'op_key', 'target_qnid']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_reads');
    }
};
