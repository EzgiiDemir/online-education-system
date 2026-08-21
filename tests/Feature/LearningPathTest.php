<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningPath;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 Learning Paths (§24): a sequential/optional ordering of existing
 * courses. Locking is informational only -- it does not block direct access
 * to a course's own page or catalog enrollment, which keep working exactly
 * as before (see LearningPath::isLockedFor doc comment).
 */
class LearningPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_published_paths_are_listed_in_the_catalog(): void
    {
        $published = LearningPath::create(['title' => 'Yayında Yol', 'status' => 'published']);
        LearningPath::create(['title' => 'Taslak Yol', 'status' => 'draft']);

        $response = $this->get(route('learning-paths.index'));

        $response->assertOk();
        $response->assertSee('Yayında Yol');
        $response->assertDontSee('Taslak Yol');
    }

    public function test_guest_cannot_view_a_draft_path(): void
    {
        $path = LearningPath::create(['title' => 'Taslak', 'status' => 'draft']);

        $this->get(route('learning-paths.show', $path))->assertNotFound();
    }

    public function test_second_required_course_is_locked_until_the_first_is_completed(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $path = LearningPath::create(['title' => 'Onboarding', 'status' => 'published', 'is_sequential' => true]);
        $first = Course::factory()->create(['status' => 'published']);
        $second = Course::factory()->create(['status' => 'published']);
        $path->courses()->attach($first, ['sort_order' => 1, 'is_required' => true]);
        $path->courses()->attach($second, ['sort_order' => 2, 'is_required' => true]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $first->id, 'status' => 'enrolled']);

        $response = $this->actingAs($learner)->get(route('learning-paths.show', $path));

        $response->assertOk();
        $response->assertSee('Kilitli');
    }

    public function test_second_course_unlocks_once_the_first_required_course_is_completed(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $path = LearningPath::create(['title' => 'Onboarding', 'status' => 'published', 'is_sequential' => true]);
        $first = Course::factory()->create(['status' => 'published']);
        $second = Course::factory()->create(['status' => 'published']);
        $path->courses()->attach($first, ['sort_order' => 1, 'is_required' => true]);
        $path->courses()->attach($second, ['sort_order' => 2, 'is_required' => true]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $first->id, 'status' => 'completed', 'completed_at' => now()]);

        $response = $this->actingAs($learner)->get(route('learning-paths.show', $path));

        $response->assertOk();
        $response->assertDontSee('Kilitli');
    }

    public function test_non_sequential_path_never_locks_courses(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $path = LearningPath::create(['title' => 'Serbest Yol', 'status' => 'published', 'is_sequential' => false]);
        $first = Course::factory()->create(['status' => 'published']);
        $second = Course::factory()->create(['status' => 'published']);
        $path->courses()->attach($first, ['sort_order' => 1, 'is_required' => true]);
        $path->courses()->attach($second, ['sort_order' => 2, 'is_required' => true]);

        $this->assertFalse($path->isLockedFor($second, $learner));
    }

    public function test_enrolling_in_a_path_enrolls_the_learner_in_every_published_course(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $path = LearningPath::create(['title' => 'Tam Program', 'status' => 'published']);
        $courseA = Course::factory()->create(['status' => 'published']);
        $courseB = Course::factory()->create(['status' => 'published']);
        $draftCourse = Course::factory()->create(['status' => 'draft']);
        $path->courses()->attach($courseA, ['sort_order' => 1, 'is_required' => true]);
        $path->courses()->attach($courseB, ['sort_order' => 2, 'is_required' => true]);
        $path->courses()->attach($draftCourse, ['sort_order' => 3, 'is_required' => true]);

        $this->actingAs($learner)->post(route('learning-paths.enroll', $path));

        $this->assertDatabaseHas('enrollments', ['user_id' => $learner->id, 'course_id' => $courseA->id]);
        $this->assertDatabaseHas('enrollments', ['user_id' => $learner->id, 'course_id' => $courseB->id]);
        $this->assertDatabaseMissing('enrollments', ['user_id' => $learner->id, 'course_id' => $draftCourse->id]);
    }

    public function test_path_progress_percentage_reflects_completed_courses(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $path = LearningPath::create(['title' => 'İlerleme Testi', 'status' => 'published']);
        $courseA = Course::factory()->create(['status' => 'published']);
        $courseB = Course::factory()->create(['status' => 'published']);
        $path->courses()->attach($courseA, ['sort_order' => 1, 'is_required' => true]);
        $path->courses()->attach($courseB, ['sort_order' => 2, 'is_required' => true]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $courseA->id, 'status' => 'completed', 'completed_at' => now()]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $courseB->id, 'status' => 'enrolled']);

        $response = $this->actingAs($learner)->get(route('learning-paths.index'));

        $response->assertOk();
        $response->assertSee('%50');
    }
}
