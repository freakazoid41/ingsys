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
            $table->string('grp_code',100)->nullable()->default('-');
            $table->text('qnid')->nullable();

            $table->timestamp('selected_at')->useCurrent();
            $table->timestamps();

            // Hot file lookups — see 2026_09_04_000005_add_audit_indexes
            $table->index(['relation_id', 'status'], 'idx_df_relation_status');
            $table->unique('qnid', 'document_files_qnid_unique');
        });
        \Illuminate\Support\Facades\DB::statement("CREATE INDEX IF NOT EXISTS idx_df_relation_status_partial ON document_files (relation_id, status) WHERE relation = 'documents'");
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
