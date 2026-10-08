<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('portal_settings')) {
            return;
        }

        DB::table('portal_settings')
            ->where('portal_name', 'Arucad Sürekli Öğrenme Merkezi')
            ->update(['portal_name' => 'Arucad Eğitim Merkezi']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('portal_settings')) {
            return;
        }

        DB::table('portal_settings')
            ->where('portal_name', 'Arucad Eğitim Merkezi')
            ->update(['portal_name' => 'Arucad Sürekli Öğrenme Merkezi']);
    }
};
