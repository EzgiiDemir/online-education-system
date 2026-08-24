<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bearer tokens for the SCIM 2.0 provisioning API (routes/api.php,
 * /scim/v2/*), issued to an identity provider (Okta, Azure AD, etc.) --
 * deliberately separate from normal user auth, since a SCIM client isn't a
 * logged-in person. Only the hash is stored, same as Laravel's own
 * personal-access-token pattern; the plaintext is shown once, at creation,
 * in the admin panel and never again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scim_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('token_hash')->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scim_tokens');
    }
};
