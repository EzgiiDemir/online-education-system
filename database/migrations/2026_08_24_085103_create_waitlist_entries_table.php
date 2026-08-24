<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deliberately a separate table rather than an Enrollment with a
 * "waitlisted" status: an Enrollment implies course access and feeds
 * completion/overdue/certificate logic read in a dozen places across the
 * app, none of which should ever see someone who hasn't actually gotten a
 * seat yet. A waitlisted person simply has no Enrollment row until
 * CourseRun::promoteNextFromWaitlist() creates one for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['course_run_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
