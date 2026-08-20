<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_a_required_activity_is_recorded(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $section = $course->sections()->create(['title' => 'Bölüm 1']);
        $activity = $section->activities()->create(['title' => 'Giriş videosu', 'type' => 'video', 'is_required' => true]);
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->post(route('activities.complete', $activity), ['watched_seconds' => 60]);

        $response->assertRedirect();
        $this->assertDatabaseHas('activity_progress', ['activity_id' => $activity->id, 'completed' => true]);
    }

    public function test_course_completes_only_once_every_material_and_required_activity_is_done(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $material = Material::create(['course_session_id' => $session->id, 'title' => 'Slides', 'type' => 'application/pdf', 'path' => 'demo/slides.pdf']);
        $section = $course->sections()->create(['title' => 'Bölüm 1']);
        $required = $section->activities()->create(['title' => 'Zorunlu video', 'type' => 'video', 'is_required' => true]);
        $optional = $section->activities()->create(['title' => 'Ek kaynak', 'type' => 'resource', 'is_required' => false]);
        $learner = User::factory()->create(['role' => 'learner']);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $this->actingAs($learner)->post(route('materials.complete', $material));
        $enrollment->refresh();
        $this->assertNotSame('completed', $enrollment->status);

        $this->actingAs($learner)->post(route('activities.complete', $required));
        $enrollment->refresh();
        $this->assertSame('completed', $enrollment->status);
        $this->assertTrue(Certificate::where('enrollment_id', $enrollment->id)->exists());
        $this->assertDatabaseMissing('activity_progress', ['activity_id' => $optional->id]);
    }

    public function test_optional_activity_alone_does_not_complete_the_course(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $section = $course->sections()->create(['title' => 'Bölüm 1']);
        $section->activities()->create(['title' => 'Zorunlu video', 'type' => 'video', 'is_required' => true]);
        $optional = $section->activities()->create(['title' => 'Ek kaynak', 'type' => 'resource', 'is_required' => false]);
        $learner = User::factory()->create(['role' => 'learner']);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $this->actingAs($learner)->post(route('activities.complete', $optional));

        $enrollment->refresh();
        $this->assertNotSame('completed', $enrollment->status);
    }
}
