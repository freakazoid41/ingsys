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

        Schema::create('vector_documents', function (Blueprint $table) {
            $table->id();
            $table->text('content');
            $table->text('origin');
            $table->text('year_code');
            $table->jsonb('metadata');
        });

       
        $embedDim = env('LOCAL_EMBED_DIM', 768);
        DB::statement("CREATE EXTENSION IF NOT EXISTS vector");
        DB::statement("ALTER TABLE vector_documents ADD COLUMN embedding VECTOR({$embedDim})");
        // index on metadata->>'source' for faster source lookups
        DB::statement("CREATE INDEX IF NOT EXISTS idx_vector_documents_source ON vector_documents ((metadata->>'source'))");
        // GIN index for metadata JSONB
        DB::statement("CREATE INDEX IF NOT EXISTS idx_vector_documents_metadata_gin ON vector_documents USING gin (metadata)");


        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vector_documents');
    }
};