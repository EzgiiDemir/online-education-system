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
        Schema::table('activities', function (Blueprint $table) {
            $table->enum('type', ['video', 'document', 'presentation', 'live_session', 'resource', 'quiz', 'assignment'])->change();
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->enum('submission_type', ['text', 'file', 'url'])->nullable()->after('max_attempts')->comment('Only used for assignment-type activities.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('submission_type');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->enum('type', ['video', 'document', 'presentation', 'live_session', 'resource', 'quiz'])->change();
        });
    }
};
