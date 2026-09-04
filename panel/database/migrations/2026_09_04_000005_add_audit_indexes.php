<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Hot EAV lookups: conn_id + entity_tag (getFormData, orderSnapshot, file lookups)
        // Using raw DB::statement for partial / conditional indexes where needed.
        if (! $this->indexExists('sys_con_entities', 'idx_sce_conn_tag')) {
            DB::statement('CREATE INDEX idx_sce_conn_tag ON sys_con_entities (conn_id, entity_tag)');
        }
        if (! $this->indexExists('sys_con_entities', 'idx_sce_table_value')) {
            DB::statement('CREATE INDEX idx_sce_table_value ON sys_con_entities (table_tag, entity_value)');
        }
        if (! $this->indexExists('sys_con_entities', 'idx_sce_entity_tag_like')) {
            // For LIKE 'prefix%' queries (isMultiFile single path) — btree with text_pattern_ops
            DB::statement('CREATE INDEX idx_sce_entity_tag_like ON sys_con_entities (entity_tag text_pattern_ops)');
        }
        if (! $this->indexExists('sys_con_ops', 'idx_sco_main_conn')) {
            DB::statement('CREATE INDEX idx_sco_main_conn ON sys_con_ops (main_id, type_id) WHERE conn_id = 0');
        }
        if (! $this->indexExists('document_files', 'idx_df_relation_status')) {
            DB::statement('CREATE INDEX idx_df_relation_status ON document_files (relation_id, status) WHERE relation = \'documents\'');
        }
        if (! $this->indexExists('document_files', 'idx_df_qnid')) {
            // qnid already unique but ensure index exists for /order-file lookups
            $has = DB::selectOne("SELECT 1 FROM pg_indexes WHERE tablename='document_files' AND indexname='document_files_qnid_unique'");
            if (!$has) {
                DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS document_files_qnid_unique ON document_files (qnid)');
            }
        }
        if (! $this->indexExists('transactions', 'idx_trans_target_op')) {
            DB::statement('CREATE INDEX idx_trans_target_op ON transactions (target_id, op_id)');
        }
        if (! $this->indexExists('sys_options', 'idx_sys_op_key')) {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS idx_sys_op_key ON sys_options (op_key)');
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_sce_conn_tag');
        DB::statement('DROP INDEX IF EXISTS idx_sce_table_value');
        DB::statement('DROP INDEX IF EXISTS idx_sce_entity_tag_like');
        DB::statement('DROP INDEX IF EXISTS idx_sco_main_conn');
        DB::statement('DROP INDEX IF EXISTS idx_df_relation_status');
        DB::statement('DROP INDEX IF EXISTS idx_trans_target_op');
        // keep idx_sys_op_key and document_files_qnid_unique — they are useful even after rollback
    }

    private function indexExists(string $table, string $index): bool
    {
        $row = DB::selectOne("SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?", [$table, $index]);
        return (bool) $row;
    }
};
