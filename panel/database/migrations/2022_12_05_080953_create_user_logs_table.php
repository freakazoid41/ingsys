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
            $table->integer('sys_code')->default('0');
            $table->integer('user_id');
            $table->integer('type_id');
            $table->integer('relation_id')->default('0');
            $table->string('relation',1000)->default('-');;
            $table->text('description');
            $table->string('grp_code',100)->nullable()->default('op-fclt-1');
            $table->timestamps();

            
            $table->index(['user_id'],'logindex_1');
            $table->index(['relation_id'],'logindex_2');
            $table->index(['relation'],'logindex_4');
            $table->index(['type_id'],'logindex_3');
            $table->index(['relation_id','relation'],'logindex_5');
        });

        Schema::create('cron_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('is_working')->default('0');
            $table->text('title')->default('-');
            $table->text('last_log')->default('-');
            $table->timestamp('ended_at')->useCurrent();
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
        Schema::dropIfExists('user_logs');
    }
};
