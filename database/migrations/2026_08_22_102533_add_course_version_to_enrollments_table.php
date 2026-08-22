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
        Schema::table('enrollments', function (Blueprint $table) {
            $table->unsignedInteger('course_version')->nullable()->after('course_id')
                ->comment('Snapshot of courses.version at the moment of enrollment. Never updated afterwards, even if the course is edited or a new version is published -- this is what "completion is tied to a version" means here.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('course_version');
        });
    }
};
