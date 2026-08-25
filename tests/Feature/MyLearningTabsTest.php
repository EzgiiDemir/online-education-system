<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 My Learning tabs (§10): filters an authenticated learner's own
 * enrollments server-side by tab, each independently paginated. "Required"
 * is intentionally not a separate tab -- see the comment on
 * LmsController::myLearning(): in this app status flips to 'completed' the
 * instant every required item is done, so it would just duplicate "in_progress".
 */
class MyLearningTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_tab_shows_all_enrollments_with_correct_counts(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $inProgress = Course::factory()->create(['status' => 'published']);
        $completed = Course::factory()->create(['status' => 'published']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $inProgress->id, 'status' => 'enrolled']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $completed->id, 'status' => 'completed', 'completed_at' => now()]);

        $response = $this->actingAs($learner)->get(route('learning.index'));

        $response->assertOk();
        $response->assertSee($inProgress->title);
        $response->assertSee($completed->title);
    }

    public function test_completed_tab_only_shows_completed_enrollments(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $inProgress = Course::factory()->create(['status' => 'published']);
        $completedCourse = Course::factory()->create(['status' => 'published']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $inProgress->id, 'status' => 'enrolled']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $completedCourse->id, 'status' => 'completed', 'completed_at' => now()]);

        $response = $this->actingAs($learner)->get(route('learning.index', ['tab' => 'completed']));

        $response->assertOk();
        $response->assertSee($completedCourse->title);
        $response->assertDontSee($inProgress->title);
    }

    public function test_in_progress_tab_excludes_completed_enrollments(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $inProgress = Course::factory()->create(['status' => 'published']);
        $completedCourse = Course::factory()->create(['status' => 'published']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $inProgress->id, 'status' => 'enrolled']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $completedCourse->id, 'status' => 'completed', 'completed_at' => now()]);

        $response = $this->actingAs($learner)->get(route('learning.index', ['tab' => 'in_progress']));

        $response->assertOk();
        $response->assertSee($inProgress->title);
        $response->assertDontSee($completedCourse->title);
    }

    public function test_upcoming_tab_only_shows_enrollments_with_a_future_session(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $withFutureSession = Course::factory()->create(['status' => 'published', 'delivery_type' => 'online']);
        $withFutureSession->sessions()->create(['title' => 'Gelecek Ders', 'starts_at' => now()->addWeek()]);
        $withoutSession = Course::factory()->create(['status' => 'published']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $withFutureSession->id]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $withoutSession->id]);

        $response = $this->actingAs($learner)->get(route('learning.index', ['tab' => 'upcoming']));

        $response->assertOk();
        $response->assertSee($withFutureSession->title);
        $response->assertDontSee($withoutSession->title);
    }

    public function test_certificates_tab_only_shows_enrollments_with_a_certificate(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $withCertCourse = Course::factory()->create(['status' => 'published']);
        $withoutCertCourse = Course::factory()->create(['status' => 'published']);
        $withCert = Enrollment::create(['user_id' => $learner->id, 'course_id' => $withCertCourse->id, 'status' => 'completed', 'completed_at' => now()]);
        Certificate::create(['enrollment_id' => $withCert->id, 'certificate_number' => 'CERT-XYZ1', 'issued_at' => now()]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $withoutCertCourse->id]);

        $response = $this->actingAs($learner)->get(route('learning.index', ['tab' => 'certificates']));

        $response->assertOk();
        $response->assertSee($withCertCourse->title);
        $response->assertDontSee($withoutCertCourse->title);
    }

    /**
     * Faz 102 (UI/UX pass): My Learning got a search box once a learner's
     * course count grows past what a single page comfortably shows.
     */
    public function test_search_narrows_to_matching_course_titles(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $matching = Course::factory()->create(['status' => 'published', 'title' => 'Pazarlama Temelleri']);
        $other = Course::factory()->create(['status' => 'published', 'title' => 'Finans Eğitimi']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $matching->id]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $other->id]);

        $response = $this->actingAs($learner)->get(route('learning.index', ['q' => 'Pazarlama']));

        $response->assertOk();
        $response->assertSee('Pazarlama Temelleri');
        $response->assertDontSee('Finans Eğitimi');
    }

    public function test_an_unknown_tab_value_falls_back_to_all(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->get(route('learning.index', ['tab' => 'not-a-real-tab']));

        $response->assertOk();
        $response->assertSee($course->title);
    }

    /**
     * Regression: the compact card redesign replaced the per-session/material
     * breakdown with a single generic "Devam et" button pointing back at the
     * course page -- which itself had no join-class link either (see
     * CourseShowPageTest::test_enrolled_learner_sees_a_join_class_link_...).
     * Together those two gaps meant an enrolled learner could not reach the
     * live classroom or any material at all once past the very first item.
     */
    public function test_my_learning_shows_a_join_class_link_for_an_upcoming_session(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published', 'delivery_type' => 'online']);
        $session = $course->sessions()->create(['title' => 'Canlı Oturum 1', 'starts_at' => now()->addDay()]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->get(route('learning.index'));

        $response->assertOk();
        $response->assertSee('Derse katıl');
        $response->assertSee(route('sessions.classroom', $session), false);
    }
}
