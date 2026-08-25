<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 Course Detail page (/courses/{course}): the learner-facing "should I take
 * this course / where do I stand in it" page that didn't exist before -- the
 * catalog only had cards, with no click-through. Visibility mirrors the
 * catalog's own rule (published only), except for the owning instructor/admin.
 */
class CourseShowPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_a_published_course(): void
    {
        $course = Course::factory()->create(['status' => 'published', 'description' => 'Bu eğitimde iletişim becerilerini geliştireceksiniz.']);

        $response = $this->get(route('courses.show', $course));

        $response->assertOk();
        $response->assertSee($course->title);
        $response->assertSee('Bu eğitimde iletişim becerilerini geliştireceksiniz.');
        $response->assertSee('Kayıt olmak için giriş yapın');
    }

    public function test_guest_cannot_view_a_draft_course(): void
    {
        $course = Course::factory()->create(['status' => 'draft']);

        $this->get(route('courses.show', $course))->assertNotFound();
    }

    public function test_owning_instructor_can_preview_their_own_draft_course(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['status' => 'draft', 'instructor_id' => $instructor->id]);

        $this->actingAs($instructor)->get(route('courses.show', $course))->assertOk();
    }

    public function test_another_instructors_draft_course_is_not_visible(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['status' => 'draft']);

        $this->actingAs($instructor)->get(route('courses.show', $course))->assertNotFound();
    }

    public function test_authenticated_non_enrolled_learner_sees_enroll_button(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published']);

        $response = $this->actingAs($learner)->get(route('courses.show', $course));

        $response->assertOk();
        $response->assertSee('Eğitime kayıt ol');
    }

    public function test_enrolled_learner_sees_progress_and_next_action(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published']);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $material = Material::create(['course_session_id' => $session->id, 'title' => 'Slaytlar', 'type' => 'application/pdf', 'path' => 'demo/slides.pdf']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->get(route('courses.show', $course));

        $response->assertOk();
        $response->assertSee('Tamamlanma koşulları');
        $response->assertSee('Öğrenmeye devam et');
        $response->assertSee(route('materials.view', $material));
    }

    /**
     * Regression: the Sessions section used to show a session's title and a
     * completion badge only -- no way to actually get into the live class.
     * The only click-through into content was the single "Continue learning"
     * button computed from nextIncompleteItem(), which explicitly skips
     * live_session activities -- so a course whose curriculum was mostly
     * live sessions left an enrolled learner with no path in at all.
     */
    public function test_enrolled_learner_sees_a_join_class_link_for_an_upcoming_session(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published', 'delivery_type' => 'online']);
        $session = $course->sessions()->create(['title' => 'Canlı Oturum 1', 'starts_at' => now()->addDay()]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->get(route('courses.show', $course));

        $response->assertOk();
        $response->assertSee('Derse katıl');
        $response->assertSee(route('sessions.classroom', $session), false);
    }

    public function test_completed_enrollment_shows_certificate_download_link(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published']);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);
        $certificate = Certificate::create(['enrollment_id' => $enrollment->id, 'certificate_number' => 'CERT-TEST123', 'issued_at' => now()]);

        $response = $this->actingAs($learner)->get(route('courses.show', $course));

        $response->assertOk();
        $response->assertSee('Sertifikayı indir');
        $response->assertSee(route('certificates.download', $certificate));
    }
}
