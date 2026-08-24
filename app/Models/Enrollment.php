<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use Auditable;

    protected $guarded = [];

    // Mirrors the DB column default so a freshly-instantiated (not yet
    // reloaded) model reflects 'enrolled' immediately -- SQLite/MySQL don't
    // populate column defaults back onto the in-memory model after create()
    // (see Course::$attributes for the same issue with `version`). Without
    // this, EnrollmentService::enroll() would hand back a new cycle whose
    // ->status is null until the caller happens to ->fresh() it.
    protected $attributes = ['status' => 'enrolled'];

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

        // A deleted enrollment (e.g. an admin removing someone, or a future
        // learner-initiated cancellation) may have just freed the seat that
        // was blocking the run's waitlist -- this is the only place that
        // trigger is wired up, so it fires regardless of which code path
        // did the deleting.
        static::deleted(function (Enrollment $enrollment) {
            if ($enrollment->course_run_id) {
                $enrollment->run?->promoteNextFromWaitlist();
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

    /**
     * True once this cycle is "done" in a way that should let the learner
     * start a fresh one: completed AND its certificate has expired
     * (recertification due). A completed enrollment with no certificate,
     * or a certificate with no recertification_period_days, never goes
     * stale -- there's nothing to recertify against, so it stays the
     * learner's one and only cycle forever, exactly like before this
     * feature existed.
     */
    public function isStale(): bool
    {
        return $this->status === 'completed' && $this->certificate && $this->certificate->isExpired();
    }

    /**
     * The most recent enrollment row for a given (user, course) pair --
     * "current" regardless of whether it's still in progress or long since
     * completed. Safe by construction: EnrollmentService::enroll() only
     * ever starts a new cycle once the previous one is both completed and
     * isStale(), so at most one non-stale cycle exists at a time and the
     * highest id is always the right one to act on.
     */
    public static function currentFor(int $userId, int $courseId): ?self
    {
        return static::where('user_id', $userId)->where('course_id', $courseId)->latest('id')->first();
    }

    /**
     * Restricts a query to exactly one row per (user_id, course_id) pair --
     * whichever has the highest id, i.e. the current cycle as defined by
     * currentFor() above. Use this anywhere a roster/stats view lists or
     * counts "how many learners/enrollments", so a recertifying learner's
     * old, superseded cycle doesn't count twice.
     */
    public function scopeCurrentCycle($query)
    {
        return $query->where('enrollments.id', function ($sub) {
            $sub->selectRaw('MAX(e2.id)')
                ->from('enrollments as e2')
                ->whereColumn('e2.user_id', 'enrollments.user_id')
                ->whereColumn('e2.course_id', 'enrollments.course_id');
        });
    }

    public function sessionProgress()
    {
        return $this->hasMany(SessionProgress::class);
    }

    public function activityProgress()
    {
        return $this->hasMany(ActivityProgress::class);
    }

    public function quizAttempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function assignmentSubmissions()
    {
        return $this->hasMany(AssignmentSubmission::class);
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
