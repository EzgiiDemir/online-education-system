<?php

namespace Tests\Feature;

use App\Filament\Resources\Certificates\Pages\ListCertificates;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin's Certificates list (Filament) had no way to get data out of it at
 * all -- no CSV, no PDF, nothing -- despite every other admin list-heavy
 * area (Manager team export, all four Reports pages) already having one.
 * This adds the same capability here, reusing the app's existing CSV
 * pattern and the dompdf setup the certificate PDF itself already uses.
 */
class CertificatesAdminExportTest extends TestCase
{
    use RefreshDatabase;

    private function makeCertificate(string $number): Certificate
    {
        $learner = User::factory()->create(['name' => 'Export Test User']);
        $course = Course::factory()->create(['title' => 'Export Test Course']);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);

        return Certificate::create(['enrollment_id' => $enrollment->id, 'certificate_number' => $number, 'issued_at' => now()]);
    }

    public function test_an_admin_can_export_certificates_as_csv(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->makeCertificate('CERT-CSVEXPORT1');

        Livewire::actingAs($admin)->test(ListCertificates::class)
            ->callTableAction('exportCsv')
            ->assertFileDownloaded();
    }

    public function test_an_admin_can_export_certificates_as_pdf(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->makeCertificate('CERT-PDFEXPORT1');

        Livewire::actingAs($admin)->test(ListCertificates::class)
            ->callTableAction('exportPdf')
            ->assertFileDownloaded();
    }

    public function test_a_non_admin_cannot_reach_the_certificates_list_page(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);

        $this->actingAs($learner)->get('/admin/certificates')->assertForbidden();
    }
}
