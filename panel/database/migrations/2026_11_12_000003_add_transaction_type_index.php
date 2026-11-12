<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Notification feeds resolve the latest status events from transactions before
 * loading documents. That lookup is type_id + newest-first.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS idx_trans_type_id_desc ON transactions (type_id, id DESC)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_trans_type_id_desc');
    }
};
