<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = ['issued_at' => 'datetime'];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
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
}
