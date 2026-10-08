<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The authoritative 0-100 `score` column is untouched and still what every
 * pass/fail check in the app reads -- this JSON map (criterion_id =>
 * points awarded) is only the breakdown behind that number, kept for
 * transparency to the learner and the grading instructor. Null when the
 * submission was graded without a rubric (the common case today).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->jsonb('rubric_scores')->nullable()->after('score');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->dropColumn('rubric_scores');
        });
    }
};
