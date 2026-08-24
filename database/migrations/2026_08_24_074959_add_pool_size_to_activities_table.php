<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Question-bank quizzes: an instructor can author more questions than any
 * one attempt needs to answer (e.g. 30 questions in the bank, 10 drawn per
 * attempt), so learners retaking a failed quiz don't just see the exact
 * same test. Null (the default) means "use every question", which is the
 * existing, unchanged behavior -- this is purely additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedInteger('pool_size')->nullable()->after('max_attempts')->comment('For quiz activities: how many questions to randomly draw per attempt. Null = use every question.');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('pool_size');
        });
    }
};
