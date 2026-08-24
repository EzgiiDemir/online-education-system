<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Null (the default) means unlimited seats -- every existing run keeps
 * enrolling exactly as before. Only a run with an explicit max_seats value
 * can ever fill up and start sending people to waitlist_entries instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_runs', function (Blueprint $table) {
            $table->unsignedInteger('max_seats')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('course_runs', function (Blueprint $table) {
            $table->dropColumn('max_seats');
        });
    }
};
