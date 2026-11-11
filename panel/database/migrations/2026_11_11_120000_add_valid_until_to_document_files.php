<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supplier document library: each uploaded version can carry its own validity.
     * Expired files stay viewable but block tender submission until replaced.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('document_files', 'valid_until')) {
            Schema::table('document_files', function (Blueprint $table) {
                $table->date('valid_until')->nullable()->after('replaced_id');
                $table->index(['status', 'valid_until']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('document_files', 'valid_until')) {
            Schema::table('document_files', function (Blueprint $table) {
                $table->dropIndex(['status', 'valid_until']);
                $table->dropColumn('valid_until');
            });
        }
    }
};
