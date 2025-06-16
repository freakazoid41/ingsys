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
            $table->timestamps();
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
