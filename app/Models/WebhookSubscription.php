<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WebhookSubscription extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = ['events' => 'array', 'is_active' => 'boolean'];

    public const EVENTS = [
        'enrollment.created' => 'Yeni kayıt oluşturuldu',
        'enrollment.completed' => 'Eğitim tamamlandı',
        'certificate.issued' => 'Sertifika verildi',
        'certificate.revoked' => 'Sertifika iptal edildi',
    ];

    public function deliveries()
    {
        return $this->hasMany(WebhookDelivery::class)->latest();
    }

    public static function generateSecret(): string
    {
        return 'whsec_'.Str::random(40);
    }

    public function subscribesTo(string $event): bool
    {
        return $this->is_active && in_array($event, $this->events ?? [], true);
    }
}
