<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionAttendance extends Model
{
    protected $guarded = [];

    protected $casts = ['joined_at' => 'datetime', 'left_at' => 'datetime'];

    public function session()
    {
        return $this->belongsTo(CourseSession::class, 'course_session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
