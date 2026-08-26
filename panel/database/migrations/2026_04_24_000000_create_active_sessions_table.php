<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('active_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('token_id')->nullable()->index();
            $table->string('session_id')->nullable()->index();
            $table->string('ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->text('current_status')->nullable();
            $table->string('permission_version')->nullable()->index();
            $table->timestamp('last_seen')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('active_sessions');
    }
};
