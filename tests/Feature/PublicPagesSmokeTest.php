<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Material;
use App\Models\PortalSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renders every learner/instructor-facing page for real, authenticated users.
 * This is a broad regression guard against Blade compilation bugs like the
 * material-view ParseError (a dense inline @if/@else/@endif with no whitespace
 * before @endif silently failed to compile under Livewire's directive rewriter).
 * A page that merely returns a non-500 status here proves its Blade source is
 * at least well-formed and reachable for that role, not that every pixel is right.
 */
class PublicPagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_pages_render(): void
    {
        PortalSetting::create(['id' => 1, 'accepted_domains' => ['company.com'], 'local_login_enabled' => true, 'self_registration_enabled' => true]);
        Course::factory()->create(['status' => 'published']);

        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }

    public function test_learner_pages_render(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published']);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $material = Material::create(['course_session_id' => $session->id, 'title' => 'Slaytlar', 'type' => 'application/pdf', 'path' => 'demo/slides.pdf']);
        $section = $course->sections()->create(['title' => 'Bölüm 1']);
        $activity = $section->activities()->create(['title' => 'Kaynak', 'type' => 'resource', 'is_required' => true]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $this->actingAs($learner)->get('/')->assertOk();
        $this->actingAs($learner)->get(route('learning.index'))->assertOk();
        $this->actingAs($learner)->get(route('account.show'))->assertOk();
        $this->actingAs($learner)->get(route('materials.view', $material))->assertOk();
        $this->actingAs($learner)->get(route('activities.view', $activity))->assertOk();
    }

    public function test_instructor_pages_render(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['status' => 'published', 'instructor_id' => $instructor->id]);
        $course->sessions()->create(['title' => 'Seans 1']);

        $this->actingAs($instructor)->get(route('instructor.dashboard'))->assertOk();
        $this->actingAs($instructor)->get(route('instructor.courses.show', $course))->assertOk();
        $this->actingAs($instructor)->get(route('instructor.profile'))->assertOk();
        $this->actingAs($instructor)->get(route('account.show'))->assertOk();
    }

    public function test_classroom_page_renders_for_an_enrolled_learner(): void
    {
        $course = Course::factory()->create(['status' => 'published', 'delivery_type' => 'online']);
        $session = $course->sessions()->create(['title' => 'Canlı Ders', 'meeting_status' => 'scheduled', 'room_key' => 'test-room-key']);
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $this->actingAs($learner)->get(route('sessions.classroom', $session))->assertOk();
    }
}
