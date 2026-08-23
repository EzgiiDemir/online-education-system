<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class CourseSession extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'meeting_started_at' => 'datetime'];

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
