<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_progress', function (Blueprint $table) {
            $table->timestamp('last_heartbeat_at')->nullable()->after('watched_seconds');
        });
        Schema::table('activity_progress', function (Blueprint $table) {
            $table->timestamp('last_heartbeat_at')->nullable()->after('watched_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('material_progress', function (Blueprint $table) {
            $table->dropColumn('last_heartbeat_at');
        });
        Schema::table('activity_progress', function (Blueprint $table) {
            $table->dropColumn('last_heartbeat_at');
        });
    }
};
