<?php

use App\Support\EnumConstraint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('manager_id')->nullable()->after('role')->constrained('users')->nullOnDelete();
        });

        EnumConstraint::redefine('users', 'role', ['learner', 'instructor', 'manager', 'admin'], 'learner');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        EnumConstraint::redefine('users', 'role', ['learner', 'instructor', 'admin'], 'learner');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
        });
    }
};
