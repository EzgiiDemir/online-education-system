<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every other progress table (material_progress, activity_progress,
 * enrollments) already has a unique constraint on its "one row per learner
 * per thing" pair -- session_attendance was missing its equivalent. Without
 * it, two near-simultaneous first-ping requests (e.g. two browser tabs
 * joining the same live class at once) could each pass a "does a row
 * exist?" check before either had committed, creating two attendance rows
 * for the same user+session and silently splitting their accrued duration
 * across both -- CompletionEngine::accrueSessionAttendance() now also
 * locks and retries around this constraint (see that class for the
 * concurrency-safe read-then-write logic this backstops).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_attendance', function (Blueprint $table) {
            $table->unique(['course_session_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('session_attendance', function (Blueprint $table) {
            $table->dropUnique(['course_session_id', 'user_id']);
        });
    }
};
