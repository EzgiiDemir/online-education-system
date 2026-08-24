<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaitlistEntry extends Model
{
    protected $guarded = [];

    public function courseRun()
    {
        return $this->belongsTo(CourseRun::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
