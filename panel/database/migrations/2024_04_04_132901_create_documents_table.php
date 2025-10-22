<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       
        $db = DB::connection();

        if ($db->getDriverName() === 'sqlite') {
            $db->unprepared('PRAGMA cache_size = 0; ');
        }

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->integer('status')->default(1);
            $table->integer('parent_type_id')->default(0); // if document is old version then it will be 1
            $table->integer('parent_id')->default(0);
            $table->integer('type_id')->default(0);
            $table->integer('person_id')->default(0); // this guy is client
            
            $table->string('title',300)->default('-');
            $table->string('grp_code',100)->nullable()->default('op-fclt-1');
            $table->string('qnid')->nullable();

            $table->timestamp('starting_at')->nullable();
            $table->timestamp('ending_at')->nullable();
            
            $table->timestamps();
            $table->index(['grp_code'],'docindex_1');
            //example column index code
            //$table->index(['account_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
