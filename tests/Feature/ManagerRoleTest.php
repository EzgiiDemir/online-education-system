<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 Manager role (§18): read-only visibility into the manager's own direct
 * reports only -- no admin privileges, no visibility into other managers'
 * teams. "manager_id" on users is a self-referencing FK added this phase;
 * no separate "team" table exists.
 */
class ManagerRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_sees_their_own_team_members(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $report = User::factory()->create(['role' => 'learner', 'manager_id' => $manager->id]);
        $notReport = User::factory()->create(['role' => 'learner']);

        $response = $this->actingAs($manager)->get(route('manager.dashboard'));

        $response->assertOk();
        $response->assertSee($report->name);
        $response->assertDontSee($notReport->name);
    }

    public function test_manager_sees_teams_in_progress_enrollments(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $report = User::factory()->create(['role' => 'learner', 'manager_id' => $manager->id]);
        $course = Course::factory()->create(['status' => 'published']);
        Enrollment::create(['user_id' => $report->id, 'course_id' => $course->id, 'status' => 'enrolled']);

        $response = $this->actingAs($manager)->get(route('manager.dashboard'));

        $response->assertOk();
        $response->assertSee('Devam eden eğitimler');
        $response->assertSee($course->title);
    }

    public function test_manager_sees_teams_certificate_count(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $report = User::factory()->create(['role' => 'learner', 'manager_id' => $manager->id]);
        $course = Course::factory()->create(['status' => 'published']);
        $enrollment = Enrollment::create(['user_id' => $report->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);
        Certificate::create(['enrollment_id' => $enrollment->id, 'certificate_number' => 'CERT-MGR1', 'issued_at' => now()]);

        $response = $this->actingAs($manager)->get(route('manager.dashboard'));

        $response->assertOk();
        $response->assertSee('KAZANILAN SERTİFİKA');
    }

    /**
     * P1 richer manager dashboard: Course::isOverdueFor() already existed
     * (driving the reminder-email scheduler) but nothing surfaced it to a
     * manager -- a report could be silently overdue on required training
     * and the only visible signal was an email the manager never sees.
     */
    public function test_manager_sees_an_overdue_badge_for_a_report_past_the_due_date(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $report = User::factory()->create(['role' => 'learner', 'manager_id' => $manager->id]);
        $course = Course::factory()->create(['status' => 'published', 'due_days' => 7]);
        Enrollment::create(['user_id' => $report->id, 'course_id' => $course->id, 'status' => 'enrolled', 'created_at' => now()->subDays(10)]);

        $response = $this->actingAs($manager)->get(route('manager.dashboard'));

        $response->assertOk();
        $response->assertSee('Süresi geçti');
        $response->assertSee('SÜRESİ GEÇEN');
    }

    public function test_manager_does_not_see_an_overdue_badge_for_a_report_within_the_due_window(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $report = User::factory()->create(['role' => 'learner', 'manager_id' => $manager->id]);
        $course = Course::factory()->create(['status' => 'published', 'due_days' => 30]);
        Enrollment::create(['user_id' => $report->id, 'course_id' => $course->id, 'status' => 'enrolled', 'created_at' => now()->subDays(5)]);

        $response = $this->actingAs($manager)->get(route('manager.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Süresi geçti');
    }

    public function test_non_manager_cannot_access_the_manager_dashboard(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $instructor = User::factory()->create(['role' => 'instructor']);

        $this->actingAs($learner)->get(route('manager.dashboard'))->assertForbidden();
        $this->actingAs($instructor)->get(route('manager.dashboard'))->assertForbidden();
    }

    public function test_manager_still_cannot_access_the_admin_panel(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)->get('/admin')->assertForbidden();
    }

    public function test_manager_login_redirects_to_manager_dashboard(): void
    {
        \App\Models\PortalSetting::create(['id' => 1, 'accepted_domains' => ['company.com'], 'local_login_enabled' => true]);
        User::factory()->create(['role' => 'manager', 'email' => 'manager@company.com', 'password' => 'secret123']);

        $response = $this->post('/login', ['email' => 'manager@company.com', 'password' => 'secret123']);

        $response->assertRedirect(route('manager.dashboard'));
    }
}
