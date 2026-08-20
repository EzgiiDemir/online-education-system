<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseSection extends Model
{
    protected $guarded = [];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class)->orderBy('sort_order');
    }
}
