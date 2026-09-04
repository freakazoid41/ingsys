<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('op_id')->default('0');
            $table->smallInteger('status')->default('1');
            $table->integer('trans_id')->default('0');
            $table->integer('type_id');
            $table->integer('target_id');
            $table->integer('rel_id')->default(0);
            $table->integer('cur_id')->default(0);
            $table->integer('sign')->default(0);
            $table->integer('log_id')->default(0);
            $table->decimal('amount')->default(0);
            $table->string('period',7)->default('-');
            $table->string('description',300)->default('-');
            $table->string('note',300)->default('-');
            $table->string('grp_code',100)->nullable()->default('-');
            $table->text('qnid')->nullable();
            
            $table->timestamps();

            $table->index(['grp_code','period','target_id'],'transindex_1');
            $table->index(['target_id'],'transindex_2');
            // Hot audit — file vs order status — see 2026_09_04_000005_add_audit_indexes
            $table->index(['target_id', 'op_id'], 'idx_trans_target_op');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};
