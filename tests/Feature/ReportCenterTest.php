<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\SessionProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz 99 (spec §26): the report center gained three aggregate views
 * alongside the existing per-enrollment Training report -- Completion (per
 * course), Attendance (per online course), Instructors (per instructor).
 * Certificate/Compliance report types were deliberately not built as
 * separate screens (see ReportController's docblock): they'd duplicate the
 * existing CertificateResource and ComplianceDashboard.
 */
class ReportCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_completion_report_shows_a_courses_enrollment_and_completion_counts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['title' => 'Tamamlanma Testi']);
        $completed = User::factory()->create();
        $active = User::factory()->create();
        Enrollment::create(['user_id' => $completed->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);
        Enrollment::create(['user_id' => $active->id, 'course_id' => $course->id, 'status' => 'enrolled']);

        $response = $this->actingAs($admin)->get(route('reports.completion'));

        $response->assertOk();
        $response->assertSee('Tamamlanma Testi');
        $response->assertSee('%50');
    }

    public function test_attendance_report_computes_average_attendance_for_an_online_course(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['title' => 'Katılım Testi', 'delivery_type' => 'online']);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $attended = User::factory()->create();
        $absent = User::factory()->create();
        $attendedEnrollment = Enrollment::create(['user_id' => $attended->id, 'course_id' => $course->id]);
        Enrollment::create(['user_id' => $absent->id, 'course_id' => $course->id]);
        SessionProgress::create(['enrollment_id' => $attendedEnrollment->id, 'course_session_id' => $session->id, 'started_at' => now(), 'completed_at' => now()]);

        $response = $this->actingAs($admin)->get(route('reports.attendance'));

        $response->assertOk();
        $response->assertSee('Katılım Testi');
        $response->assertSee('%50');
    }

    public function test_an_offline_course_does_not_appear_in_the_attendance_report(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Course::factory()->create(['title' => 'Yüz Yüze Eğitim', 'delivery_type' => 'offline'])
            ->sessions()->create(['title' => 'Seans 1']);

        $response = $this->actingAs($admin)->get(route('reports.attendance'));

        $response->assertOk();
        $response->assertDontSee('Yüz Yüze Eğitim');
    }

    public function test_instructor_report_rolls_up_courses_and_students_per_instructor(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $instructor = User::factory()->create(['role' => 'instructor', 'name' => 'Rapor Eğitmeni']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        Enrollment::create(['user_id' => User::factory()->create()->id, 'course_id' => $course->id]);

        $response = $this->actingAs($admin)->get(route('reports.instructors'));

        $response->assertOk();
        $response->assertSee('Rapor Eğitmeni');
        $response->assertSee($instructor->email);
    }

    public function test_an_instructor_with_no_courses_does_not_appear_in_the_instructor_report(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'instructor', 'name' => 'Bos Egitmen']);

        $response = $this->actingAs($admin)->get(route('reports.instructors'));

        $response->assertOk();
        $response->assertDontSee('Bos Egitmen');
    }

    public function test_non_admins_cannot_view_or_export_any_new_report(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)->get(route('reports.completion'))->assertForbidden();
        $this->actingAs($manager)->get(route('reports.attendance'))->assertForbidden();
        $this->actingAs($manager)->get(route('reports.instructors'))->assertForbidden();
        $this->actingAs($manager)->get(route('reports.completion.export'))->assertForbidden();
        $this->actingAs($manager)->get(route('reports.attendance.export'))->assertForbidden();
        $this->actingAs($manager)->get(route('reports.instructors.export'))->assertForbidden();
    }

    public function test_the_instructor_filter_narrows_the_training_report(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $instructorA = User::factory()->create(['role' => 'instructor']);
        $instructorB = User::factory()->create(['role' => 'instructor']);
        $courseA = Course::factory()->create(['instructor_id' => $instructorA->id]);
        $courseB = Course::factory()->create(['instructor_id' => $instructorB->id]);
        Enrollment::create(['user_id' => User::factory()->create(['name' => 'Eğitmen A Öğrencisi'])->id, 'course_id' => $courseA->id]);
        Enrollment::create(['user_id' => User::factory()->create(['name' => 'Eğitmen B Öğrencisi'])->id, 'course_id' => $courseB->id]);

        $response = $this->actingAs($admin)->get(route('reports.index', ['instructor_id' => $instructorA->id]));

        $response->assertOk();
        $response->assertSee('Eğitmen A Öğrencisi');
        $response->assertDontSee('Eğitmen B Öğrencisi');
    }

    public function test_the_manager_filter_narrows_the_training_report(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $managerA = User::factory()->create(['role' => 'manager']);
        $managerB = User::factory()->create(['role' => 'manager']);
        $course = Course::factory()->create();
        Enrollment::create(['user_id' => User::factory()->create(['name' => 'A Ekibi', 'manager_id' => $managerA->id])->id, 'course_id' => $course->id]);
        Enrollment::create(['user_id' => User::factory()->create(['name' => 'B Ekibi', 'manager_id' => $managerB->id])->id, 'course_id' => $course->id]);

        $response = $this->actingAs($admin)->get(route('reports.index', ['manager_id' => $managerA->id]));

        $response->assertOk();
        $response->assertSee('A Ekibi');
        $response->assertDontSee('B Ekibi');
    }
}
