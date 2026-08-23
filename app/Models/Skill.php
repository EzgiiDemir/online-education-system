<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    use Auditable;

    protected $guarded = [];

    public function courses()
    {
        return $this->belongsToMany(Course::class);
    }
}
