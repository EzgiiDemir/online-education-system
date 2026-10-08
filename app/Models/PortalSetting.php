<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PortalSetting extends Model
{
    use Auditable;

    public const DEFAULT_LOGO = 'images/sdg/arucad_home_logo.png';

    /**
     * The stock portal name. Kept as a constant because two places have to
     * recognise it: current() seeds it, and the admin panel brand falls back
     * to a translated label while the name is still this untouched default
     * (an admin who renames the portal means that name literally, in every
     * locale, so it is shown as typed).
     */
    public const DEFAULT_NAME = 'Arucad Eğitim Merkezi';

    /**
     * Signature block on the certificate PDF. Defaults match the official
     * ARUCAD "Katılım Belgesi"; an admin overrides them in the portal
     * settings screen when the signatory changes.
     */
    public const DEFAULT_SIGNATORY_NAME = 'Prof. Dr. Asım Vehbi';

    public const DEFAULT_SIGNATORY_TITLE = 'Rektör';

    public const DEFAULT_ISSUER_NAME = 'Arkın Yaratıcı Sanatlar ve Tasarım Üniversitesi';

    public function signatoryName(): string
    {
        return $this->certificate_signatory_name ?: self::DEFAULT_SIGNATORY_NAME;
    }

    public function signatoryTitle(): string
    {
        return $this->certificate_signatory_title ?: self::DEFAULT_SIGNATORY_TITLE;
    }

    public function issuerName(): string
    {
        return $this->certificate_issuer_name ?: self::DEFAULT_ISSUER_NAME;
    }

    protected $guarded = [];

    protected $casts = ['accepted_domains' => 'array', 'available_locales' => 'array', 'entra_client_secret' => 'encrypted', 'entra_enabled' => 'boolean', 'local_login_enabled' => 'boolean', 'self_registration_enabled' => 'boolean', 'internal_class_enabled' => 'boolean'];

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], ['portal_name' => self::DEFAULT_NAME, 'accepted_domains' => ['company.com'], 'available_locales' => ['tr', 'en']]);
    }

    /**
     * Public URL for the admin-uploaded portal logo, or the bundled
     * default mark when none is set / the file is missing.
     */
    public function logoUrl(): string
    {
        $path = $this->logo_path;
        if (is_array($path)) {
            $path = $path[0] ?? null;
        }
        if (! is_string($path) || $path === '') {
            return asset(self::DEFAULT_LOGO);
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        return asset(self::DEFAULT_LOGO);
    }
}
