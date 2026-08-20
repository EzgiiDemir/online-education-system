<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WatchTimeIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_material_cannot_be_completed_without_any_watch_time(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $material = Material::create(['course_session_id' => $session->id, 'title' => 'Slides', 'type' => 'application/pdf', 'path' => 'demo/slides.pdf']);
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->post(route('materials.complete', $material));

        $response->assertStatus(422);
        $this->assertDatabaseMissing('material_progress', ['material_id' => $material->id, 'completed' => true]);
    }

    public function test_material_completion_requires_accrued_heartbeat_time(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $material = Material::create(['course_session_id' => $session->id, 'title' => 'Video', 'type' => 'video/mp4', 'path' => 'demo/video.mp4']);
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        // required = 20s (no duration set on the material); each accepted heartbeat adds 5s.
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($learner)->postJson(route('materials.heartbeat', $material));
            $this->travel(5)->seconds();
        }

        $stillIneligible = $this->actingAs($learner)->post(route('materials.complete', $material));
        $stillIneligible->assertStatus(422);

        $this->actingAs($learner)->postJson(route('materials.heartbeat', $material));

        $now = $this->actingAs($learner)->post(route('materials.complete', $material));
        $now->assertRedirect();
        $this->assertDatabaseHas('material_progress', ['material_id' => $material->id, 'completed' => true]);
    }

    public function test_heartbeat_calls_faster_than_the_throttle_window_do_not_accrue_extra_time(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $material = Material::create(['course_session_id' => $session->id, 'title' => 'Video', 'type' => 'video/mp4', 'path' => 'demo/video.mp4']);
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $first = $this->actingAs($learner)->postJson(route('materials.heartbeat', $material))->json();
        $spam = $this->actingAs($learner)->postJson(route('materials.heartbeat', $material))->json();
        $spam2 = $this->actingAs($learner)->postJson(route('materials.heartbeat', $material))->json();

        $this->assertSame(5, $first['watched_seconds']);
        $this->assertSame(5, $spam['watched_seconds']);
        $this->assertSame(5, $spam2['watched_seconds']);
    }

    public function test_activity_cannot_be_completed_without_any_watch_time(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $section = $course->sections()->create(['title' => 'Bölüm 1']);
        $activity = $section->activities()->create(['title' => 'Video', 'type' => 'video', 'is_required' => true]);
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->post(route('activities.complete', $activity));

        $response->assertStatus(422);
        $this->assertDatabaseMissing('activity_progress', ['activity_id' => $activity->id, 'completed' => true]);
    }

    public function test_live_session_activities_are_not_self_completable(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $section = $course->sections()->create(['title' => 'Bölüm 1']);
        $activity = $section->activities()->create(['title' => 'Canlı oturum', 'type' => 'live_session', 'is_required' => true]);
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $this->actingAs($learner)->get(route('activities.view', $activity))->assertStatus(422);
        $this->actingAs($learner)->postJson(route('activities.heartbeat', $activity))->assertStatus(422);
        $this->actingAs($learner)->post(route('activities.complete', $activity))->assertStatus(422);
    }

    public function test_certificate_is_not_issued_when_only_watch_time_is_faked_client_side(): void
    {
        // Regression guard: completeMaterial must ignore any client-supplied
        // watched_seconds and rely solely on server-accrued heartbeat time.
        $course = Course::factory()->create(['status' => 'published']);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $material = Material::create(['course_session_id' => $session->id, 'title' => 'Video', 'type' => 'video/mp4', 'path' => 'demo/video.mp4']);
        $learner = User::factory()->create(['role' => 'learner']);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $this->actingAs($learner)->post(route('materials.complete', $material), ['watched_seconds' => 999999]);

        $enrollment->refresh();
        $this->assertNotSame('completed', $enrollment->status);
        $this->assertDatabaseMissing('material_progress', ['material_id' => $material->id, 'completed' => true]);
    }
}
