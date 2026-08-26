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
        Schema::table('materials', function (Blueprint $table) {
            $table->foreignId('uploaded_by_id')->nullable()->after('course_session_id')->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('grade_note')->nullable();
            $table->foreignId('graded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uploaded_by_id');
            $table->dropConstrainedForeignId('graded_by_id');
            $table->dropColumn(['score', 'grade_note', 'graded_at']);
        });
    }
};
