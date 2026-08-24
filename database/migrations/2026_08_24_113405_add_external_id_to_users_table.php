<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The identity provider's own id for this user (SCIM's externalId
 * attribute) -- lets a SCIM client find/update "the same" user across
 * syncs by an id it controls, instead of only by email, which the IdP
 * might also be changing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('external_id')->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('external_id');
        });
    }
};
