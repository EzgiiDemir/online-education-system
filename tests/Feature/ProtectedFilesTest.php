<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Material;
use App\Models\MaterialVersion;
use App\Models\User;
use App\Support\ProtectedFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Course content and learner uploads are served only through /files/{path}
 * after a per-file access check (ProtectedFiles). Before, they sat behind the
 * public/storage symlink and anyone holding a URL could download them.
 */
class ProtectedFilesTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->instructor = User::factory()->create(['role' => 'instructor']);
        $this->course = Course::factory()->create(['status' => 'published', 'instructor_id' => $this->instructor->id]);
    }

    private function learner(bool $enrolled = true): User
    {
        $learner = User::factory()->create(['role' => 'learner']);
        if ($enrolled) {
            Enrollment::create(['user_id' => $learner->id, 'course_id' => $this->course->id]);
        }

        return $learner;
    }

    private function material(string $path = 'materials/slides.pdf'): Material
    {
        Storage::disk('local')->put($path, "%PDF-1.4\n%%EOF");
        $session = $this->course->sessions()->create(['title' => 'Seans 1']);

        return Material::create(['course_session_id' => $session->id, 'title' => 'Slides', 'type' => 'application/pdf', 'path' => $path]);
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->material();

        $this->get('/files/materials/slides.pdf')->assertRedirect(route('login'));
    }

    public function test_material_is_served_to_enrollees_owner_and_admin_only(): void
    {
        $this->material();
        $url = ProtectedFiles::url('materials/slides.pdf');

        $this->assertStringEndsWith('/files/materials/slides.pdf', $url);
        $this->actingAs($this->learner())->get($url)->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('inline', $this->actingAs($this->learner())->get($url)->headers->get('Content-Disposition'));
        $this->actingAs($this->instructor)->get($url)->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get($url)->assertOk();

        $this->actingAs($this->learner(enrolled: false))->get($url)->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'instructor']))->get($url)->assertNotFound();
    }

    public function test_a_file_no_record_points_at_is_never_served(): void
    {
        Storage::disk('local')->put('materials/orphan.pdf', 'x');

        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/files/materials/orphan.pdf')->assertNotFound();
    }

    public function test_path_traversal_is_rejected(): void
    {
        $this->material();

        $this->actingAs($this->learner())->get('/files/materials/../../../.env')->assertNotFound();
        $this->actingAs($this->learner())->get('/files/materials/..%2F..%2F.env')->assertNotFound();
    }

    public function test_assignment_submission_is_visible_to_its_author_and_grader_only(): void
    {
        $section = CourseSection::create(['course_id' => $this->course->id, 'title' => 'Bölüm 1']);
        $activity = Activity::create(['course_section_id' => $section->id, 'title' => 'Ödev', 'type' => 'assignment', 'submission_type' => 'file', 'pass_score' => 50]);
        $author = $this->learner();
        $classmate = $this->learner();

        $this->actingAs($author)->post(route('activities.submissions.store', $activity), ['file' => UploadedFile::fake()->create('odev.pdf', 10, 'application/pdf')])->assertRedirect();
        $path = AssignmentSubmission::firstOrFail()->file_path;

        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
        $this->actingAs($author)->get(ProtectedFiles::url($path))->assertOk();
        $this->actingAs($this->instructor)->get(ProtectedFiles::url($path))->assertOk();
        $this->actingAs($classmate)->get(ProtectedFiles::url($path))->assertNotFound();
    }

    public function test_recordings_and_old_material_versions_are_for_the_course_owner_only(): void
    {
        $material = $this->material();
        Storage::disk('local')->put('recordings/class.webm', 'video');
        Storage::disk('local')->put('materials/old.pdf', '%PDF-1.4');
        $material->session->update(['recording_path' => 'recordings/class.webm']);
        MaterialVersion::create(['material_id' => $material->id, 'title' => 'Old', 'type' => 'application/pdf', 'path' => 'materials/old.pdf', 'replaced_by' => $this->instructor->id]);
        $learner = $this->learner();

        foreach (['recordings/class.webm', 'materials/old.pdf'] as $path) {
            $this->actingAs($this->instructor)->get(ProtectedFiles::url($path))->assertOk();
            $this->actingAs($learner)->get(ProtectedFiles::url($path))->assertNotFound();
        }
    }

    public function test_shared_class_files_land_on_the_private_disk_and_return_an_access_checked_url(): void
    {
        $session = $this->course->sessions()->create(['title' => 'Canlı', 'room_key' => 'room-1', 'meeting_status' => 'scheduled']);

        $response = $this->actingAs($this->instructor)->postJson(route('sessions.files.share', $session), ['file' => UploadedFile::fake()->create('notlar.pdf', 10, 'application/pdf')]);

        $path = Material::firstOrFail()->path;
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
        $response->assertJsonPath('url', ProtectedFiles::url($path));
    }

    public function test_learner_pages_link_to_the_access_checked_route_not_public_storage(): void
    {
        $material = $this->material();

        $this->actingAs($this->learner())->get(route('materials.view', $material))
            ->assertOk()
            ->assertSee(ProtectedFiles::url('materials/slides.pdf'), false)
            ->assertDontSee('/storage/materials/', false);
    }

    public function test_the_migration_moves_existing_files_off_the_public_disk(): void
    {
        $material = $this->material('materials/legacy.pdf');
        Storage::disk('local')->delete('materials/legacy.pdf');
        Storage::disk('public')->put('materials/legacy.pdf', '%PDF-1.4 legacy');
        Storage::disk('public')->put('course-posters/cover.jpg', 'jpeg');

        $migration = require database_path('migrations/2026_10_22_100000_move_course_files_to_private_disk.php');
        $migration->up();
        $migration->up(); // re-running is harmless

        Storage::disk('local')->assertExists($material->path);
        $this->assertSame('%PDF-1.4 legacy', Storage::disk('local')->get($material->path));
        Storage::disk('public')->assertMissing($material->path);
        Storage::disk('public')->assertExists('course-posters/cover.jpg'); // public images stay public

        $migration->down();
        Storage::disk('public')->assertExists($material->path);
        Storage::disk('local')->assertMissing($material->path);
    }
}
