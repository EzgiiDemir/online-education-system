<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $t) {
            $t->string('language', 5)->default('tr');
        });
        Schema::table('portal_settings', function (Blueprint $t) {
            $t->string('default_locale', 5)->default('tr');
            $t->json('available_locales')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('courses', fn (Blueprint $t) => $t->dropColumn('language'));
        Schema::table('portal_settings', fn (Blueprint $t) => $t->dropColumn(['default_locale', 'available_locales']));
    }
};
