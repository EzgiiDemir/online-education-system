<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class PortalSetting extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = ['accepted_domains' => 'array', 'available_locales' => 'array', 'entra_client_secret' => 'encrypted', 'entra_enabled' => 'boolean', 'local_login_enabled' => 'boolean', 'self_registration_enabled' => 'boolean', 'internal_class_enabled' => 'boolean'];

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], ['portal_name' => 'Arucad Campus LMS', 'accepted_domains' => ['company.com'], 'available_locales' => ['tr', 'en']]);
    }
}
