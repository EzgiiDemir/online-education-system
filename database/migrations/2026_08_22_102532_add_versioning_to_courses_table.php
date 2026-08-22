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
            $table->unsignedInteger('version')->default(1)->after('status');
            $table->foreignId('version_group_id')->nullable()->after('version')->constrained('courses')->nullOnDelete()
                ->comment('Points at the original (v1) course that this version was forked from. Null on the original itself.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('version_group_id');
            $table->dropColumn('version');
        });
    }
};
