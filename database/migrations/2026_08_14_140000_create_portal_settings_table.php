<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_settings', function (Blueprint $t) {
            $t->id();
            $t->string('portal_name')->default('Arucad Sürekli Öğrenme Merkezi');
            $t->string('logo_path')->nullable();
            $t->json('accepted_domains')->nullable();
            $t->boolean('entra_enabled')->default(false);
            $t->string('entra_tenant_id')->nullable();
            $t->string('entra_client_id')->nullable();
            $t->text('entra_client_secret')->nullable();
            $t->string('entra_redirect_uri')->nullable();
            $t->boolean('local_login_enabled')->default(true);
            $t->boolean('self_registration_enabled')->default(false);
            $t->boolean('internal_class_enabled')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_settings');
    }
};
