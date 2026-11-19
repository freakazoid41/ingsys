<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * sys_con_ops main_id index — the document list search resolves searchable
 * EAV text with `exists (select 1 from sys_con_ops where main_id = d.id ...)`.
 * The only existing indexes are the (main_id, type_id) partial index
 * (conn_id = 0) and (conn_id, main_id), neither of which can drive a
 * main_id-only probe for item-level conns.
 */
class AddSysConOpsMainIdIndex extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS idx_sco_main ON sys_con_ops (main_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_sco_main');
    }
}
