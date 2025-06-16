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
        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->boolean('status')->default(true);
            $table->smallInteger('type_id')->default('0');
            $table->smallInteger('email_approved')->default('0');
            $table->smallInteger('approved')->default('0');

            $table->integer('parent_id')->default('0');
            

            $table->string('spec_code',150)->nullable()->default('-');
            $table->string('title', 50)->nullable()->default('-');
            $table->string('name', 50);
            $table->string('surname',50)->nullable()->default('-');
            $table->string('phone', 50)->default('-');
            $table->string('address', 250)->nullable()->default('-');


            $table->float('balance', 15, 3)->default('0');

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
        Schema::dropIfExists('persons');
    }
};
