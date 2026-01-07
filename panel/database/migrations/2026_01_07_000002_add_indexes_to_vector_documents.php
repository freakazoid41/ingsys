<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddIndexesToVectorDocuments extends Migration
{
    public function up()
    {
        // index on metadata->>'source' for faster source lookups
        DB::statement("CREATE INDEX IF NOT EXISTS idx_vector_documents_source ON vector_documents ((metadata->>'source'))");
        // GIN index for metadata JSONB
        DB::statement("CREATE INDEX IF NOT EXISTS idx_vector_documents_metadata_gin ON vector_documents USING gin (metadata)");
    }

    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS idx_vector_documents_source");
        DB::statement("DROP INDEX IF EXISTS idx_vector_documents_metadata_gin");
    }
}
