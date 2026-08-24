<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use Auditable;

    protected $guarded = [];

    public function session()
    {
        return $this->belongsTo(CourseSession::class, 'course_session_id');
    }

    public function versions()
    {
        return $this->hasMany(MaterialVersion::class)->latest();
    }
}
