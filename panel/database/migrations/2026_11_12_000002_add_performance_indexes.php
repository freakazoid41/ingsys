<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Performance indexes found by profiling the notification feed.
 *
 * The bell endpoint used to spend 3-4s per call: sys_options was seq-scanned
 * ~6.5M times (no op_key index) and the per-type document queries sorted the
 * whole documents set by id with only (type_id) indexed.
 */
return new class extends Migration
{
    public function up(): void
    {
        // op_key / group_key lookups happen on nearly every request.
        DB::statement('CREATE INDEX IF NOT EXISTS idx_sys_options_op_key ON sys_options (op_key)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_sys_options_group_key ON sys_options (group_key)');

        // "latest N of a document type" is the core notification/list query.
        DB::statement('CREATE INDEX IF NOT EXISTS idx_documents_type_id_desc ON documents (type_id, id DESC)');

        // Latest-transaction lookups per document.
        DB::statement('CREATE INDEX IF NOT EXISTS idx_trans_target_id_desc ON transactions (target_id, id DESC)');

        // Read-state filtering per user/category.
        DB::statement('CREATE INDEX IF NOT EXISTS idx_notification_reads_user_op ON notification_reads (user_id, op_key)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_notification_reads_user_op');
        DB::statement('DROP INDEX IF EXISTS idx_trans_target_id_desc');
        DB::statement('DROP INDEX IF EXISTS idx_documents_type_id_desc');
        DB::statement('DROP INDEX IF EXISTS idx_sys_options_group_key');
        DB::statement('DROP INDEX IF EXISTS idx_sys_options_op_key');
    }
};
