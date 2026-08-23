<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = ['is_required' => 'boolean'];

    public function section()
    {
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }

    public function quizQuestions()
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('sort_order');
    }

    public function quizAttempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function assignmentSubmissions()
    {
        return $this->hasMany(AssignmentSubmission::class);
    }
}
