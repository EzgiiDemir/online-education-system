<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * P0 file-upload hardening: session materials, class-shared files, and
 * assignment submissions used to accept any file type up to a size limit
 * with no content check at all -- an .exe, .php, or script-bearing .html
 * uploaded as "course material" would land on the public disk and get
 * served to whoever later opened it. Laravel's `mimes` rule checks the
 * file's actually-detected content type, not just its name, so a
 * relabeled executable is rejected here rather than merely by extension.
 */
class FileUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_dangerous_file_is_rejected_as_a_session_material(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);

        $response = $this->actingAs($instructor)->post(route('instructor.sessions.store', $course), [
            'title' => 'Seans 1',
            'material' => UploadedFile::fake()->create('payload.exe', 100, 'application/x-msdownload'),
        ]);

        $response->assertSessionHasErrors('material');
        $this->assertDatabaseMissing('course_sessions', ['title' => 'Seans 1']);
    }

    public function test_a_legitimate_pdf_session_material_is_accepted(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);

        $response = $this->actingAs($instructor)->post(route('instructor.sessions.store', $course), [
            'title' => 'Seans 1',
            'material' => UploadedFile::fake()->create('handout.pdf', 100, 'application/pdf'),
        ]);

        $response->assertSessionDoesntHaveErrors('material');
        $this->assertDatabaseHas('course_sessions', ['title' => 'Seans 1']);
    }

    public function test_a_dangerous_file_is_rejected_when_shared_in_a_live_class(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $session = $course->sessions()->create(['title' => 'Seans 1']);

        $response = $this->actingAs($instructor)->post(route('sessions.files.share', $session), [
            'file' => UploadedFile::fake()->create('payload.exe', 100, 'application/x-msdownload'),
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('materials', ['course_session_id' => $session->id]);
    }

    public function test_a_dangerous_file_is_rejected_as_an_assignment_submission(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['status' => 'published', 'instructor_id' => $instructor->id]);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);
        $activity = Activity::create(['course_section_id' => $section->id, 'title' => 'Ödev', 'type' => 'assignment', 'submission_type' => 'file', 'is_required' => true]);
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->post(route('activities.submissions.store', $activity), [
            'file' => UploadedFile::fake()->create('payload.exe', 100, 'application/x-msdownload'),
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('assignment_submissions', ['activity_id' => $activity->id]);
    }
}
