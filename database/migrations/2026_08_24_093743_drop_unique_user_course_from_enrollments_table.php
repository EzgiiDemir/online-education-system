<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recertification (P1 "recurring training"): a completed enrollment whose
 * certificate has expired (Enrollment::isStale()) needs to be retakeable,
 * which means a second Enrollment row for the same (user_id, course_id)
 * pair -- a new "cycle" -- without destroying the first one's completion
 * evidence. This DB-level constraint physically forbade that. The
 * "at most one *active* cycle at a time" invariant that used to be this
 * constraint's job is now enforced in application code instead (see
 * EnrollmentService::enroll() and Enrollment::isStale()/scopeCurrentCycle()),
 * the same way max_attempts/waitlist capacity/etc. are already enforced
 * at the app layer rather than the schema layer elsewhere in this app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->unique(['user_id', 'course_id']);
        });
    }
};
