<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    protected $guarded = [];

    protected $casts = ['completed_at' => 'datetime'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function run()
    {
        return $this->belongsTo(CourseRun::class, 'course_run_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function progress()
    {
        return $this->hasMany(MaterialProgress::class);
    }

    public function certificate()
    {
        return $this->hasOne(Certificate::class);
    }

    public function sessionProgress()
    {
        return $this->hasMany(SessionProgress::class);
    }

    public function activityProgress()
    {
        return $this->hasMany(ActivityProgress::class);
    }
}
