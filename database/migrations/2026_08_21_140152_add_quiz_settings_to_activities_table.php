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
        EnumConstraint::redefine('activities', 'type', ['video', 'document', 'presentation', 'live_session', 'resource', 'quiz']);

        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedInteger('pass_score')->nullable()->after('duration_seconds')->comment('Minimum percentage required to pass this quiz. Null = no minimum.');
            $table->unsignedInteger('max_attempts')->nullable()->after('pass_score')->comment('Maximum quiz attempts allowed. Null = unlimited.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['pass_score', 'max_attempts']);
        });

        EnumConstraint::redefine('activities', 'type', ['video', 'document', 'presentation', 'live_session', 'resource']);
    }
};
