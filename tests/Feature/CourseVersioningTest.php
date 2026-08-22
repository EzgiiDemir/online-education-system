<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 Course Versioning (§29). The single hard requirement from the spec is
 * explicit: never accidentally mutate old completed training records.
 *
 * Design (see Course::createNewVersion() doc comment): a "new version" is a
 * brand-new Course row forked from the original, never an in-place edit of
 * the existing row. Old enrollments/certificates keep their original
 * course_id forever -- they are structurally incapable of being affected by
 * a later version being created, since nothing about the original row ever
 * changes. This test suite exists specifically to prove that property, not
 * just that the feature "works".
 */
class CourseVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_course_defaults_to_version_1_with_no_lineage(): void
    {
        $course = Course::factory()->create();

        $this->assertSame(1, $course->version);
        $this->assertNull($course->version_group_id);
    }

    public function test_enrolling_snapshots_the_courses_current_version(): void
    {
        $course = Course::factory()->create(['status' => 'published', 'version' => 1]);
        $learner = User::factory()->create();

        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $this->assertSame(1, $enrollment->course_version);
    }

    public function test_creating_a_new_version_produces_a_separate_course_row(): void
    {
        $original = Course::factory()->create(['title' => 'Güvenlik Eğitimi', 'version' => 1]);

        $v2 = $original->createNewVersion();

        $this->assertNotSame($original->id, $v2->id);
        $this->assertSame(2, $v2->version);
        $this->assertSame($original->id, $v2->version_group_id);
        $this->assertSame('draft', $v2->status, 'A forked version must start as draft, never silently published.');
    }

    public function test_creating_a_new_version_copies_the_curriculum(): void
    {
        $original = Course::factory()->create();
        $section = CourseSection::create(['course_id' => $original->id, 'title' => 'Bölüm 1', 'sort_order' => 1]);
        Activity::create(['course_section_id' => $section->id, 'title' => 'Giriş Videosu', 'type' => 'video', 'is_required' => true, 'sort_order' => 1]);

        $v2 = $original->createNewVersion();

        $this->assertCount(1, $v2->sections);
        $this->assertSame('Bölüm 1', $v2->sections->first()->title);
        $this->assertCount(1, $v2->sections->first()->activities);
        $this->assertSame('Giriş Videosu', $v2->sections->first()->activities->first()->title);
    }

    /**
     * The core safety property (§29's explicit requirement): forking a new
     * version must not touch the original row, its enrollments, or its
     * certificates in any way.
     */
    public function test_creating_a_new_version_does_not_alter_the_original_courses_completed_enrollments(): void
    {
        $original = Course::factory()->create(['title' => 'Uyumluluk Eğitimi', 'version' => 1]);
        $learner = User::factory()->create();
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $original->id, 'status' => 'completed', 'completed_at' => now()]);
        $certificate = Certificate::create(['enrollment_id' => $enrollment->id, 'certificate_number' => 'CERT-VER01', 'issued_at' => now()]);

        $original->createNewVersion();

        $enrollment->refresh();
        $certificate->refresh();
        $original->refresh();
        $this->assertSame($original->id, $enrollment->course_id, 'The completed enrollment must keep pointing at the original course row.');
        $this->assertSame(1, $enrollment->course_version);
        $this->assertSame('completed', $enrollment->status);
        $this->assertNotNull($certificate->issued_at);
        $this->assertSame(1, $original->version, 'The original row\'s own version number must never change.');
    }

    public function test_enrolling_in_a_new_version_after_it_is_published_creates_a_separate_enrollment_row(): void
    {
        $original = Course::factory()->create(['status' => 'published']);
        $learner = User::factory()->create();
        $oldEnrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $original->id, 'status' => 'completed', 'completed_at' => now()]);

        $v2 = $original->createNewVersion();
        $v2->update(['status' => 'published']);
        $newEnrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $v2->id]);

        $this->assertNotSame($oldEnrollment->id, $newEnrollment->id);
        $this->assertSame(1, $oldEnrollment->fresh()->course_version);
        $this->assertSame(2, $newEnrollment->course_version);
        $this->assertSame('completed', $oldEnrollment->fresh()->status, 'Old completion must be untouched by the new enrollment.');
    }

    public function test_course_show_page_advertises_a_newer_published_version(): void
    {
        $original = Course::factory()->create(['status' => 'published', 'title' => 'Eski Sürüm Eğitimi']);
        $v2 = $original->createNewVersion();
        $v2->update(['status' => 'published']);

        $response = $this->get(route('courses.show', $original));

        $response->assertOk();
        $response->assertSee('v2');
    }

    public function test_course_show_page_does_not_advertise_an_unpublished_draft_version(): void
    {
        $original = Course::factory()->create(['status' => 'published']);
        $original->createNewVersion(); // stays draft

        $response = $this->get(route('courses.show', $original));

        $response->assertOk();
        $response->assertDontSee('daha yeni bir sürümü mevcut');
    }

    public function test_admin_can_create_a_new_version_from_the_admin_panel(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create();

        $response = $this->actingAs($admin)->get("/admin/courses/{$course->id}/edit");

        $response->assertOk();
        $response->assertSee('Yeni sürüm oluştur');
    }
}
