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

            $table->index(['conn_id','main_id'],'sys_con_ops_1');
            // Hot audit/order snapshot — see 2026_09_04_000005_add_audit_indexes
            $table->index(['main_id','type_id'], 'idx_sco_main_conn');
        });
        \Illuminate\Support\Facades\DB::statement('CREATE INDEX IF NOT EXISTS idx_sco_main_conn_partial ON sys_con_ops (main_id, type_id) WHERE conn_id = 0');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sys_con_ops');
    }
};
