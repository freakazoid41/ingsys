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
        Schema::create('sys_con_entities', function (Blueprint $table) {
            $table->id();
            $table->integer('conn_id');
            $table->string('table_tag',100);
            $table->string('entity_tag',100);
            $table->text('entity_value');
            $table->text('qnid')->nullable();
            
            $table->timestamps();

            // Hot EAV lookups — see 2026_09_04_000005_add_audit_indexes
            $table->index(['conn_id', 'entity_tag'], 'idx_sce_conn_tag');
            $table->index(['table_tag', 'entity_value'], 'idx_sce_table_value');
            $table->index('entity_tag', 'idx_sce_entity_tag_like');
        });
        // text_pattern_ops for LIKE 'prefix%' (audit isMultiFile single path)
        \Illuminate\Support\Facades\DB::statement('CREATE INDEX IF NOT EXISTS idx_sce_entity_tag_like_pattern ON sys_con_entities (entity_tag text_pattern_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sys_con_entities');
    }
};
