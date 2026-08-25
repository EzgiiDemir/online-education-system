<?php

namespace Tests\Feature;

use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Faz 101 (QA) finding #3: the same class of bug as the Users table --
 * enrollments.course_id cascadeOnDelete()s, and enrollments cascade further
 * into certificates/quiz attempts/submissions, so deleting a course with
 * any enrollment history would silently destroy every one of those
 * learners' records at once. No safeguard existed at all.
 */
class CourseRemovalSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_course_with_enrollments_is_blocked(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create();
        $enrollment = Enrollment::create(['user_id' => User::factory()->create()->id, 'course_id' => $course->id]);

        Livewire::actingAs($admin)->test(ListCourses::class)
            ->callTableAction('delete', $course);

        $this->assertModelExists($course);
        $this->assertModelExists($enrollment);
    }

    public function test_deleting_a_course_with_no_enrollments_succeeds(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create();

        Livewire::actingAs($admin)->test(ListCourses::class)
            ->callTableAction('delete', $course);

        $this->assertModelMissing($course);
    }
}
