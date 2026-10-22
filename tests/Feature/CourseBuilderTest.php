<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Material;
use App\Models\MaterialProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P0 Course Builder (§15): instructor-facing section/activity management +
 * drag-and-drop reordering, scoped to courses the instructor actually owns
 * (reuses CoursePolicy::update -- the same rule Filament and the rest of the
 * instructor panel already enforce). Quiz question authoring stays
 * admin-only; this only manages the section/activity shell.
 */
class CourseBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_owning_instructor_can_add_a_section(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);

        $response = $this->actingAs($instructor)->post(route('builder.sections.store', $course), ['title' => 'Yeni Bölüm']);

        $response->assertRedirect();
        $this->assertDatabaseHas('course_sections', ['course_id' => $course->id, 'title' => 'Yeni Bölüm']);
    }

    public function test_a_non_owning_instructor_cannot_add_a_section(): void
    {
        $owner = User::factory()->create(['role' => 'instructor']);
        $other = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $owner->id]);

        $this->actingAs($other)->post(route('builder.sections.store', $course), ['title' => 'İzinsiz Bölüm'])->assertForbidden();
        $this->assertDatabaseMissing('course_sections', ['title' => 'İzinsiz Bölüm']);
    }

    public function test_admin_can_add_a_section_to_any_course(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create();

        $this->actingAs($admin)->post(route('builder.sections.store', $course), ['title' => 'Admin Bölümü'])->assertRedirect();
        $this->assertDatabaseHas('course_sections', ['title' => 'Admin Bölümü']);
    }

    public function test_owning_instructor_can_update_a_section(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Eski Ad']);

        $this->actingAs($instructor)->post(route('builder.sections.update', $section), ['title' => 'Yeni Ad']);

        $this->assertDatabaseHas('course_sections', ['id' => $section->id, 'title' => 'Yeni Ad']);
    }

    public function test_owning_instructor_can_delete_a_section(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Silinecek']);

        $this->actingAs($instructor)->delete(route('builder.sections.destroy', $section));

        $this->assertDatabaseMissing('course_sections', ['id' => $section->id]);
    }

    public function test_owning_instructor_can_add_an_activity_to_their_section(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);

        $response = $this->actingAs($instructor)->post(route('builder.activities.store', $section), ['title' => 'Yeni Video', 'type' => 'video', 'is_required' => '1']);

        $response->assertRedirect();
        $this->assertDatabaseHas('activities', ['course_section_id' => $section->id, 'title' => 'Yeni Video', 'type' => 'video', 'is_required' => 1]);
    }

    public function test_cannot_add_a_quiz_activity_through_the_builder(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);

        $response = $this->actingAs($instructor)->post(route('builder.activities.store', $section), ['title' => 'Sızma Sınavı', 'type' => 'quiz']);

        $response->assertSessionHasErrors('type');
    }

    public function test_owning_instructor_can_delete_an_activity(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);
        $activity = Activity::create(['course_section_id' => $section->id, 'title' => 'Silinecek Video', 'type' => 'video', 'is_required' => true]);

        $this->actingAs($instructor)->delete(route('builder.activities.destroy', $activity));

        $this->assertDatabaseMissing('activities', ['id' => $activity->id]);
    }

    public function test_reordering_sections_persists_the_new_sort_order(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $first = CourseSection::create(['course_id' => $course->id, 'title' => 'A', 'sort_order' => 1]);
        $second = CourseSection::create(['course_id' => $course->id, 'title' => 'B', 'sort_order' => 2]);

        $response = $this->actingAs($instructor)->postJson(route('builder.sections.reorder', $course), ['ids' => [$second->id, $first->id]]);

        $response->assertOk();
        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);
    }

    public function test_reorder_cannot_smuggle_in_a_section_from_another_course(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $ownSection = CourseSection::create(['course_id' => $course->id, 'title' => 'Kendi Bölümüm', 'sort_order' => 1]);
        $otherCourse = Course::factory()->create();
        $foreignSection = CourseSection::create(['course_id' => $otherCourse->id, 'title' => 'Başkasının Bölümü', 'sort_order' => 5]);

        $this->actingAs($instructor)->postJson(route('builder.sections.reorder', $course), ['ids' => [$foreignSection->id, $ownSection->id]]);

        $this->assertSame(5, $foreignSection->fresh()->sort_order, 'A foreign section must not be reassigned an order by another instructor.');
    }

    public function test_reordering_activities_persists_the_new_sort_order(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);
        $first = Activity::create(['course_section_id' => $section->id, 'title' => 'A', 'type' => 'video', 'is_required' => true, 'sort_order' => 1]);
        $second = Activity::create(['course_section_id' => $section->id, 'title' => 'B', 'type' => 'video', 'is_required' => true, 'sort_order' => 2]);

        $response = $this->actingAs($instructor)->postJson(route('builder.activities.reorder', $section), ['ids' => [$second->id, $first->id]]);

        $response->assertOk();
        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);
    }

    public function test_course_detail_page_shows_the_builder_ui(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        CourseSection::create(['course_id' => $course->id, 'title' => 'Görünür Bölüm']);

        $response = $this->actingAs($instructor)->get(route('instructor.courses.show', $course));

        $response->assertOk();
        $response->assertSee('Görünür Bölüm');
        $response->assertSee('+ Bölüm ekle');
    }

    /**
     * Drag-and-drop is mouse-only; §31 (accessibility) requires a
     * keyboard-operable equivalent (WCAG 2.5.7) -- the ▲▼ buttons rendered
     * next to each draggable section/activity.
     */
    public function test_reorder_buttons_are_present_as_a_keyboard_alternative_to_drag_and_drop(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);
        Activity::create(['course_section_id' => $section->id, 'title' => 'Video 1', 'type' => 'video', 'is_required' => true]);

        $response = $this->actingAs($instructor)->get(route('instructor.courses.show', $course));

        $response->assertOk();
        $response->assertSee('Bölümü yukarı taşı');
        $response->assertSee('Etkinliği yukarı taşı');
    }

    public function test_owning_instructor_can_rename_a_material(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $material = Material::create(['course_session_id' => $session->id, 'title' => 'Eski Ad', 'type' => 'application/pdf', 'path' => 'demo/x.pdf']);

        $this->actingAs($instructor)->post(route('builder.materials.update', $material), ['title' => 'Yeni Ad'])->assertRedirect();

        $this->assertDatabaseHas('materials', ['id' => $material->id, 'title' => 'Yeni Ad']);
    }

    /**
     * P1 material versioning: replacing a material's file must not destroy
     * the old one -- a snapshot of the pre-replacement path/title lands in
     * material_versions, and the material row itself keeps material_id
     * (and therefore any learner's MaterialProgress) unchanged.
     */
    public function test_replacing_a_materials_file_snapshots_the_old_one_as_a_version(): void
    {
        Storage::fake('local');
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $material = Material::create(['course_session_id' => $session->id, 'title' => 'Slaytlar', 'type' => 'application/pdf', 'path' => 'materials/old.pdf']);

        $response = $this->actingAs($instructor)->post(route('builder.materials.update', $material), [
            'title' => 'Slaytlar',
            'file' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('material_versions', ['material_id' => $material->id, 'path' => 'materials/old.pdf', 'title' => 'Slaytlar', 'replaced_by' => $instructor->id]);
        $material->refresh();
        $this->assertNotSame('materials/old.pdf', $material->path);
    }

    public function test_a_learners_progress_survives_a_material_file_replacement(): void
    {
        Storage::fake('local');
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'status' => 'published']);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $material = Material::create(['course_session_id' => $session->id, 'title' => 'Slaytlar', 'type' => 'application/pdf', 'path' => 'materials/old.pdf']);
        $learner = User::factory()->create(['role' => 'learner']);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);
        $progress = MaterialProgress::create(['enrollment_id' => $enrollment->id, 'material_id' => $material->id, 'completed' => true, 'completed_at' => now()]);

        $this->actingAs($instructor)->post(route('builder.materials.update', $material), [
            'title' => 'Slaytlar',
            'file' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf'),
        ]);

        $this->assertDatabaseHas('material_progress', ['id' => $progress->id, 'material_id' => $material->id, 'completed' => 1]);
    }

    public function test_updating_a_materials_title_only_does_not_create_a_version(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $material = Material::create(['course_session_id' => $session->id, 'title' => 'Eski Ad', 'type' => 'application/pdf', 'path' => 'demo/x.pdf']);

        $this->actingAs($instructor)->post(route('builder.materials.update', $material), ['title' => 'Yeni Ad']);

        $this->assertSame(0, $material->versions()->count());
    }

    public function test_a_non_owning_instructor_cannot_rename_a_material(): void
    {
        $owner = User::factory()->create(['role' => 'instructor']);
        $other = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $owner->id]);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $material = Material::create(['course_session_id' => $session->id, 'title' => 'Eski Ad', 'type' => 'application/pdf', 'path' => 'demo/x.pdf']);

        $this->actingAs($other)->post(route('builder.materials.update', $material), ['title' => 'İzinsiz Ad'])->assertForbidden();

        $this->assertDatabaseHas('materials', ['id' => $material->id, 'title' => 'Eski Ad']);
    }

    public function test_owning_instructor_can_delete_a_material(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $material = Material::create(['course_session_id' => $session->id, 'title' => 'Silinecek', 'type' => 'application/pdf', 'path' => 'demo/x.pdf']);

        $this->actingAs($instructor)->delete(route('builder.materials.destroy', $material));

        $this->assertDatabaseMissing('materials', ['id' => $material->id]);
    }

    /**
     * The Log view has to answer two different questions in one place: who
     * actually consumed the content (material_progress/activity_progress --
     * real learner access, not a mutation) and what was done to the content
     * itself (AuditLog rows written by the Auditable trait on Course/
     * CourseSection/Activity/Material). This test seeds one of each and
     * checks both show up.
     */
    public function test_course_log_shows_learner_access_and_the_audit_trail(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);
        $activity = Activity::create(['course_section_id' => $section->id, 'title' => 'Video 1', 'type' => 'video', 'is_required' => true]);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $material = Material::create(['course_session_id' => $session->id, 'title' => 'El Kitabı', 'type' => 'application/pdf', 'path' => 'demo/x.pdf']);
        $learner = User::factory()->create(['role' => 'learner', 'name' => 'Kayıt İzleyen Öğrenci']);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);
        $enrollment->activityProgress()->create(['activity_id' => $activity->id, 'watched_seconds' => 30, 'completed' => true, 'completed_at' => now()]);
        $enrollment->progress()->create(['material_id' => $material->id, 'watched_seconds' => 10, 'completed' => false]);

        $response = $this->actingAs($instructor)->get(route('instructor.courses.log', $course));

        $response->assertOk();
        $response->assertSee('Kayıt İzleyen Öğrenci');
        $response->assertSee('Video 1');
        $response->assertSee('El Kitabı');
        // CourseSection uses the Auditable trait, so creating it above must
        // have written a 'created' AuditLog row that shows up here too.
        $response->assertSee('Bölüm 1');
    }

    public function test_a_non_owning_instructor_cannot_view_another_instructors_course_log(): void
    {
        $owner = User::factory()->create(['role' => 'instructor']);
        $other = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $owner->id]);

        $this->actingAs($other)->get(route('instructor.courses.log', $course))->assertForbidden();
    }
}
