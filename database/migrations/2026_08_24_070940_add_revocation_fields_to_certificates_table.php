<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Before this, the only way to invalidate an issued certificate was to
 * delete the row -- which destroys the record that it was ever issued at
 * all, exactly the kind of record a compliance audit needs to keep (see
 * §14 of the architecture review: certificates/audit-adjacent rows should
 * be revoked, not hard-deleted). revoked_by is nullable + nullOnDelete
 * like every other "who did this" admin-reference column in this app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->timestamp('revoked_at')->nullable()->after('issued_at');
            $table->string('revoked_reason')->nullable()->after('revoked_at');
            $table->foreignId('revoked_by')->nullable()->after('revoked_reason')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropColumn(['revoked_at', 'revoked_reason']);
        });
    }
};
