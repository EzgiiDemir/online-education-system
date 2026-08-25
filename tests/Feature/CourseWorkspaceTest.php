<?php

namespace Tests\Feature;

use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz 93 (spec §8-9): the instructor course page became a tabbed "Course
 * Workspace" (Overview/Curriculum/Students/Sessions/Assessments/Settings)
 * instead of one long scroll. The curriculum builder's own HTML/JS is
 * untouched (still covered by CourseBuilderTest) -- this file covers what's
 * new: the Settings tab (instructors previously had no way to edit a course
 * after creating it), the enriched Students table, and course health.
 */
class CourseWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Faz 102 (UI/UX pass): (int) EXPR / 60 casts before dividing (operator
     * precedence), so the "old" attendance minutes stat was still a float
     * like 5.4166666666667 despite the cast -- needed (int) (EXPR / 60).
     */
    public function test_attendance_minutes_is_a_whole_number_not_a_long_decimal(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'delivery_type' => 'online']);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        \App\Models\SessionAttendance::create(['course_session_id' => $session->id, 'user_id' => User::factory()->create()->id, 'joined_at' => now(), 'duration_seconds' => 325]);

        $response = $this->actingAs($instructor)->get(route('instructor.courses.show', $course));

        $response->assertOk();
        $response->assertSee('>5<', false);
        $response->assertDontSee('5.4166666666667');
    }

    public function test_all_workspace_tabs_are_present(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);

        $response = $this->actingAs($instructor)->get(route('instructor.courses.show', $course));

        $response->assertOk();
        foreach (['Genel bakış', 'Müfredat', 'Öğrenciler', 'Seanslar', 'Değerlendirmeler', 'Ayarlar'] as $tab) {
            $response->assertSee($tab);
        }
    }

    public function test_the_owning_instructor_can_update_their_courses_settings(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'title' => 'Eski Başlık', 'status' => 'draft']);

        $response = $this->actingAs($instructor)->post(route('instructor.courses.update', $course), [
            'title' => 'Yeni Başlık', 'delivery_type' => 'online', 'language' => 'tr', 'status' => 'published',
            'certificate_enabled' => '1',
        ]);

        $response->assertRedirect(route('instructor.courses.show', $course));
        $this->assertSame('Yeni Başlık', $course->fresh()->title);
        $this->assertSame('published', $course->fresh()->status);
        $this->assertTrue($course->fresh()->certificate_enabled);
    }

    public function test_a_different_instructor_cannot_update_someone_elses_course(): void
    {
        $owner = User::factory()->create(['role' => 'instructor']);
        $stranger = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $owner->id, 'title' => 'Değişmesin']);

        $this->actingAs($stranger)->post(route('instructor.courses.update', $course), [
            'title' => 'Ele Geçirildi', 'delivery_type' => 'online', 'language' => 'tr', 'status' => 'published',
        ])->assertForbidden();

        $this->assertSame('Değişmesin', $course->fresh()->title);
    }

    public function test_an_admin_can_update_any_instructors_course(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'title' => 'Eski']);

        $this->actingAs($admin)->post(route('instructor.courses.update', $course), [
            'title' => 'Admin Güncelledi', 'delivery_type' => 'online', 'language' => 'tr', 'status' => 'published',
        ])->assertRedirect(route('instructor.courses.show', $course));

        $this->assertSame('Admin Güncelledi', $course->fresh()->title);
    }

    public function test_the_students_tab_shows_quiz_average_and_assignment_counts(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);
        $quiz = $section->activities()->create(['title' => 'Quiz 1', 'type' => 'quiz', 'is_required' => true]);
        $assignment = $section->activities()->create(['title' => 'Ödev 1', 'type' => 'assignment', 'submission_type' => 'text', 'is_required' => true]);
        $learner = User::factory()->create(['role' => 'learner', 'name' => 'Test Öğrenci']);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);
        QuizAttempt::create(['activity_id' => $quiz->id, 'enrollment_id' => $enrollment->id, 'score' => 80, 'submitted_at' => now()]);
        AssignmentSubmission::create(['activity_id' => $assignment->id, 'enrollment_id' => $enrollment->id, 'submitted_at' => now()]);

        $response = $this->actingAs($instructor)->get(route('instructor.courses.show', $course));

        $response->assertOk();
        $response->assertSee('Test Öğrenci');
        $response->assertSee('%80');
    }

    public function test_course_health_flags_missing_content_and_assessment(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'delivery_type' => 'offline']);

        $response = $this->actingAs($instructor)->get(route('instructor.courses.show', $course));

        $response->assertOk();
        $response->assertSeeInOrder(['İçerik', 'Eksik']);
    }

    public function test_course_health_marks_content_ready_once_a_section_has_an_activity(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'delivery_type' => 'offline']);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);
        $section->activities()->create(['title' => 'Video', 'type' => 'video', 'is_required' => true]);

        $response = $this->actingAs($instructor)->get(route('instructor.courses.show', $course));

        $response->assertOk();
        $response->assertSeeInOrder(['İçerik', 'Hazır']);
    }
}
