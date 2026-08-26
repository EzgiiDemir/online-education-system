<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = ['graded_at' => 'datetime'];

    public function session()
    {
        return $this->belongsTo(CourseSession::class, 'course_session_id');
    }

    public function versions()
    {
        return $this->hasMany(MaterialVersion::class)->latest();
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function gradedBy()
    {
        return $this->belongsTo(User::class, 'graded_by_id');
    }
}
