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
        Schema::create('sys_con_entities', function (Blueprint $table) {
            $table->id();
            $table->integer('conn_id');
            $table->string('table_tag',100);
            $table->string('entity_tag',100);
            $table->text('entity_value');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sys_con_entities');
    }
};
