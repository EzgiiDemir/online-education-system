<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            '/admin/audit-logs',
            '/admin/course-runs',
            '/admin/course-sections',
            '/admin/course-sessions',
            '/admin/courses',
            '/admin/enrollments',
            '/admin/materials',
            '/admin/skills',
            '/admin/users',
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
            '/admin/course-runs/create',
            '/admin/course-sections/create',
            '/admin/course-sessions/create',
            '/admin/courses/create',
            '/admin/enrollments/create',
            '/admin/materials/create',
            '/admin/skills/create',
            '/admin/users/create',
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
