<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAttempt extends Model
{
    protected $guarded = [];

    protected $casts = ['answers' => 'array', 'passed' => 'boolean', 'submitted_at' => 'datetime'];

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }
}
