<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    protected $guarded = [];

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }

    public function options()
    {
        return $this->hasMany(QuizOption::class)->orderBy('sort_order');
    }
}
