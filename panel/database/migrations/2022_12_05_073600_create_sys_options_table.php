<?php

use App\Models\Sys_options;
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
        Schema::create('sys_options', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('status')->default('1');
            $table->integer('parent_id')->default('0');
            $table->string('title',150);
            $table->string('code',150)->default('-');
            $table->string('ttitle',150);
            $table->string('ctitle',150);
            $table->string('op_key',150)->default('-');
            $table->string('group_key',150)->default('-');
            $table->string('description',250)->default('-');
            $table->string('icon',50)->default('-');
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
        Schema::dropIfExists('sys_options');
    }
};
