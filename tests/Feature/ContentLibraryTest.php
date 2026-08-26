<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz 96 (spec §11): a searchable, filterable browser over everything an
 * instructor has already uploaded across ALL of their courses -- not the
 * "reusable content" cross-course attachment part of the spec, which is a
 * separate, larger architectural change (see contentLibrary()'s docblock).
 */
class ContentLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_content_from_the_instructors_own_courses_only(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $other = User::factory()->create(['role' => 'instructor']);
        $ownCourse = Course::factory()->create(['instructor_id' => $instructor->id]);
        $otherCourse = Course::factory()->create(['instructor_id' => $other->id]);
        $ownSection = CourseSection::create(['course_id' => $ownCourse->id, 'title' => 'Bölüm 1']);
        $otherSection = CourseSection::create(['course_id' => $otherCourse->id, 'title' => 'Bölüm 1']);
        $ownSection->activities()->create(['title' => 'Kendi Videom', 'type' => 'video', 'is_required' => true]);
        $otherSection->activities()->create(['title' => 'Başkasının Videosu', 'type' => 'video', 'is_required' => true]);

        $response = $this->actingAs($instructor)->get(route('instructor.content-library'));

        $response->assertOk();
        $response->assertSee('Kendi Videom');
        $response->assertDontSee('Başkasının Videosu');
    }

    public function test_search_filters_by_title(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);
        $section->activities()->create(['title' => 'Pazarlama Temelleri', 'type' => 'document', 'is_required' => true]);
        $section->activities()->create(['title' => 'Finans Girişi', 'type' => 'document', 'is_required' => true]);

        $response = $this->actingAs($instructor)->get(route('instructor.content-library', ['q' => 'Pazarlama']));

        $response->assertOk();
        $response->assertSee('Pazarlama Temelleri');
        $response->assertDontSee('Finans Girişi');
    }

    public function test_type_filter_shows_only_that_type(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);
        $section->activities()->create(['title' => 'Bir Quiz', 'type' => 'quiz', 'is_required' => true]);
        $section->activities()->create(['title' => 'Bir Video', 'type' => 'video', 'is_required' => true]);

        $response = $this->actingAs($instructor)->get(route('instructor.content-library', ['type' => 'quiz']));

        $response->assertOk();
        $response->assertSee('Bir Quiz');
        $response->assertDontSee('Bir Video');
    }

    public function test_course_filter_narrows_to_one_course(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $courseA = Course::factory()->create(['instructor_id' => $instructor->id, 'title' => 'A Eğitimi']);
        $courseB = Course::factory()->create(['instructor_id' => $instructor->id, 'title' => 'B Eğitimi']);
        $sessionA = $courseA->sessions()->create(['title' => 'Seans A']);
        $sessionB = $courseB->sessions()->create(['title' => 'Seans B']);
        Material::create(['course_session_id' => $sessionA->id, 'title' => 'A Materyali', 'type' => 'application/pdf', 'path' => 'demo/a.pdf']);
        Material::create(['course_session_id' => $sessionB->id, 'title' => 'B Materyali', 'type' => 'application/pdf', 'path' => 'demo/b.pdf']);

        $response = $this->actingAs($instructor)->get(route('instructor.content-library', ['course_id' => $courseA->id]));

        $response->assertOk();
        $response->assertSee('A Materyali');
        $response->assertDontSee('B Materyali');
    }

    public function test_a_live_session_appears_as_content(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $course->sessions()->create(['title' => 'Canlı Ders Seansı']);

        $response = $this->actingAs($instructor)->get(route('instructor.content-library'));

        $response->assertOk();
        $response->assertSee('Canlı Ders Seansı');
    }

    public function test_a_learner_cannot_access_the_content_library(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);

        $this->actingAs($learner)->get(route('instructor.content-library'))->assertForbidden();
    }

    public function test_the_content_library_link_appears_in_the_instructor_navigation(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);

        $response = $this->actingAs($instructor)->get(route('instructor.dashboard'));

        $response->assertOk();
        $response->assertSee(route('instructor.content-library'), false);
    }

    public function test_an_instructor_can_export_their_content_library_as_csv(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'title' => 'Dışa Aktarma Testi']);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);
        $section->activities()->create(['title' => 'Dışa Aktarılacak Video', 'type' => 'video', 'is_required' => true]);

        $response = $this->actingAs($instructor)->get(route('instructor.content-library.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Dışa Aktarılacak Video', $response->streamedContent());
        $this->assertStringContainsString('Dışa Aktarma Testi', $response->streamedContent());
    }

    public function test_content_library_export_respects_the_course_filter(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $courseA = Course::factory()->create(['instructor_id' => $instructor->id, 'title' => 'Eğitim A']);
        $courseB = Course::factory()->create(['instructor_id' => $instructor->id, 'title' => 'Eğitim B']);
        CourseSection::create(['course_id' => $courseA->id, 'title' => 'Bölüm 1'])->activities()->create(['title' => 'A İçeriği', 'type' => 'video', 'is_required' => true]);
        CourseSection::create(['course_id' => $courseB->id, 'title' => 'Bölüm 1'])->activities()->create(['title' => 'B İçeriği', 'type' => 'video', 'is_required' => true]);

        $response = $this->actingAs($instructor)->get(route('instructor.content-library.export', ['course_id' => $courseA->id]));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('A İçeriği', $csv);
        $this->assertStringNotContainsString('B İçeriği', $csv);
    }

    public function test_a_learner_cannot_export_the_content_library(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);

        $this->actingAs($learner)->get(route('instructor.content-library.export'))->assertForbidden();
    }
}
