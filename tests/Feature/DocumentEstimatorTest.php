<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Material;
use App\Models\User;
use App\Support\DocumentEstimator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P1 storage abstraction: DocumentEstimator used to take a hardcoded
 * storage_path('app/public/...') absolute path, which would silently break
 * the moment the 'public' disk was ever pointed at S3 instead of local
 * storage. It now resolves through Storage::disk($disk)->path($path) like
 * every other file read in this app, and degrades to "no hint" (never an
 * error) for a disk that has no local filesystem path at all.
 */
class DocumentEstimatorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A disk name with no matching entry in config/filesystems.php (e.g. an
     * S3 disk whose driver package isn't installed, or a typo) throws at
     * resolution time rather than returning a broken filesystem -- this is
     * the case the try/catch in estimateMinutes() exists for.
     */
    public function test_returns_null_when_the_disk_cannot_be_resolved_at_all(): void
    {
        $minutes = DocumentEstimator::estimateMinutes('not-a-configured-disk', 'materials/does-not-exist.pdf', 'application/pdf');

        $this->assertNull($minutes);
    }

    public function test_returns_null_for_a_missing_file_on_a_resolvable_disk(): void
    {
        Storage::fake('public');

        $minutes = DocumentEstimator::estimateMinutes('public', 'materials/does-not-exist.pdf', 'application/pdf');

        $this->assertNull($minutes);
    }

    public function test_returns_null_for_an_unrecognized_mime_type(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('materials/notes.txt', 'plain text content');

        $minutes = DocumentEstimator::estimateMinutes('public', 'materials/notes.txt', 'text/plain');

        $this->assertNull($minutes);
    }

    public function test_counts_pdf_pages_from_the_public_disk(): void
    {
        Storage::fake('public');
        $pdfBytes = "%PDF-1.4\n1 0 obj<</Type/Page>>endobj\n2 0 obj<</Type/Page>>endobj\n3 0 obj<</Type/Page>>endobj\n%%EOF";
        Storage::disk('public')->put('materials/three-pages.pdf', $pdfBytes);

        $minutes = DocumentEstimator::estimateMinutes('public', 'materials/three-pages.pdf', 'application/pdf');

        $this->assertSame(5, $minutes); // 3 pages * 1.5 min, rounded
    }

    public function test_the_material_view_page_still_renders_with_a_pdf_attached(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('materials/slides.pdf', "%PDF-1.4\n1 0 obj<</Type/Page>>endobj\n%%EOF");
        $course = Course::factory()->create(['status' => 'published']);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $material = Material::create(['course_session_id' => $session->id, 'title' => 'Slides', 'type' => 'application/pdf', 'path' => 'materials/slides.pdf']);
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->get(route('materials.view', $material));

        $response->assertOk();
    }
}
