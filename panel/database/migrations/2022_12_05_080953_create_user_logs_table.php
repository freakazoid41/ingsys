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
        Schema::create('user_logs', function (Blueprint $table) {
            $table->id();
            $table->text('sys_code')->default('-');
            $table->integer('user_id');
            $table->integer('type_id');
            $table->integer('relation_id')->default('0');
            $table->text('relation')->default('-');;
            $table->text('description');
            $table->timestamps();
        });

        /*DB::statement("alter table user_logs 
                            add target_id varchar generated always as (description::json ->'request'->>'target_id') stored");
        DB::statement("alter table user_logs 
                            add ref_id varchar generated always as (description::json ->'request'->>'ref_id') stored");*/

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_logs');
    }
};
