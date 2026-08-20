<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Course extends Model {
    protected $guarded = [];
    protected $casts = ['starts_at'=>'datetime','ends_at'=>'datetime','certificate_enabled'=>'boolean','sdg_goals'=>'array','guest_instructors'=>'array'];
    public function instructor(){return $this->belongsTo(User::class,'instructor_id');}
    public function sessions(){return $this->hasMany(CourseSession::class);}
    public function enrollments(){return $this->hasMany(Enrollment::class);}
    public function runs(){return $this->hasMany(CourseRun::class);}
    public function sections(){return $this->hasMany(CourseSection::class)->orderBy('sort_order');}
}
