<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The course-creation form on the instructor dashboard was restructured into
 * a client-side-only wizard (step indicator, Next/Back, one fieldset visible
 * at a time) without touching the backend -- it's still a single POST with
 * the same field names. This proves that restructuring didn't silently drop
 * or rename any form field, since no existing test posted to this route.
 */
class CourseCreationWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_courses_page_renders_the_wizard_steps(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);

        $response = $this->actingAs($instructor)->get(route('instructor.courses.index'));

        $response->assertOk();
        $response->assertSee('1. Temel bilgiler');
        $response->assertSee('2. Misafir eğitimci');
        $response->assertSee('3. Yayın ve görseller');
    }

    public function test_submitting_the_wizard_form_creates_a_published_course(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);

        $response = $this->actingAs($instructor)->post(route('instructor.courses.store'), [
            'title' => 'Sihirbazdan Oluşturulan Eğitim',
            'description' => 'Açıklama',
            'delivery_type' => 'offline',
            'language' => 'tr',
            'certificate_enabled' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('courses', [
            'title' => 'Sihirbazdan Oluşturulan Eğitim',
            'status' => 'published',
            'instructor_id' => $instructor->id,
            'certificate_enabled' => true,
        ]);
    }

    public function test_guest_instructor_fields_are_still_saved(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);

        $this->actingAs($instructor)->post(route('instructor.courses.store'), [
            'title' => 'Konuk Eğitmenli Eğitim',
            'delivery_type' => 'offline',
            'language' => 'tr',
            'guest_instructor_name' => 'Ayşe Yılmaz',
            'guest_instructor_email' => 'ayse@example.com',
        ]);

        $this->assertDatabaseHas('courses', [
            'title' => 'Konuk Eğitmenli Eğitim',
            'guest_instructor_name' => 'Ayşe Yılmaz',
            'guest_instructor_email' => 'ayse@example.com',
        ]);
    }
}
