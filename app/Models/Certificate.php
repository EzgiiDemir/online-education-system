<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = ['issued_at' => 'datetime', 'revoked_at' => 'datetime'];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function revoker()
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function expiresAt(): ?\Illuminate\Support\Carbon
    {
        $days = $this->enrollment?->course?->recertification_period_days;

        return $days ? $this->issued_at->copy()->addDays($days) : null;
    }

    public function isExpired(): bool
    {
        return (bool) $this->expiresAt()?->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * A revoked certificate stays revoked even past its own expiry date --
     * revocation is a distinct, permanent event (fraud, error, policy
     * violation), not something a later expiry date should be allowed to
     * quietly supersede or explain away.
     */
    public function status(): string
    {
        return match (true) {
            $this->isRevoked() => 'revoked',
            $this->isExpired() => 'expired',
            default => 'active',
        };
    }

    public function revoke(User $revoker, string $reason): void
    {
        $this->update(['revoked_at' => now(), 'revoked_reason' => $reason, 'revoked_by' => $revoker->id]);

        app(\App\Services\WebhookDispatcher::class)->dispatch('certificate.revoked', [
            'certificate_id' => $this->id,
            'certificate_number' => $this->certificate_number,
            'enrollment_id' => $this->enrollment_id,
            'reason' => $reason,
        ]);
    }
}
