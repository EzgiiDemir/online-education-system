<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Some required/compliance training genuinely doesn't apply to a specific
 * person (role change, already certified elsewhere, medical exemption) --
 * before this the only way to stop the due-date/overdue machinery from
 * chasing them was to fabricate a completion, which would have falsely
 * issued a certificate for training they never took.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->timestamp('exempted_at')->nullable()->after('completed_at');
            $table->string('exemption_reason')->nullable()->after('exempted_at');
            $table->foreignId('exempted_by')->nullable()->after('exemption_reason')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exempted_by');
            $table->dropColumn(['exempted_at', 'exemption_reason']);
        });
    }
};
