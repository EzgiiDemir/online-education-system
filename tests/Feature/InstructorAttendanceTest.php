<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz 102 (UI/UX pass): a dedicated attendance flow (course search →
 * session picker → roster) -- previously attendance marking only existed
 * buried inside a specific course's Sessions tab, with no standalone entry
 * point.
 */
class InstructorAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_step_one_lists_the_instructors_own_courses(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $ownCourse = Course::factory()->create(['instructor_id' => $instructor->id, 'title' => 'Kendi Eğitimim']);
        $otherCourse = Course::factory()->create(['title' => 'Başkasının Eğitimi']);

        $response = $this->actingAs($instructor)->get(route('instructor.attendance'));

        $response->assertOk();
        $response->assertSee('Kendi Eğitimim');
        $response->assertDontSee('Başkasının Eğitimi');
    }

    public function test_search_narrows_the_course_list(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        Course::factory()->create(['instructor_id' => $instructor->id, 'title' => 'Pazarlama Eğitimi']);
        Course::factory()->create(['instructor_id' => $instructor->id, 'title' => 'Finans Eğitimi']);

        $response = $this->actingAs($instructor)->get(route('instructor.attendance', ['q' => 'Pazarlama']));

        $response->assertOk();
        $response->assertSee('Pazarlama Eğitimi');
        $response->assertDontSee('Finans Eğitimi');
    }

    public function test_step_two_lists_the_selected_courses_sessions(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $course->sessions()->create(['title' => 'Seans 1']);

        $response = $this->actingAs($instructor)->get(route('instructor.attendance', ['course_id' => $course->id]));

        $response->assertOk();
        $response->assertSee('Seans 1');
        $response->assertSee('Bir seans seçin');
    }

    public function test_an_instructor_cannot_select_a_course_they_do_not_teach(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $otherCourse = Course::factory()->create(['title' => 'Erişemem']);

        $response = $this->actingAs($instructor)->get(route('instructor.attendance', ['course_id' => $otherCourse->id]));

        $response->assertOk();
        $response->assertDontSee('Erişemem');
        $response->assertSee('Bir eğitim seçin');
    }

    public function test_step_three_shows_the_roster_and_can_mark_attendance(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'delivery_type' => 'offline']);
        $session = $course->sessions()->create(['title' => 'Seans 1', 'meeting_status' => 'not_applicable']);
        $learner = User::factory()->create(['name' => 'Katılımcı Öğrenci']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($instructor)->get(route('instructor.attendance', ['course_id' => $course->id, 'session_id' => $session->id]));

        $response->assertOk();
        $response->assertSee('Katılımcı Öğrenci');
        $response->assertSee('Katıldı olarak işaretle');

        $this->actingAs($instructor)->post(route('instructor.sessions.attendance', $session), ['user_id' => $learner->id])
            ->assertRedirect();

        $second = $this->actingAs($instructor)->get(route('instructor.attendance', ['course_id' => $course->id, 'session_id' => $session->id]));
        $second->assertSee('Katıldı');
    }

    public function test_a_learner_cannot_access_the_attendance_page(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);

        $this->actingAs($learner)->get(route('instructor.attendance'))->assertForbidden();
    }

    public function test_the_owning_instructor_can_export_the_roster_as_csv(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $learner = User::factory()->create(['name' => 'CSV Öğrenci', 'email' => 'csv-ogrenci@example.com']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($instructor)->get(route('instructor.sessions.attendance.export', $session));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('CSV Öğrenci', $response->streamedContent());
        $this->assertStringContainsString('csv-ogrenci@example.com', $response->streamedContent());
    }

    public function test_a_different_instructor_cannot_export_someone_elses_roster(): void
    {
        $owner = User::factory()->create(['role' => 'instructor']);
        $intruder = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $owner->id]);
        $session = $course->sessions()->create(['title' => 'Seans 1']);

        $this->actingAs($intruder)->get(route('instructor.sessions.attendance.export', $session))->assertForbidden();
    }
}
