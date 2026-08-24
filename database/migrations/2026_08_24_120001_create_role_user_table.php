<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'role_id']);
        });

        // Backfill: every existing user keeps exactly the one role they
        // already had, so single-role behaviour (routing, tests) is
        // unaffected until an admin deliberately grants a second role.
        $roleIds = DB::table('roles')->pluck('id', 'key');
        $now = now();
        DB::table('users')->select('id', 'role')->orderBy('id')->chunkById(200, function ($users) use ($roleIds, $now) {
            $rows = [];
            foreach ($users as $user) {
                if (isset($roleIds[$user->role])) {
                    $rows[] = ['user_id' => $user->id, 'role_id' => $roleIds[$user->role], 'created_at' => $now, 'updated_at' => $now];
                }
            }
            if ($rows) {
                DB::table('role_user')->insertOrIgnore($rows);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};
