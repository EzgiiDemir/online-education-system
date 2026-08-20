<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Material;
use App\Models\MaterialProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveSessionAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_offline_session_cannot_be_self_completed_by_the_learner(): void
    {
        $course = Course::factory()->create(['status' => 'published', 'delivery_type' => 'offline']);
        $session = $course->sessions()->create(['title' => 'Yüz yüze oturum', 'meeting_status' => 'not_applicable']);
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->post(route('sessions.complete', $session));

        $response->assertStatus(422);
        $this->assertDatabaseMissing('session_progress', ['course_session_id' => $session->id, 'completed_at' => now()]);
    }

    public function test_online_session_cannot_be_completed_without_accrued_presence(): void
    {
        $course = Course::factory()->create(['status' => 'published', 'delivery_type' => 'online']);
        $session = $course->sessions()->create(['title' => 'Canlı ders', 'meeting_status' => 'scheduled']);
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->post(route('sessions.complete', $session));

        $response->assertStatus(422);
    }

    public function test_class_presence_pings_accrue_real_attendance_time_and_unlock_completion(): void
    {
        $course = Course::factory()->create(['status' => 'published', 'delivery_type' => 'online']);
        $session = $course->sessions()->create(['title' => 'Canlı ders', 'meeting_status' => 'scheduled', 'starts_at' => now(), 'ends_at' => now()->addSeconds(10)]);
        // required = max(60, ceil(10*0.9)) = 60s -> 12 accepted pings of 5s each.
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        for ($i = 0; $i < 11; $i++) {
            $this->actingAs($learner)->postJson(route('sessions.presence', $session));
            $this->travel(5)->seconds();
        }
        $this->actingAs($learner)->post(route('sessions.complete', $session))->assertStatus(422);

        $this->actingAs($learner)->postJson(route('sessions.presence', $session));
        $response = $this->actingAs($learner)->post(route('sessions.complete', $session));

        $response->assertRedirect();
        $this->assertDatabaseHas('session_progress', ['course_session_id' => $session->id]);
    }

    public function test_instructor_marking_offline_attendance_counts_toward_certificate(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['status' => 'published', 'delivery_type' => 'offline', 'instructor_id' => $instructor->id]);
        $session = $course->sessions()->create(['title' => 'Yüz yüze oturum', 'meeting_status' => 'not_applicable']);
        $learner = User::factory()->create(['role' => 'learner']);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($instructor)->post(route('instructor.sessions.attendance', $session), ['user_id' => $learner->id]);

        $response->assertRedirect();
        $enrollment->refresh();
        $this->assertSame('completed', $enrollment->status);
        $this->assertTrue(Certificate::where('enrollment_id', $enrollment->id)->exists());
    }

    public function test_learner_cannot_mark_their_own_offline_attendance(): void
    {
        $course = Course::factory()->create(['status' => 'published', 'delivery_type' => 'offline']);
        $session = $course->sessions()->create(['title' => 'Yüz yüze oturum', 'meeting_status' => 'not_applicable']);
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->post(route('instructor.sessions.attendance', $session), ['user_id' => $learner->id]);

        $response->assertForbidden();
    }

    public function test_instructor_cannot_mark_attendance_for_a_course_they_do_not_own(): void
    {
        $owner = User::factory()->create(['role' => 'instructor']);
        $intruder = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $owner->id]);
        $session = $course->sessions()->create(['title' => 'Yüz yüze oturum', 'meeting_status' => 'not_applicable']);
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($intruder)->post(route('instructor.sessions.attendance', $session), ['user_id' => $learner->id]);

        $response->assertForbidden();
    }

    public function test_certificate_is_withheld_until_both_the_session_and_the_material_are_done(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['status' => 'published', 'delivery_type' => 'offline', 'instructor_id' => $instructor->id]);
        $session = $course->sessions()->create(['title' => 'Yüz yüze oturum', 'meeting_status' => 'not_applicable']);
        Material::create(['course_session_id' => $session->id, 'title' => 'El kitabı', 'type' => 'application/pdf', 'path' => 'demo/handbook.pdf']);
        $learner = User::factory()->create(['role' => 'learner']);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $this->actingAs($instructor)->post(route('instructor.sessions.attendance', $session), ['user_id' => $learner->id]);
        $enrollment->refresh();
        $this->assertNotSame('completed', $enrollment->status, 'Course must not complete while the material is still unwatched.');

        MaterialProgress::create(['enrollment_id' => $enrollment->id, 'material_id' => Material::first()->id, 'watched_seconds' => 20]);
        $this->actingAs($learner)->post(route('materials.complete', Material::first()));

        $enrollment->refresh();
        $this->assertSame('completed', $enrollment->status);
        $this->assertTrue(Certificate::where('enrollment_id', $enrollment->id)->exists());
    }
}
