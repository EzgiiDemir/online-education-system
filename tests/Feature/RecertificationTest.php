<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Material;
use App\Models\MaterialProgress;
use App\Models\SessionProgress;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P1 recurring training / recertification: a completed enrollment whose
 * certificate has expired (Enrollment::isStale()) can be retaken instead of
 * being permanently stuck as "completed" with an expired certificate and no
 * way back in. This is deliberately a SECOND Enrollment row (a new "cycle")
 * for the same (user, course) pair rather than resetting the old one in
 * place -- resetting would destroy the original completion evidence
 * (MaterialProgress/QuizAttempt/etc rows), which this app never does
 * (see AccountController::destroy()'s anonymize-not-delete precedent).
 *
 * The DB-level unique(user_id, course_id) constraint that used to make a
 * second row impossible was dropped; "at most one *active* cycle at a
 * time" is now enforced in EnrollmentService::enroll() instead.
 */
class RecertificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeExpiredCycle(int $recertificationDays = 30, int $issuedDaysAgo = 40): array
    {
        $course = Course::factory()->create(['status' => 'published', 'recertification_period_days' => $recertificationDays]);
        $learner = User::factory()->create(['role' => 'learner']);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()->subDays($issuedDaysAgo)]);
        $certificate = Certificate::create(['enrollment_id' => $enrollment->id, 'certificate_number' => 'CERT-RECERT-'.$enrollment->id, 'issued_at' => now()->subDays($issuedDaysAgo)]);

        return [$course, $learner, $enrollment, $certificate];
    }

    public function test_a_completed_enrollment_with_no_certificate_is_never_stale(): void
    {
        $course = Course::factory()->create(['recertification_period_days' => 30]);
        $enrollment = Enrollment::create(['user_id' => User::factory()->create()->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);

        $this->assertFalse($enrollment->isStale());
    }

    public function test_a_completed_enrollment_whose_certificate_has_not_expired_is_not_stale(): void
    {
        [$course, $learner, $enrollment, $certificate] = $this->makeExpiredCycle(recertificationDays: 90, issuedDaysAgo: 10);

        $this->assertFalse($enrollment->isStale());
    }

    public function test_a_completed_enrollment_with_an_expired_certificate_is_stale(): void
    {
        [$course, $learner, $enrollment, $certificate] = $this->makeExpiredCycle();

        $this->assertTrue($enrollment->isStale());
    }

    public function test_an_in_progress_enrollment_is_never_stale(): void
    {
        $enrollment = Enrollment::create(['user_id' => User::factory()->create()->id, 'course_id' => Course::factory()->create()->id, 'status' => 'enrolled']);

        $this->assertFalse($enrollment->isStale());
    }

    public function test_current_for_resolves_the_latest_cycle(): void
    {
        $learner = User::factory()->create();
        $course = Course::factory()->create();
        $old = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()->subYear()]);
        $new = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $current = Enrollment::currentFor($learner->id, $course->id);

        $this->assertTrue($current->is($new));
        $this->assertFalse($current->is($old));
    }

    public function test_current_cycle_scope_returns_exactly_one_row_per_user_and_course(): void
    {
        $learner = User::factory()->create();
        $course = Course::factory()->create();
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()->subYear()]);
        $new = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $rows = Enrollment::where('user_id', $learner->id)->where('course_id', $course->id)->currentCycle()->get();

        $this->assertCount(1, $rows);
        $this->assertTrue($rows->first()->is($new));
    }

    public function test_the_db_allows_a_second_enrollment_row_for_the_same_user_and_course(): void
    {
        $learner = User::factory()->create();
        $course = Course::factory()->create();
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $this->assertSame(2, Enrollment::where('user_id', $learner->id)->where('course_id', $course->id)->count());
    }

    public function test_enrollment_service_blocks_re_enrollment_in_a_normal_completed_course(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $learner = User::factory()->create(['role' => 'learner']);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);

        $result = app(EnrollmentService::class)->enroll($course, $learner);

        $this->assertTrue($result->is($enrollment));
        $this->assertSame(1, Enrollment::where('user_id', $learner->id)->where('course_id', $course->id)->count());
    }

    public function test_enrollment_service_starts_a_fresh_cycle_when_the_current_one_is_stale(): void
    {
        [$course, $learner, $oldEnrollment, $certificate] = $this->makeExpiredCycle();

        $result = app(EnrollmentService::class)->enroll($course, $learner);

        $this->assertFalse($result->is($oldEnrollment));
        $this->assertSame('enrolled', $result->status);
        $this->assertSame(2, Enrollment::where('user_id', $learner->id)->where('course_id', $course->id)->count());
    }

    public function test_the_new_cycle_starts_at_zero_percent_not_carrying_over_old_completion(): void
    {
        [$course, $learner, $oldEnrollment, $certificate] = $this->makeExpiredCycle();
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        Material::create(['course_session_id' => $session->id, 'title' => 'Slides', 'type' => 'application/pdf', 'path' => 'demo/slides.pdf']);

        $newEnrollment = app(EnrollmentService::class)->enroll($course, $learner);

        $stats = $newEnrollment->completionStats();
        $this->assertSame(0, $stats['completed']);
        $this->assertSame(2, $stats['total']); // 1 material + 1 session, 0 completed
    }

    public function test_the_old_certificate_stays_downloadable_after_a_new_cycle_starts(): void
    {
        [$course, $learner, $oldEnrollment, $certificate] = $this->makeExpiredCycle();

        app(EnrollmentService::class)->enroll($course, $learner);

        $response = $this->actingAs($learner)->get(route('certificates.download', $certificate));
        $response->assertOk();
        $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'enrollment_id' => $oldEnrollment->id]);
    }

    public function test_course_show_offers_a_retake_button_when_recertification_is_needed(): void
    {
        [$course, $learner, $enrollment, $certificate] = $this->makeExpiredCycle();

        $response = $this->actingAs($learner)->get(route('courses.show', $course));

        $response->assertOk();
        $response->assertSee('Yeniden sertifikalandırma gerekiyor');
        $response->assertSee('Eğitimi yeniden al');
    }

    public function test_course_show_does_not_offer_a_retake_button_for_a_normal_completed_course(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);

        $response = $this->actingAs($learner)->get(route('courses.show', $course));

        $response->assertOk();
        $response->assertDontSee('Yeniden sertifikalandırma gerekiyor');
    }

    public function test_posting_to_enroll_creates_a_new_cycle_for_a_stale_learner(): void
    {
        [$course, $learner, $oldEnrollment, $certificate] = $this->makeExpiredCycle();

        $response = $this->actingAs($learner)->post(route('courses.enroll', $course));

        $response->assertRedirect(route('learning.index'));
        $this->assertSame(2, Enrollment::where('user_id', $learner->id)->where('course_id', $course->id)->count());
    }

    /**
     * Regression guard for the mechanical LmsController refactor: progress
     * (material completion) must be recorded against the CURRENT cycle when
     * two rows exist for the same user+course, not an arbitrary/oldest one.
     */
    public function test_material_progress_is_recorded_against_the_current_cycle(): void
    {
        [$course, $learner, $oldEnrollment, $certificate] = $this->makeExpiredCycle();
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $material = Material::create(['course_session_id' => $session->id, 'title' => 'Slides', 'type' => 'application/pdf', 'path' => 'demo/slides.pdf']);
        $newEnrollment = app(EnrollmentService::class)->enroll($course, $learner);
        MaterialProgress::create(['enrollment_id' => $newEnrollment->id, 'material_id' => $material->id, 'watched_seconds' => 20]);
        SessionProgress::create(['enrollment_id' => $newEnrollment->id, 'course_session_id' => $session->id, 'completed_at' => now()]);

        $this->actingAs($learner)->post(route('materials.complete', $material))->assertRedirect();

        $this->assertDatabaseHas('material_progress', ['enrollment_id' => $newEnrollment->id, 'material_id' => $material->id, 'completed' => 1]);
        $newEnrollment->refresh();
        $this->assertSame('completed', $newEnrollment->status);
        $this->assertSame('completed', $oldEnrollment->fresh()->status);
    }
}
