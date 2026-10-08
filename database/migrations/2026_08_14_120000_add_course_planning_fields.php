<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('guest_instructor_name')->nullable();
            $table->string('guest_instructor_email')->nullable();
            $table->text('guest_instructor_bio')->nullable();
            $table->boolean('certificate_enabled')->default(true);
            $table->jsonb('sdg_goals')->nullable();
            $table->string('poster_path')->nullable();
            $table->string('image_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['guest_instructor_name', 'guest_instructor_email', 'guest_instructor_bio', 'certificate_enabled', 'sdg_goals', 'poster_path', 'image_path']);
        });
    }
};
