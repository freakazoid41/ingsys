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
        Schema::create('sys_con_ops', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('status')->default('1');
            $table->integer('main_id');
            $table->integer('conn_id');
            $table->integer('type_id');
            $table->integer('sub_type_id')->default(0);
            $table->string('description',300)->default('-');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sys_con_ops');
    }
};
