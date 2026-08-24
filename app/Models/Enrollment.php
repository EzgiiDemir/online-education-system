<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = ['completed_at' => 'datetime', 'exempted_at' => 'datetime'];

    /**
     * Snapshots which course version a learner enrolled under, once, at
     * creation time. This is what "completion is tied to a version" means
     * (§29): the value never changes afterwards even if the course is later
     * edited or forked into a new version, so a completed/certified
     * enrollment always keeps pointing at the content it was actually
     * completed against.
     */
    public static function booted(): void
    {
        static::creating(function (Enrollment $enrollment) {
            if ($enrollment->course_version === null && $enrollment->course_id) {
                $enrollment->course_version = Course::find($enrollment->course_id)?->version ?? 1;
            }
        });
    }

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

    public function exemptedBy()
    {
        return $this->belongsTo(User::class, 'exempted_by');
    }

    public function isExempt(): bool
    {
        return $this->exempted_at !== null;
    }

    public function exempt(User $actor, string $reason): void
    {
        $this->update(['exempted_at' => now(), 'exemption_reason' => $reason, 'exempted_by' => $actor->id]);
    }

    public function unexempt(): void
    {
        $this->update(['exempted_at' => null, 'exemption_reason' => null, 'exempted_by' => null]);
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

    /**
     * "Continue Learning" CTA'sının nereye götüreceğini belirler: sıradaki
     * tamamlanmamış materyal, yoksa sıradaki tamamlanmamış zorunlu etkinlik.
     * live_session tipi etkinlikler hariç -- onlar sadece oturum katılımıyla
     * tamamlanır, kendi görüntüleme sayfaları yok. İkisi de kalmadıysa null
     * döner (geriye kalan tek şey seans katılımıdır).
     */
    public function nextIncompleteItem(): ?array
    {
        $completedMaterialIds = $this->progress()->where('completed', true)->pluck('material_id');
        $material = Material::whereHas('session', fn ($q) => $q->where('course_id', $this->course_id))
            ->whereNotIn('id', $completedMaterialIds)
            ->orderBy('id')
            ->first();
        if ($material) {
            return ['type' => 'material', 'model' => $material];
        }

        $completedActivityIds = $this->activityProgress()->where('completed', true)->pluck('activity_id');
        $activity = Activity::whereHas('section', fn ($q) => $q->where('course_id', $this->course_id))
            ->where('is_required', true)
            ->where('type', '!=', 'live_session')
            ->whereNotIn('id', $completedActivityIds)
            ->orderBy('sort_order')
            ->first();
        if ($activity) {
            return ['type' => 'activity', 'model' => $activity];
        }

        return null;
    }
}
