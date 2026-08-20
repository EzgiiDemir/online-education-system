<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isInstructor();
    }

    public function view(User $user, Course $course): bool
    {
        return $user->role === 'admin' || $user->id === $course->instructor_id;
    }

    public function create(User $user): bool
    {
        return $user->isInstructor();
    }

    public function update(User $user, Course $course): bool
    {
        return $user->role === 'admin' || $user->id === $course->instructor_id;
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->role === 'admin';
    }
}
