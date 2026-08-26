<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 Certificate verification (§28): a public, unauthenticated page anyone
 * can use to confirm a certificate number is real -- deliberately shows only
 * holder name / course / dates, never the downloadable PDF or other
 * enrollment data, and requires no login. Reuses the existing unique,
 * non-guessable certificate_number rather than adding a new UUID column.
 */
class CertificateVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_verify_a_real_certificate_without_logging_in(): void
    {
        $learner = User::factory()->create(['name' => 'Ayşe Yılmaz']);
        $course = Course::factory()->create(['title' => 'Doğrulanabilir Eğitim']);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);
        $certificate = Certificate::create(['enrollment_id' => $enrollment->id, 'certificate_number' => 'CERT-VERIFY01', 'issued_at' => now()]);

        $response = $this->get(route('certificates.verify', $certificate->certificate_number));

        $response->assertOk();
        $response->assertSee('Geçerli sertifika');
        $response->assertSee('Ayşe Yılmaz');
        $response->assertSee('Doğrulanabilir Eğitim');
    }

    public function test_an_unknown_certificate_number_is_reported_as_not_found(): void
    {
        $response = $this->get(route('certificates.verify', 'CERT-DOESNOTEXIST'));

        $response->assertOk();
        $response->assertSee('Bulunamadı');
    }

    public function test_expired_certificate_is_flagged_on_the_verification_page(): void
    {
        $course = Course::factory()->create(['recertification_period_days' => 30]);
        $enrollment = Enrollment::create(['user_id' => User::factory()->create()->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);
        $certificate = Certificate::create(['enrollment_id' => $enrollment->id, 'certificate_number' => 'CERT-EXPIRED1', 'issued_at' => now()->subDays(60)]);

        $response = $this->get(route('certificates.verify', $certificate->certificate_number));

        $response->assertOk();
        $response->assertSee('süresi dolmuştur');
    }

    public function test_verification_page_does_not_expose_a_download_link(): void
    {
        $enrollment = Enrollment::create(['user_id' => User::factory()->create()->id, 'course_id' => Course::factory()->create()->id, 'status' => 'completed', 'completed_at' => now()]);
        $certificate = Certificate::create(['enrollment_id' => $enrollment->id, 'certificate_number' => 'CERT-NODL01', 'issued_at' => now()]);

        $response = $this->get(route('certificates.verify', $certificate->certificate_number));

        $response->assertOk();
        $response->assertDontSee(route('certificates.download', $certificate));
    }

    /**
     * The verify route requires a certificate number in the URL, so anyone
     * arriving without one already in hand (e.g. from a nav link, not a QR
     * scan) needs a lookup form -- this is that entry point.
     */
    public function test_the_lookup_page_shows_a_form_when_no_number_is_given(): void
    {
        $response = $this->get(route('certificates.verify.lookup'));

        $response->assertOk();
        $response->assertSee('Sertifika doğrula');
    }

    public function test_submitting_a_number_on_the_lookup_page_redirects_to_the_verify_page(): void
    {
        $response = $this->get(route('certificates.verify.lookup', ['number' => 'CERT-LOOKUP01']));

        $response->assertRedirect(route('certificates.verify', 'CERT-LOOKUP01'));
    }

    public function test_the_verification_link_is_reachable_from_every_workspace_sidebar(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $instructor = User::factory()->create(['role' => 'instructor']);
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($learner)->get(route('lms.home'))->assertSee(route('certificates.verify.lookup'), false);
        $this->actingAs($instructor)->get(route('instructor.dashboard'))->assertSee(route('certificates.verify.lookup'), false);
        $this->actingAs($manager)->get(route('manager.dashboard'))->assertSee(route('certificates.verify.lookup'), false);
    }
}
