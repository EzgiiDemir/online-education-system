<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('code')->unique();
            $t->string('language', 5)->default('tr');
            $t->dateTime('enrollment_opens_at')->nullable();
            $t->dateTime('enrollment_closes_at')->nullable();
            $t->dateTime('starts_at')->nullable();
            $t->dateTime('ends_at')->nullable();
            $t->enum('status', ['draft', 'open', 'running', 'completed', 'archived'])->default('draft');
            $t->timestamps();
        });
        Schema::create('course_sections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->text('description')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });
        Schema::create('activities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_section_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->enum('type', ['video', 'document', 'presentation', 'live_session', 'resource']);
            $t->text('description')->nullable();
            $t->string('path')->nullable();
            $t->unsignedInteger('duration_seconds')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_required')->default(true);
            $t->timestamps();
        });
        Schema::create('session_attendance', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_session_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamp('joined_at');
            $t->timestamp('left_at')->nullable();
            $t->unsignedInteger('duration_seconds')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_attendance');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('course_sections');
        Schema::dropIfExists('course_runs');
    }
};
