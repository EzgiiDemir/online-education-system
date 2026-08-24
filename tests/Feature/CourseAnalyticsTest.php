<?php

namespace Tests\Feature;

use App\Models\ActivityProgress;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Material;
use App\Models\MaterialProgress;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz 95 (spec §13): the Course Workspace's Analytics tab, deferred from
 * Faz 93. The headline feature is "content performance" -- not just how
 * students as a whole are doing, but which specific video/quiz is where
 * they're getting stuck, each shown as its own completion/score rate.
 */
class CourseAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_completion_funnel_counts_started_halfway_and_completed(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);
        // 4 required activities so 1/4, 2/4 and 4/4 land on clean percentages.
        $activities = collect(range(1, 4))->map(fn ($i) => $section->activities()->create(['title' => "Etkinlik $i", 'type' => 'video', 'is_required' => true]));

        $markDone = function (Enrollment $enrollment, int $count) use ($activities) {
            foreach ($activities->take($count) as $activity) {
                ActivityProgress::create(['enrollment_id' => $enrollment->id, 'activity_id' => $activity->id, 'completed' => true, 'completed_at' => now()]);
            }
        };

        $untouched = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $untouched->id, 'course_id' => $course->id]);

        $startedOnly = User::factory()->create(['role' => 'learner']);
        $markDone(Enrollment::create(['user_id' => $startedOnly->id, 'course_id' => $course->id]), 1);

        $halfway = User::factory()->create(['role' => 'learner']);
        $markDone(Enrollment::create(['user_id' => $halfway->id, 'course_id' => $course->id]), 2);

        $completedUser = User::factory()->create(['role' => 'learner']);
        $completedEnrollment = Enrollment::create(['user_id' => $completedUser->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);
        $markDone($completedEnrollment, 4);

        $response = $this->actingAs($instructor)->get(route('instructor.courses.show', $course));

        $response->assertOk();
        // 4 enrollments: 3 have >0% progress (started), 2 are at 50%+
        // (halfway + completed), 1 has status === 'completed'.
        $response->assertSeeInOrder(['BAŞLADI', '3']);
        $response->assertSeeInOrder(['%50+', '2']);
        $response->assertSeeInOrder(['TAMAMLADI', '1']);
    }

    public function test_quiz_average_score_and_pass_rate_are_computed_across_all_quiz_activities(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);
        $quiz = $section->activities()->create(['title' => 'Quiz 1', 'type' => 'quiz', 'is_required' => true]);
        $learnerA = User::factory()->create(['role' => 'learner']);
        $learnerB = User::factory()->create(['role' => 'learner']);
        $enrollmentA = Enrollment::create(['user_id' => $learnerA->id, 'course_id' => $course->id]);
        $enrollmentB = Enrollment::create(['user_id' => $learnerB->id, 'course_id' => $course->id]);
        QuizAttempt::create(['activity_id' => $quiz->id, 'enrollment_id' => $enrollmentA->id, 'score' => 100, 'passed' => true, 'submitted_at' => now()]);
        QuizAttempt::create(['activity_id' => $quiz->id, 'enrollment_id' => $enrollmentB->id, 'score' => 40, 'passed' => false, 'submitted_at' => now()]);

        $response = $this->actingAs($instructor)->get(route('instructor.courses.show', $course));

        $response->assertOk();
        $response->assertSee('Ortalama puan');
        $response->assertSee('%70');
        $response->assertSee('%50');
    }

    public function test_content_performance_flags_a_low_completion_item_and_not_a_high_one(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $strongMaterial = Material::create(['course_session_id' => $session->id, 'title' => 'Güçlü İçerik', 'type' => 'application/pdf', 'path' => 'demo/strong.pdf']);
        $weakMaterial = Material::create(['course_session_id' => $session->id, 'title' => 'Zayıf İçerik', 'type' => 'application/pdf', 'path' => 'demo/weak.pdf']);

        foreach (range(1, 5) as $i) {
            $learner = User::factory()->create(['role' => 'learner']);
            $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);
            MaterialProgress::create(['enrollment_id' => $enrollment->id, 'material_id' => $strongMaterial->id, 'completed' => true, 'completed_at' => now()]);
            if ($i === 1) {
                MaterialProgress::create(['enrollment_id' => $enrollment->id, 'material_id' => $weakMaterial->id, 'completed' => true, 'completed_at' => now()]);
            }
        }

        $response = $this->actingAs($instructor)->get(route('instructor.courses.show', $course));

        $response->assertOk();
        $response->assertSeeInOrder(['Güçlü İçerik', '%100']);
        $response->assertSeeInOrder(['Zayıf İçerik', '⚠', '%20']);
    }

    public function test_assignment_stats_split_submitted_from_pending(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);
        $assignment = $section->activities()->create(['title' => 'Ödev 1', 'type' => 'assignment', 'submission_type' => 'text', 'is_required' => true]);
        $learner = User::factory()->create(['role' => 'learner']);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);
        AssignmentSubmission::create(['activity_id' => $assignment->id, 'enrollment_id' => $enrollment->id, 'submitted_at' => now()]);

        $response = $this->actingAs($instructor)->get(route('instructor.courses.show', $course));

        $response->assertOk();
        $response->assertSeeInOrder(['Teslim edilen', '1']);
        $response->assertSeeInOrder(['Bekleyen', '1']);
    }

    public function test_analytics_tab_shows_an_empty_state_for_a_course_with_no_enrollments(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);

        $response = $this->actingAs($instructor)->get(route('instructor.courses.show', $course));

        $response->assertOk();
        $response->assertSee('Henüz içerik tamamlama verisi yok.');
    }
}
