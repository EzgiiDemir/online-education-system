<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 certificate lifecycle: revoking used to mean hard-deleting the row,
 * destroying the very record a compliance audit needs to keep ("this
 * certificate existed and was invalidated on this date, for this reason").
 * Certificate::revoke() instead flips a status; this suite checks that a
 * revoked certificate is treated as invalid everywhere it's checked, not
 * just flagged in the database and otherwise ignored.
 */
class CertificateLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function makeCertificate(): Certificate
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create();
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'completed']);

        return Certificate::create(['enrollment_id' => $enrollment->id, 'certificate_number' => 'CERT-LIFECYCLE1', 'issued_at' => now()]);
    }

    public function test_a_fresh_certificate_is_active(): void
    {
        $certificate = $this->makeCertificate();

        $this->assertSame('active', $certificate->status());
        $this->assertFalse($certificate->isRevoked());
    }

    public function test_revoking_records_who_when_and_why(): void
    {
        $certificate = $this->makeCertificate();
        $admin = User::factory()->create(['role' => 'admin']);

        $certificate->revoke($admin, 'Yanlış katılımcıya verildi');

        $certificate->refresh();
        $this->assertSame('revoked', $certificate->status());
        $this->assertTrue($certificate->isRevoked());
        $this->assertSame('Yanlış katılımcıya verildi', $certificate->revoked_reason);
        $this->assertSame($admin->id, $certificate->revoked_by);
        $this->assertNotNull($certificate->revoked_at);
    }

    public function test_a_revoked_certificate_cannot_be_downloaded(): void
    {
        $certificate = $this->makeCertificate();
        $admin = User::factory()->create(['role' => 'admin']);
        $certificate->revoke($admin, 'Test');
        $owner = $certificate->enrollment->user;

        $response = $this->actingAs($owner)->get(route('certificates.download', $certificate));

        $response->assertStatus(410);
    }

    public function test_a_non_revoked_certificate_can_still_be_downloaded(): void
    {
        $certificate = $this->makeCertificate();
        $owner = $certificate->enrollment->user;

        $response = $this->actingAs($owner)->get(route('certificates.download', $certificate));

        $response->assertOk();
    }

    /**
     * The QR code lets a physical printout be verified by scanning rather
     * than typing in the URL -- P1 §15. Only ext-gd/ext-imagick-free SVG
     * output is available in this environment (see QrCodeGenerator), and
     * DomPDF needs an explicit pixel size on the <svg> tag itself or it
     * renders oversized; this locks both of those in.
     */
    public function test_the_downloaded_pdf_embeds_a_qr_code_pointing_at_the_verify_url(): void
    {
        $certificate = $this->makeCertificate();
        $owner = $certificate->enrollment->user;

        $html = view('lms.certificate', [
            'certificate' => $certificate,
            'enrollment' => $certificate->enrollment->load('user', 'course'),
        ])->render();

        $this->assertStringContainsString('<svg width="100" height="100"', $html);
        $this->assertStringContainsString(route('certificates.verify', $certificate->certificate_number), $html);

        $response = $this->actingAs($owner)->get(route('certificates.download', $certificate));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_public_verify_page_flags_a_revoked_certificate(): void
    {
        $certificate = $this->makeCertificate();
        $admin = User::factory()->create(['role' => 'admin']);
        $certificate->revoke($admin, 'Test');

        $response = $this->get(route('certificates.verify', $certificate->certificate_number));

        $response->assertOk();
        $response->assertSee('İptal edilmiş sertifika');
        $response->assertDontSee('Geçerli sertifika');
    }

    public function test_the_public_verify_page_shows_a_non_revoked_certificate_as_valid(): void
    {
        $certificate = $this->makeCertificate();

        $response = $this->get(route('certificates.verify', $certificate->certificate_number));

        $response->assertOk();
        $response->assertSee('Geçerli sertifika');
    }

    public function test_admin_certificates_index_page_renders(): void
    {
        $this->makeCertificate();
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/admin/certificates');

        $response->assertOk();
        $response->assertSee('CERT-LIFECYCLE1');
    }
}
