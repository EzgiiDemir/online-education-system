<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class LearningPath extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = ['is_sequential' => 'boolean'];

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'learning_path_course')
            ->withPivot(['sort_order', 'is_required'])
            ->orderBy('learning_path_course.sort_order');
    }

    /**
     * For a sequential path, a required course is locked until every earlier
     * required course in the path has been completed by the given user. This
     * is informational only -- it does not block direct access to a course
     * via its own /courses/{course} page or catalog enrollment, which keep
     * working exactly as before.
     */
    public function isLockedFor(Course $course, User $user): bool
    {
        if (! $this->is_sequential) {
            return false;
        }

        $pivot = $this->courses->firstWhere('id', $course->id)?->pivot;
        if (! $pivot || ! $pivot->is_required) {
            return false;
        }

        $earlierRequiredCourseIds = $this->courses
            ->filter(fn ($c) => $c->pivot->is_required && $c->pivot->sort_order < $pivot->sort_order)
            ->pluck('id');

        if ($earlierRequiredCourseIds->isEmpty()) {
            return false;
        }

        $completedCount = $user->enrollments()
            ->whereIn('course_id', $earlierRequiredCourseIds)
            ->where('status', 'completed')
            ->count();

        return $completedCount < $earlierRequiredCourseIds->count();
    }
}
