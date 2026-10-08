<?php

use App\Support\EnumConstraint;
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
        EnumConstraint::redefine('activities', 'type', ['video', 'document', 'presentation', 'live_session', 'resource', 'quiz', 'assignment']);

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

        EnumConstraint::redefine('activities', 'type', ['video', 'document', 'presentation', 'live_session', 'resource', 'quiz']);
    }
};
