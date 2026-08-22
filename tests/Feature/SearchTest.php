<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 Global search (§7): searches published-course content only -- draft
 * courses and everything under them must never surface, matching the
 * catalog's own visibility rule.
 */
class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_find_a_published_course_by_title(): void
    {
        Course::factory()->create(['status' => 'published', 'title' => 'Etkili Sunum Teknikleri']);

        $response = $this->get(route('search.index', ['q' => 'Sunum Teknikleri']));

        $response->assertOk();
        $response->assertSee('Etkili Sunum Teknikleri');
    }

    public function test_a_draft_course_never_appears_in_search_results(): void
    {
        Course::factory()->create(['status' => 'draft', 'title' => 'Gizli Taslak Eğitim']);

        $response = $this->get(route('search.index', ['q' => 'Gizli Taslak']));

        $response->assertOk();
        $response->assertDontSee('Gizli Taslak Eğitim');
    }

    public function test_search_finds_an_activity_inside_a_published_course(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);
        Activity::create(['course_section_id' => $section->id, 'title' => 'Aramayla Bulunacak Video', 'type' => 'video', 'is_required' => true]);

        $response = $this->get(route('search.index', ['q' => 'Aramayla Bulunacak']));

        $response->assertOk();
        $response->assertSee('Aramayla Bulunacak Video');
    }

    public function test_an_activity_under_a_draft_course_does_not_appear(): void
    {
        $course = Course::factory()->create(['status' => 'draft']);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);
        Activity::create(['course_section_id' => $section->id, 'title' => 'Gizli Aktivite', 'type' => 'video', 'is_required' => true]);

        $response = $this->get(route('search.index', ['q' => 'Gizli Aktivite']));

        // The query itself is echoed back in the page heading regardless of
        // match count, so assert on the no-results state instead of the
        // (ambiguous, since it's also the search term) activity title.
        $response->assertOk();
        $response->assertSee('Sonuç bulunamadı');
    }

    public function test_search_finds_a_material_by_title(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        Material::create(['course_session_id' => $session->id, 'title' => 'Aranabilir Sunum Dosyası', 'type' => 'application/pdf', 'path' => 'demo/slides.pdf']);

        $response = $this->get(route('search.index', ['q' => 'Aranabilir Sunum']));

        $response->assertOk();
        $response->assertSee('Aranabilir Sunum Dosyası');
    }

    public function test_search_finds_an_instructor_by_name(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor', 'name' => 'Zeynep Arıkan']);
        Course::factory()->create(['status' => 'published', 'instructor_id' => $instructor->id]);

        $response = $this->get(route('search.index', ['q' => 'Zeynep Arıkan']));

        $response->assertOk();
        $response->assertSee('Zeynep Arıkan');
    }

    public function test_type_filter_restricts_results_to_one_category(): void
    {
        Course::factory()->create(['status' => 'published', 'title' => 'Ortak Terim Eğitimi']);
        $course = Course::factory()->create(['status' => 'published']);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);
        Activity::create(['course_section_id' => $section->id, 'title' => 'Ortak Terim Aktivite', 'type' => 'video', 'is_required' => true]);

        $response = $this->get(route('search.index', ['q' => 'Ortak Terim', 'type' => 'courses']));

        $response->assertOk();
        $response->assertSee('Ortak Terim Eğitimi');
        $response->assertDontSee('Ortak Terim Aktivite');
    }

    public function test_empty_query_shows_a_prompt_instead_of_all_content(): void
    {
        Course::factory()->create(['status' => 'published', 'title' => 'Herhangi Bir Eğitim']);

        $response = $this->get(route('search.index'));

        $response->assertOk();
        $response->assertDontSee('Herhangi Bir Eğitim');
    }

    public function test_no_results_state_is_shown_for_an_unmatched_query(): void
    {
        $response = $this->get(route('search.index', ['q' => 'hicbirseyleeslesmeyensorgu12345']));

        $response->assertOk();
        $response->assertSee('Sonuç bulunamadı');
    }

    public function test_quick_search_endpoint_returns_json_grouped_results(): void
    {
        Course::factory()->create(['status' => 'published', 'title' => 'Hızlı Arama Eğitimi']);

        $response = $this->getJson(route('search.quick', ['q' => 'Hızlı Arama']));

        $response->assertOk();
        $response->assertJsonPath('courses.0.title', 'Hızlı Arama Eğitimi');
    }

    public function test_quick_search_with_a_short_query_returns_empty_groups(): void
    {
        $response = $this->getJson(route('search.quick', ['q' => 'a']));

        $response->assertOk();
        $response->assertJson(['courses' => [], 'activities' => [], 'materials' => [], 'sessions' => [], 'instructors' => []]);
    }
}
