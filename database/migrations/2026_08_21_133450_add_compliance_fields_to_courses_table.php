<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->enum('category', ['required', 'optional', 'compliance', 'onboarding', 'professional_development'])->default('optional')->after('status');
            $table->unsignedInteger('due_days')->nullable()->after('category')->comment('Days after enrollment by which the course must be completed');
            $table->unsignedInteger('recertification_period_days')->nullable()->after('due_days')->comment('If set, a certificate for this course expires this many days after issue and must be redone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['category', 'due_days', 'recertification_period_days']);
        });
    }
};
