<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A snapshot of a Material's file+title taken right before it gets
 * replaced, not after -- the row here is always the OLD content, while
 * materials.path/title always hold the current one. A learner who watched
 * or downloaded the old file (MaterialProgress is keyed on material_id,
 * which never changes) still has a historical reference to exactly what
 * they saw, the same "never destroy evidence" principle used for
 * enrollments/certificates elsewhere in this app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type');
            $table->string('path');
            $table->foreignId('replaced_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_versions');
    }
};
