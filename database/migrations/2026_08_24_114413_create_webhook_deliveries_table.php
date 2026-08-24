<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per delivery attempt (not per event) -- a retried delivery adds
 * a new row rather than mutating the old one, so the admin-visible history
 * shows exactly what was tried, when, and what the receiving end said back,
 * across every attempt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_subscription_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->json('payload');
            $table->unsignedInteger('attempt');
            $table->unsignedInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->boolean('successful')->default(false);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
