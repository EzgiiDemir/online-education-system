<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A proper hierarchical entity (department/division/team, arbitrarily
 * nested via parent_id) alongside the existing free-text users.department
 * string -- department stays as-is for casual display, this is the
 * structured tree that reporting/compliance rollups (ReportController's
 * organization_unit filter) walk recursively.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('organization_units')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_units');
    }
};
