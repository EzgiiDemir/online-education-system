<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionProgress extends Model
{
    protected $guarded = [];

    protected $casts = ['started_at' => 'datetime', 'last_seen_at' => 'datetime', 'completed_at' => 'datetime'];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function session()
    {
        return $this->belongsTo(CourseSession::class, 'course_session_id');
    }
}
