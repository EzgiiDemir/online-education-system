<?php

namespace Tests\Feature;

use App\Models\Course;
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
            '/admin/course-runs',
            '/admin/course-sections',
            '/admin/course-sessions',
            '/admin/courses',
            '/admin/enrollments',
            '/admin/materials',
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

    public function test_non_admin_cannot_access_the_admin_panel(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $instructor = User::factory()->create(['role' => 'instructor']);

        $this->actingAs($learner)->get('/admin')->assertForbidden();
        $this->actingAs($instructor)->get('/admin')->assertForbidden();
    }
}
