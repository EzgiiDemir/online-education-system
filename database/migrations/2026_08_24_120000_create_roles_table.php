<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive multi-role support: users.role stays as the "primary" role (every
 * existing role === 'x' check across the app keeps working unchanged), this
 * table plus role_user lets a person hold more than one role at once
 * (e.g. Learner + Instructor + Manager) for the workspace switcher.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->timestamps();
        });

        DB::table('roles')->insert([
            ['key' => 'learner', 'name' => 'Öğrenci', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'instructor', 'name' => 'Eğitmen', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'manager', 'name' => 'Yönetici (Manager)', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'admin', 'name' => 'Sistem Yöneticisi', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
