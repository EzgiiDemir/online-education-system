<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An assignment activity with no rows here has no rubric -- grading stays
 * the existing single 0-100 score field, unchanged. Rubric grading is
 * purely additive: it's just a different, structured way of arriving at
 * that same score, not a parallel scoring system.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubric_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('max_points');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rubric_criteria');
    }
};
