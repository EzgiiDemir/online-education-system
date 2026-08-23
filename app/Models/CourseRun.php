<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class CourseRun extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = ['enrollment_opens_at' => 'datetime', 'enrollment_closes_at' => 'datetime', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }
}
