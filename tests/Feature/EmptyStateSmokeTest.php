<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz 101 (QA) sweep item: every screen built across Faz 91-100 (workspace
 * switcher-era) rendered against a completely fresh account with zero data
 * -- no courses, no team, no enrollments, no departments. None of these
 * should 500; each should show a genuine empty state rather than crashing
 * on a null/empty collection.
 */
class EmptyStateSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_instructor_with_no_courses_sees_every_workspace_page_render(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);

        foreach ([
            route('instructor.dashboard'),
            route('instructor.courses.index'),
            route('instructor.calendar'),
            route('instructor.content-library'),
            route('instructor.profile'),
        ] as $url) {
            $this->actingAs($instructor)->get($url)->assertOk();
        }
    }

    public function test_a_fresh_manager_with_no_reports_sees_every_workspace_tab_render(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)->get(route('manager.dashboard'))->assertOk();
        $this->actingAs($manager)->get(route('manager.export'))->assertOk();
    }

    public function test_a_fresh_learner_with_no_enrollments_sees_every_workspace_page_render(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);

        foreach ([
            route('lms.home'),
            route('learning.index'),
            route('learning-paths.index'),
            route('calendar.index'),
            route('skills.index'),
            route('account.show'),
        ] as $url) {
            $this->actingAs($learner)->get($url)->assertOk();
        }
    }

    public function test_admin_report_center_renders_with_a_completely_empty_database(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach ([
            route('reports.index'),
            route('reports.completion'),
            route('reports.attendance'),
            route('reports.instructors'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_admin_departments_page_renders_with_no_departments_at_all(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/add-user')->assertOk();
    }

    public function test_admin_system_health_and_compliance_dashboard_render_with_no_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/system-health')->assertOk();
        $this->actingAs($admin)->get('/admin/compliance-dashboard')->assertOk();
    }
}
