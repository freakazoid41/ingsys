<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('active_sessions', function (Blueprint $table) {
            $table->boolean('force_logout')->default(false)->after('permission_version')->index();
            $table->text('force_logout_reason')->nullable()->after('force_logout');
            $table->timestamp('force_logout_at')->nullable()->after('force_logout_reason')->index();
        });
    }

    public function down(): void
    {
        Schema::table('active_sessions', function (Blueprint $table) {
            $table->dropColumn(['force_logout', 'force_logout_reason', 'force_logout_at']);
        });
    }
};
