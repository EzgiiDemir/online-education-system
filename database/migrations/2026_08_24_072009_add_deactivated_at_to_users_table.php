<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supports anonymization instead of hard delete on self-service account
 * deletion (AccountController::destroy()). enrollments.user_id and several
 * other FKs cascadeOnDelete -- a real $user->delete() would silently wipe
 * every enrollment, certificate, quiz attempt and assignment submission
 * that row was ever party to, which is exactly the compliance evidence
 * this whole app exists to keep. Anonymizing in place (name/email/etc.
 * scrubbed, this column set) keeps the row -- and everything that points
 * at it -- intact while making the account itself unusable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable()->after('manager_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deactivated_at');
        });
    }
};
