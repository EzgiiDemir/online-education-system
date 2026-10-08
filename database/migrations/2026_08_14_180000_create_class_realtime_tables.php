<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_presence', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_session_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamp('last_seen_at');
            $t->timestamps();
            $t->unique(['course_session_id', 'user_id']);
        });
        Schema::create('class_signals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_session_id')->constrained()->cascadeOnDelete();
            $t->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('type');
            $t->jsonb('payload');
            $t->boolean('consumed')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_signals');
        Schema::dropIfExists('class_presence');
    }
};
