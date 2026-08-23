<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks every row created by DemoFakeDataSeeder so it can be removed
     * later with one clean `php artisan demo:clear` -- without this, telling
     * "fake showcase data" apart from "real data someone typed in" after the
     * fact would mean guessing by naming convention.
     */
    public function up(): void
    {
        Schema::create('demo_seed_records', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->string('model_id');
            $table->timestamp('created_at')->nullable();
            $table->unique(['model_type', 'model_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demo_seed_records');
    }
};
