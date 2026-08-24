<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uploaded live-class recordings had no retention policy at all -- once
 * stored they sat on disk forever. This column, set at upload time to
 * CourseSession::RECORDING_RETENTION_DAYS out, is what the new
 * app:purge-expired-recordings command checks against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_sessions', function (Blueprint $table) {
            $table->timestamp('recording_expires_at')->nullable()->after('recording_path');
        });
    }

    public function down(): void
    {
        Schema::table('course_sessions', function (Blueprint $table) {
            $table->dropColumn('recording_expires_at');
        });
    }
};
