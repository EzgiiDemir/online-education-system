<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 Calendar (§11): every event is derived from data that already exists
 * (session start times, course.due_days, certificate expiry) -- there is no
 * separate events table. Month view + agenda only; week view intentionally
 * not built (see controller doc comment).
 */
class CalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_live_session_from_an_enrolled_course_appears_on_its_date(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published', 'delivery_type' => 'online']);
        $sessionDate = now()->addDays(5);
        $course->sessions()->create(['title' => 'Takvim Testi Seansı', 'starts_at' => $sessionDate]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->get(route('calendar.index', ['month' => $sessionDate->format('Y-m')]));

        $response->assertOk();
        $response->assertSee('Takvim Testi Seansı');
    }

    public function test_a_session_from_a_course_the_learner_is_not_enrolled_in_does_not_appear(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published', 'delivery_type' => 'online']);
        $sessionDate = now()->addDays(5);
        $course->sessions()->create(['title' => 'Yabancı Seans', 'starts_at' => $sessionDate]);

        $response = $this->actingAs($learner)->get(route('calendar.index', ['month' => $sessionDate->format('Y-m')]));

        $response->assertOk();
        $response->assertDontSee('Yabancı Seans');
    }

    public function test_a_due_date_appears_derived_from_course_due_days(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published', 'due_days' => 10, 'title' => 'Son Tarihli Eğitim']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'enrolled', 'created_at' => now()]);
        $dueMonth = now()->addDays(10)->format('Y-m');

        $response = $this->actingAs($learner)->get(route('calendar.index', ['month' => $dueMonth]));

        $response->assertOk();
        $response->assertSee('Son Tarihli Eğitim');
    }

    public function test_a_completed_enrollments_due_date_does_not_appear(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published', 'due_days' => 10, 'title' => 'Tamamlanmış Eğitim']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now(), 'created_at' => now()]);
        $dueMonth = now()->addDays(10)->format('Y-m');

        $response = $this->actingAs($learner)->get(route('calendar.index', ['month' => $dueMonth]));

        $response->assertOk();
        $response->assertDontSee('Tamamlanmış Eğitim');
    }

    public function test_certificate_expiry_appears_on_the_calendar(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published', 'recertification_period_days' => 30, 'title' => 'Süreli Sertifika Eğitimi']);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);
        Certificate::create(['enrollment_id' => $enrollment->id, 'certificate_number' => 'CERT-CAL01', 'issued_at' => now()]);
        $expiryMonth = now()->addDays(30)->format('Y-m');

        $response = $this->actingAs($learner)->get(route('calendar.index', ['month' => $expiryMonth]));

        $response->assertOk();
        $response->assertSee('Süreli Sertifika Eğitimi');
    }

    public function test_ics_download_requires_enrollment_in_the_sessions_course(): void
    {
        $outsider = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published', 'delivery_type' => 'online']);
        $session = $course->sessions()->create(['title' => 'Seans 1', 'starts_at' => now()->addDay()]);

        $this->actingAs($outsider)->get(route('calendar.ics.session', $session))->assertForbidden();
    }

    public function test_ics_download_succeeds_for_an_enrolled_learner(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published', 'delivery_type' => 'online']);
        $session = $course->sessions()->create(['title' => 'Seans 1', 'starts_at' => now()->addDay()]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->get(route('calendar.ics.session', $session));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=UTF-8');
    }
}
