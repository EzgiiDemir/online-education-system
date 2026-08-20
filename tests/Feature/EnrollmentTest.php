<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Material;
use App\Models\User;
use App\Notifications\LiveSessionScheduled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolling_in_published_course_notifies_learner_of_scheduled_sessions(): void
    {
        Notification::fake();

        $course = Course::factory()->create(['delivery_type' => 'online', 'status' => 'published']);
        $session = $course->sessions()->create(['title' => 'Live', 'meeting_status' => 'scheduled']);
        $learner = User::factory()->create(['role' => 'learner']);

        $response = $this->actingAs($learner)->post(route('courses.enroll', $course));

        $response->assertRedirect(route('learning.index'));
        $this->assertDatabaseHas('enrollments', ['user_id' => $learner->id, 'course_id' => $course->id]);
        Notification::assertSentTo($learner, LiveSessionScheduled::class, fn ($n) => $n->session->is($session));
    }

    public function test_enrolling_twice_does_not_duplicate_or_renotify(): void
    {
        Notification::fake();

        $course = Course::factory()->create(['status' => 'published']);
        $learner = User::factory()->create(['role' => 'learner']);

        $this->actingAs($learner)->post(route('courses.enroll', $course));
        $this->actingAs($learner)->post(route('courses.enroll', $course));

        $this->assertSame(1, Enrollment::where('user_id', $learner->id)->where('course_id', $course->id)->count());
    }

    public function test_cannot_enroll_in_a_draft_course(): void
    {
        $course = Course::factory()->draft()->create();
        $learner = User::factory()->create(['role' => 'learner']);

        $response = $this->actingAs($learner)->post(route('courses.enroll', $course));

        $response->assertNotFound();
        $this->assertDatabaseMissing('enrollments', ['user_id' => $learner->id, 'course_id' => $course->id]);
    }

    public function test_completing_every_material_completes_enrollment_and_issues_certificate(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $material = Material::create([
            'course_session_id' => $session->id,
            'title' => 'Slides',
            'type' => 'application/pdf',
            'path' => 'demo/slides.pdf',
        ]);
        $learner = User::factory()->create(['role' => 'learner']);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->post(route('materials.complete', $material), ['watched_seconds' => 120]);

        $response->assertOk();
        $enrollment->refresh();
        $this->assertSame('completed', $enrollment->status);
        $this->assertNotNull($enrollment->completed_at);
        $this->assertTrue(Certificate::where('enrollment_id', $enrollment->id)->exists());
    }
}
