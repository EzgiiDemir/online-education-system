<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $t) {
            $t->foreignId('course_run_id')->nullable()->constrained('course_runs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', fn (Blueprint $t) => $t->dropConstrainedForeignId('course_run_id'));
    }
};
