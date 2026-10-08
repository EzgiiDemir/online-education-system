<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The certificate PDF carries a signature block. It used to be an untitled
 * pair of ruled lines in the template; the official ARUCAD certificate
 * names the signatory instead. Storing it here keeps a rector change an
 * admin-panel edit rather than a code edit and a redeploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_settings', function (Blueprint $table) {
            $table->string('certificate_signatory_name')->nullable();
            $table->string('certificate_signatory_title')->nullable();
            $table->string('certificate_issuer_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('portal_settings', function (Blueprint $table) {
            $table->dropColumn(['certificate_signatory_name', 'certificate_signatory_title', 'certificate_issuer_name']);
        });
    }
};
