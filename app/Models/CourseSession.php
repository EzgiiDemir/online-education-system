<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class CourseSession extends Model
{
    use Auditable;

    /**
     * How long an uploaded live-class recording stays available before
     * app:purge-expired-recordings deletes it. Recordings are raw
     * webm/mp4 captures with no editing/redaction step, so an indefinite
     * retention window is a growing storage and data-minimization liability
     * rather than a feature -- a fixed cutoff is the safer default.
     */
    public const RECORDING_RETENTION_DAYS = 180;

    protected $guarded = [];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'meeting_started_at' => 'datetime', 'recording_expires_at' => 'datetime'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function materials()
    {
        return $this->hasMany(Material::class);
    }

    public function progress()
    {
        return $this->hasMany(SessionProgress::class);
    }

    public function getMeetingUrlAttribute()
    {
        return $this->room_key ? url('/meet/'.$this->room_key) : null;
    }
}
