<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An external system's registered endpoint for outbound event notifications
 * (enrollment.created, enrollment.completed, certificate.issued,
 * certificate.revoked -- see WebhookDispatcher). `secret` is stored in
 * plaintext (unlike ScimToken/ApiToken's hashed credentials) because it has
 * to be read back out to sign every outgoing delivery -- it's not
 * presented back to us, we present it to the subscriber.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->string('secret');
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_subscriptions');
    }
};
