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

    /**
     * Tek bir yerden hesaplanan ilerleme özeti; sertifika tetikleme mantığı
     * (LmsController::checkAndCompleteEnrollment) ve hesap/panel görünümleri
     * ayn? sayimi kullanir -- iki yerde farkli mantikla tekrar yazilmaz.
     */
    public function completionStats(): array
    {
        $courseId = $this->course_id;
        $totalMaterials = Material::whereHas('session', fn ($q) => $q->where('course_id', $courseId))->count();
        $completedMaterials = $this->progress()->where('completed', true)->count();
        $totalActivities = Activity::whereHas('section', fn ($q) => $q->where('course_id', $courseId))->where('is_required', true)->count();
        $completedActivities = $this->activityProgress()->whereHas('activity', fn ($q) => $q->where('is_required', true))->where('completed', true)->count();
        $totalSessions = CourseSession::where('course_id', $courseId)->count();
        $completedSessions = $this->sessionProgress()->whereNotNull('completed_at')->count();
        $total = $totalMaterials + $totalActivities + $totalSessions;
        $completed = $completedMaterials + $completedActivities + $completedSessions;

        return [
            'total' => $total,
            'completed' => $completed,
            'percent' => $total ? (int) round($completed / $total * 100) : ($this->status === 'completed' ? 100 : 0),
        ];
    }
}
