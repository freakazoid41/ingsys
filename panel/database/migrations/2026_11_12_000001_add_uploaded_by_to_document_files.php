<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('document_files', 'uploaded_by')) {
            Schema::table('document_files', function (Blueprint $table) {
                $table->unsignedBigInteger('uploaded_by')->default(0)->after('status');
                $table->index('uploaded_by');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('document_files', 'uploaded_by')) {
            Schema::table('document_files', function (Blueprint $table) {
                $table->dropIndex(['uploaded_by']);
                $table->dropColumn('uploaded_by');
            });
        }
    }
};
