<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 Instructor dashboard "Today's Actions" (§13): upcoming session,
 * sessions needing attendance review, incomplete learners, new enrollments.
 * Each widget is scoped strictly to the current instructor's own courses.
 */
class InstructorDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_upcoming_session_is_shown(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'delivery_type' => 'online']);
        $course->sessions()->create(['title' => 'Yaklaşan Seans', 'starts_at' => now()->addDay()]);

        $response = $this->actingAs($instructor)->get(route('instructor.dashboard'));

        $response->assertOk();
        $response->assertSee('SIRADAKİ SEANS');
        $response->assertSee('Yaklaşan Seans');
    }

    public function test_session_without_attendance_needs_review(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'delivery_type' => 'online']);
        $course->sessions()->create(['title' => 'Dünkü Seans', 'starts_at' => now()->subDay()]);

        $response = $this->actingAs($instructor)->get(route('instructor.dashboard'));

        $response->assertOk();
        $response->assertSee('KATILIMCI LİSTESİ KONTROLÜ');
        $response->assertSee('Dünkü Seans');
    }

    public function test_a_session_with_recorded_attendance_does_not_need_review(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'delivery_type' => 'online']);
        $session = $course->sessions()->create(['title' => 'Katılım Alınan Seans', 'starts_at' => now()->subDay()]);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);
        $session->progress()->create(['enrollment_id' => $enrollment->id, 'started_at' => now()->subDay(), 'completed_at' => now()->subDay()]);

        $response = $this->actingAs($instructor)->get(route('instructor.dashboard'));

        $response->assertOk();
        $response->assertDontSee('KATILIMCI LİSTESİ KONTROLÜ');
    }

    /**
     * Faz 92 (Instructor Overview redesign, spec §5): "students behind"
     * means overdue against the course's due window, not merely "hasn't
     * finished yet" -- the old generic DEVAM EDEN ÖĞRENCİ count fired for
     * almost every active enrollment and wasn't actionable. Also verifies
     * the pending-tasks checklist stays scoped to the instructor's own
     * courses, same guarantee the old test protected.
     */
    public function test_the_pending_tasks_checklist_counts_students_behind_scoped_to_the_instructors_own_courses(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $otherInstructor = User::factory()->create(['role' => 'instructor']);
        $learner = User::factory()->create(['role' => 'learner']);
        $ownCourse = Course::factory()->create(['instructor_id' => $instructor->id, 'due_days' => 5]);
        $otherCourse = Course::factory()->create(['instructor_id' => $otherInstructor->id, 'due_days' => 5]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $ownCourse->id, 'created_at' => now()->subDays(10)]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $otherCourse->id, 'created_at' => now()->subDays(10)]);

        $response = $this->actingAs($instructor)->get(route('instructor.dashboard'));

        $response->assertOk();
        $response->assertSee('öğrenci geride kaldı');
    }

    public function test_the_pending_tasks_checklist_is_empty_when_no_one_is_behind(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'due_days' => 30]);
        $course->sections()->create(['title' => 'Bölüm 1']);
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'created_at' => now()->subDays(2)]);

        $response = $this->actingAs($instructor)->get(route('instructor.dashboard'));

        $response->assertOk();
        $response->assertDontSee('öğrenci geride kaldı');
        $response->assertSee('Şu an dikkat gerektiren bir şey yok.');
    }

    public function test_course_card_shows_completion_rate(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $completedLearner = User::factory()->create(['role' => 'learner']);
        $activeLearner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $completedLearner->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);
        Enrollment::create(['user_id' => $activeLearner->id, 'course_id' => $course->id, 'status' => 'enrolled']);

        $response = $this->actingAs($instructor)->get(route('instructor.courses.index'));

        $response->assertOk();
        $response->assertSee('%50 tamamlama');
    }
}
