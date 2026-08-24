<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-referencing many-to-many on courses: "Advanced X" can require
 * "Fundamentals of X" to be completed first. Deliberately no cycle
 * detection at the DB level -- Course::unmetPrerequisitesFor() only ever
 * walks one level deep (a course's direct prerequisites, not a transitive
 * chain), so a cycle would just mean two courses each blocking the other,
 * which is a content-authoring mistake for an admin to notice and fix, not
 * a state the system needs to prevent from being saved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_prerequisites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prerequisite_course_id')->constrained('courses')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['course_id', 'prerequisite_course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_prerequisites');
    }
};
