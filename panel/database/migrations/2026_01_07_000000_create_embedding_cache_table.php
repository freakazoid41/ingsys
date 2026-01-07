<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmbeddingCacheTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('embedding_cache')) {
            Schema::create('embedding_cache', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('cache_key')->unique();
                $table->text('text_content')->nullable();
                $table->json('embedding');
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('embedding_cache');
    }
}
