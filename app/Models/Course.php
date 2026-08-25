<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory, Auditable;

    protected $guarded = [];

    // Mirrors the DB column default so a freshly-instantiated (not yet
    // reloaded) model reflects version 1 immediately -- SQLite/MySQL don't
    // populate column defaults back onto the in-memory model after create().
    protected $attributes = ['version' => 1];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'certificate_enabled' => 'boolean', 'sdg_goals' => 'array'];

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function sessions()
    {
        return $this->hasMany(CourseSession::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * enrollments.course_id cascadeOnDelete()s, and enrollments themselves
     * cascade further into certificates/quiz attempts/submissions -- a hard
     * delete of a course with any enrollment history would silently destroy
     * every one of those learners' records at once. Set status to draft
     * instead; nothing about "retiring" a course requires the row to be
     * gone.
     */
    public function blockingRemovalReason(): ?string
    {
        if ($this->enrollments()->exists()) {
            return 'Bu eğitimde kayıt/tamamlanma geçmişi var, silinemez. Silme işlemi tüm katılımcıların kayıt, sertifika ve sınav geçmişini kalıcı olarak yok eder. Bunun yerine durumu "Taslak" yapmayı düşünün.';
        }

        return null;
    }

    /**
     * Exactly one row per distinct learner -- their current cycle (see
     * Enrollment::scopeCurrentCycle()) -- instead of every historical row.
     * Rosters, participant counts, and completion-rate stats should read
     * this, not enrollments(), so a recertifying learner is one person on
     * the roster and one line in the completion rate, not two.
     */
    public function currentEnrollments()
    {
        return $this->hasMany(Enrollment::class)->currentCycle();
    }

    public function runs()
    {
        return $this->hasMany(CourseRun::class);
    }

    public function sections()
    {
        return $this->hasMany(CourseSection::class)->orderBy('sort_order');
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class);
    }

    public function learningPaths()
    {
        return $this->belongsToMany(LearningPath::class, 'learning_path_course')->withPivot(['sort_order', 'is_required']);
    }

    /**
     * Courses that must be completed before a learner can enroll in this
     * one (e.g. "Fundamentals" before "Advanced").
     */
    public function prerequisites()
    {
        return $this->belongsToMany(Course::class, 'course_prerequisites', 'course_id', 'prerequisite_course_id');
    }

    /**
     * The inverse: courses that list this one as a prerequisite.
     */
    public function dependents()
    {
        return $this->belongsToMany(Course::class, 'course_prerequisites', 'prerequisite_course_id', 'course_id');
    }

    /**
     * Only checks direct prerequisites, not a transitive chain -- if A
     * requires B and B requires C, finishing C alone doesn't unlock A; the
     * learner has to actually complete B, which is the more defensible
     * reading of "prerequisite" anyway (B's own prerequisite check already
     * enforces the chain when the learner tries to enroll in B).
     */
    public function unmetPrerequisitesFor(User $user): \Illuminate\Support\Collection
    {
        $completedCourseIds = $user->enrollments()->where('status', 'completed')->pluck('course_id');

        return $this->prerequisites()->get()->reject(fn (Course $course) => $completedCourseIds->contains($course->id));
    }

    public function dueDateFor(Enrollment $enrollment): ?\Illuminate\Support\Carbon
    {
        return $this->due_days ? $enrollment->created_at->copy()->addDays($this->due_days) : null;
    }

    public function isOverdueFor(Enrollment $enrollment): bool
    {
        if ($enrollment->status === 'completed' || $enrollment->isExempt()) {
            return false;
        }
        $due = $this->dueDateFor($enrollment);

        return $due !== null && $due->isPast();
    }

    /**
     * The v1 course this row descends from -- itself, if this row has no
     * version_group_id (i.e. it IS the original).
     */
    public function versionRoot()
    {
        return $this->belongsTo(Course::class, 'version_group_id');
    }

    public function rootCourseId(): int
    {
        return $this->version_group_id ?? $this->id;
    }

    /**
     * Every version that shares this course's lineage, oldest first,
     * including both the original and this row itself.
     */
    public function versions()
    {
        $rootId = $this->rootCourseId();

        // Grouped in a closure so callers can safely chain further ->where()
        // clauses afterwards (e.g. ->where('status', 'published')) without
        // it being AND-ed into only the second half of this OR condition.
        return Course::where(fn ($q) => $q->where('id', $rootId)->orWhere('version_group_id', $rootId))->orderBy('version');
    }

    /**
     * Forks this course into a new version: a brand-new Course row (with its
     * own sections/activities copied over) linked back to the same lineage.
     * The original row -- and every enrollment/certificate that already
     * points at it -- is never touched by this. New enrollments only ever
     * happen against the new row via its own course_id.
     */
    public function createNewVersion(): Course
    {
        $nextVersion = ((int) $this->versions()->max('version')) + 1;

        $attributes = $this->only(array_diff(array_keys($this->getAttributes()), ['id', 'created_at', 'updated_at', 'version', 'version_group_id']));
        $attributes['version'] = $nextVersion;
        $attributes['version_group_id'] = $this->rootCourseId();
        $attributes['status'] = 'draft';

        $newCourse = Course::create($attributes);

        foreach ($this->sections()->with('activities')->get() as $section) {
            $newSection = $newCourse->sections()->create($section->only(['title', 'description', 'sort_order']));
            foreach ($section->activities as $activity) {
                $newSection->activities()->create($activity->only([
                    'title', 'type', 'description', 'path', 'duration_seconds', 'pass_score', 'max_attempts', 'submission_type', 'sort_order', 'is_required',
                ]));
            }
        }

        return $newCourse;
    }
}
