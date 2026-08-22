<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 "Completion Transparency": the learner must be able to see WHY a material
 * or activity isn't complete yet -- required / verified / remaining time --
 * without weakening the underlying anti-cheat rule (server-accrued heartbeat
 * time only). This only asserts on the display; WatchTimeIntegrityTest already
 * covers that the accrual itself cannot be faked.
 */
class CompletionTransparencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_material_view_shows_required_verified_and_remaining_time(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published']);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $material = Material::create(['course_session_id' => $session->id, 'title' => 'Slaytlar', 'type' => 'application/pdf', 'path' => 'demo/slides.pdf', 'duration_seconds' => 100]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->get(route('materials.view', $material));

        $response->assertOk();
        $response->assertSee('Gerekli:');
        $response->assertSee('Doğrulanan:');
        $response->assertSee('Kalan:');
        $response->assertSee('Sekme arka plandayken geçen süre sayılmaz');
        // duration 100 -> required = ceil(100*0.9) = 90s = 01:30
        $response->assertSee('01:30');
    }

    public function test_activity_view_shows_required_verified_and_remaining_time(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published']);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);
        $activity = Activity::create(['course_section_id' => $section->id, 'title' => 'Video', 'type' => 'video', 'is_required' => true, 'duration_seconds' => 40]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->get(route('activities.view', $activity));

        $response->assertOk();
        $response->assertSee('Gerekli:');
        $response->assertSee('Doğrulanan:');
        $response->assertSee('Kalan:');
    }

    public function test_completed_material_shows_no_remaining_time_hint(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published']);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $material = Material::create(['course_session_id' => $session->id, 'title' => 'Slaytlar', 'type' => 'application/pdf', 'path' => 'demo/slides.pdf', 'duration_seconds' => 20]);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);
        $enrollment->progress()->create(['material_id' => $material->id, 'watched_seconds' => 20, 'completed' => true, 'completed_at' => now()]);

        $response = $this->actingAs($learner)->get(route('materials.view', $material));

        $response->assertOk();
        $response->assertSee('Tamamlandı');
        $response->assertDontSee('Sekme arka plandayken geçen süre sayılmaz');
    }
}
