<?php

namespace Tests\Feature;

use App\Filament\Resources\Activities\Pages\EditActivity;
use App\Filament\Resources\Activities\RelationManagers\QuizQuestionsRelationManager;
use App\Filament\Resources\Activities\RelationManagers\RubricCriteriaRelationManager;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every Filament resource's list page must render for an admin. This is a
     * direct regression guard for the "intl extension missing -> every list
     * page 500s" incident: Filament's table/pagination views call Number::format(),
     * which throws a fatal error without ext-intl loaded, even though the app
     * itself never touches that extension anywhere else.
     */
    public function test_every_admin_resource_index_page_renders_for_an_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $paths = [
            '/admin',
            '/admin/activities',
            '/admin/api-tokens',
            '/admin/audit-logs',
            '/admin/certificates',
            '/admin/course-runs',
            '/admin/course-sections',
            '/admin/course-sessions',
            '/admin/courses',
            '/admin/enrollments',
            '/admin/learning-paths',
            '/admin/materials',
            '/admin/organization-units',
            '/admin/scim-tokens',
            '/admin/skills',
            '/admin/users',
            '/admin/webhook-subscriptions',
        ];

        foreach ($paths as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }

        // Portal settings is a singleton: the index redirects straight to the
        // one real settings record instead of listing/allowing extra rows.
        $this->actingAs($admin)->get('/admin/portal-settings')->assertRedirect();
    }

    public function test_every_admin_resource_create_page_renders_for_an_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $paths = [
            '/admin/activities/create',
            '/admin/api-tokens/create',
            '/admin/course-runs/create',
            '/admin/course-sections/create',
            '/admin/course-sessions/create',
            '/admin/courses/create',
            '/admin/enrollments/create',
            '/admin/learning-paths/create',
            '/admin/materials/create',
            '/admin/organization-units/create',
            '/admin/scim-tokens/create',
            '/admin/skills/create',
            '/admin/users/create',
            '/admin/webhook-subscriptions/create',
        ];

        foreach ($paths as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    public function test_course_edit_page_renders_with_all_relation_managers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create();

        $response = $this->actingAs($admin)->get("/admin/courses/{$course->id}/edit");

        $response->assertOk();
        $response->assertSee('Seanslar');
        $response->assertSee('Katılımcılar');
    }

    public function test_every_admin_resource_edit_page_renders_with_a_real_record(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create();
        $section = $course->sections()->create(['title' => 'Bölüm 1']);
        $activity = $section->activities()->create(['title' => 'Video', 'type' => 'video', 'is_required' => true]);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $material = Material::create(['course_session_id' => $session->id, 'title' => 'Slaytlar', 'type' => 'application/pdf', 'path' => 'demo/slides.pdf']);
        $run = $course->runs()->create(['name' => 'Mart 2026', 'code' => 'RUN-SMOKE-1', 'status' => 'open']);
        $learner = User::factory()->create(['role' => 'learner']);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $paths = [
            "/admin/activities/{$activity->id}/edit",
            "/admin/course-runs/{$run->id}/edit",
            "/admin/course-sections/{$section->id}/edit",
            "/admin/course-sessions/{$session->id}/edit",
            "/admin/enrollments/{$enrollment->id}/edit",
            "/admin/materials/{$material->id}/edit",
            "/admin/users/{$learner->id}/edit",
        ];

        foreach ($paths as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    /**
     * The quiz-question relation manager only appears for type === 'quiz'
     * activities (canViewForRecord) -- the other edit-page smoke test above
     * only ever exercises a 'video' activity, so it can't catch a rendering
     * bug in the quiz question/option authoring UI. This is the one place
     * that actually loads a quiz activity's edit page.
     */
    public function test_quiz_activity_edit_page_renders_the_quiz_questions_relation_manager(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create();
        $section = $course->sections()->create(['title' => 'Bölüm 1']);
        $quiz = $section->activities()->create(['title' => 'Değerlendirme Sınavı', 'type' => 'quiz', 'is_required' => true]);
        $question = $quiz->quizQuestions()->create(['question' => 'Arucad Campus LMS nedir?', 'type' => 'single_choice', 'sort_order' => 1]);
        $question->options()->createMany([
            ['label' => 'Bir öğrenme yönetim sistemi', 'is_correct' => true, 'sort_order' => 1],
            ['label' => 'Bir muhasebe programı', 'is_correct' => false, 'sort_order' => 2],
        ]);

        $response = $this->actingAs($admin)->get("/admin/activities/{$quiz->id}/edit");

        // With only one relation manager registered on this record, Filament
        // renders its table directly instead of a tab strip, so the $title
        // label itself is not guaranteed to appear as visible text -- assert
        // on the actual rendered question data instead.
        $response->assertOk();

        // The relation manager's table itself is lazy-loaded (Filament mounts
        // it behind an x-intersect placeholder, "Yükleniyor..."), so its rows
        // never appear in this plain GET's HTML regardless of whether the
        // manager works -- that has to be exercised as its own Livewire
        // component instead.
        Livewire::actingAs($admin)->test(QuizQuestionsRelationManager::class, [
            'ownerRecord' => $quiz,
            'pageClass' => EditActivity::class,
        ])->assertCanSeeTableRecords([$question]);
    }

    /**
     * Same lazy-loading caveat as the quiz relation manager above -- and the
     * rubric criteria manager only appears for type === 'assignment'.
     */
    public function test_assignment_activity_edit_page_renders_the_rubric_criteria_relation_manager(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create();
        $section = $course->sections()->create(['title' => 'Bölüm 1']);
        $assignment = $section->activities()->create(['title' => 'Dönem Ödevi', 'type' => 'assignment', 'submission_type' => 'text', 'is_required' => true]);
        $criterion = $assignment->rubricCriteria()->create(['label' => 'İçerik kalitesi', 'max_points' => 50, 'sort_order' => 1]);

        $this->actingAs($admin)->get("/admin/activities/{$assignment->id}/edit")->assertOk();

        Livewire::actingAs($admin)->test(RubricCriteriaRelationManager::class, [
            'ownerRecord' => $assignment,
            'pageClass' => EditActivity::class,
        ])->assertCanSeeTableRecords([$criterion]);
    }

    /**
     * Filament ships bundled Turkish translations for its own chrome, but the
     * admin panel's middleware stack never called App::setLocale() -- so the
     * session-based locale toggle that already worked for the public site had
     * zero effect inside /admin. This verifies the fix (SetLocale registered
     * on the panel) actually changes Filament's own rendered strings, not just
     * that the session value changes.
     */
    public function test_admin_panel_locale_switch_changes_filaments_own_chrome(): void
    {
        // The app defaults to Turkish everywhere (SetLocale's own fallback),
        // so the admin panel should render Turkish out of the box too, now
        // that SetLocale actually runs on the panel's middleware stack.
        $turkish = $this->get('/admin/login');
        $turkish->assertOk();
        $turkish->assertSee('Oturum Aç');

        $english = $this->withSession(['locale' => 'en'])->get('/admin/login');
        $english->assertOk();
        $english->assertSee('Sign in');
    }

    public function test_non_admin_cannot_access_the_admin_panel(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $instructor = User::factory()->create(['role' => 'instructor']);

        $this->actingAs($learner)->get('/admin')->assertForbidden();
        $this->actingAs($instructor)->get('/admin')->assertForbidden();
    }
}
