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
        Schema::create('document_files', function (Blueprint $table) {
            $table->id();

            $table->smallInteger('status')->default('1');
            $table->integer('type_id');
            $table->integer('replaced_id')->default('0');
            $table->integer('conn_id')->default('0'); //for nothing for now..
            $table->integer('relation_id')->default('0'); //for report purpouses
            
            $table->string('relation','20')->default('-');
            $table->string('title','250')->default('-');
            $table->text('description')->default('-');

            $table->timestamp('selected_at')->useCurrent();
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
        Schema::dropIfExists('document_files');
    }
};
