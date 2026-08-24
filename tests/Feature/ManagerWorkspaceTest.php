<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz 97 (spec §15): the manager dashboard became a tabbed workspace
 * (Overview/Team/Training/Compliance/Reports). Existing coverage in
 * ManagerRoleTest.php still protects the underlying data (scoping to
 * direct/sub-team reports, overdue/exempt logic); this covers what's new.
 */
class ManagerWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_workspace_tabs_are_present(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $response = $this->actingAs($manager)->get(route('manager.dashboard'));

        $response->assertOk();
        foreach (['Genel bakış', 'Ekip', 'Eğitim Takibi', 'Uyumluluk', 'Raporlar'] as $tab) {
            $response->assertSee($tab);
        }
    }

    public function test_team_compliance_rate_is_computed_from_mandatory_category_courses_only(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $report = User::factory()->create(['role' => 'learner', 'manager_id' => $manager->id]);
        $mandatory = Course::factory()->create(['status' => 'published', 'category' => 'required']);
        $optional = Course::factory()->create(['status' => 'published', 'category' => 'optional']);
        Enrollment::create(['user_id' => $report->id, 'course_id' => $mandatory->id, 'status' => 'completed', 'completed_at' => now()]);
        // Not completed, and not mandatory -- must not drag the rate down.
        Enrollment::create(['user_id' => $report->id, 'course_id' => $optional->id, 'status' => 'enrolled']);

        $response = $this->actingAs($manager)->get(route('manager.dashboard'));

        $response->assertOk();
        $response->assertSee('%100');
    }

    public function test_an_enrollment_due_within_a_week_is_marked_at_risk_not_overdue(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $report = User::factory()->create(['role' => 'learner', 'manager_id' => $manager->id]);
        $course = Course::factory()->create(['status' => 'published', 'due_days' => 3]);
        Enrollment::create(['user_id' => $report->id, 'course_id' => $course->id, 'status' => 'enrolled', 'created_at' => now()->subDay()]);

        $response = $this->actingAs($manager)->get(route('manager.dashboard'));

        $response->assertOk();
        $response->assertSee('Riskli');
        $response->assertDontSee('Süresi geçti');
    }

    public function test_the_manager_can_export_their_teams_csv(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $report = User::factory()->create(['role' => 'learner', 'manager_id' => $manager->id, 'name' => 'CSV Testi']);
        $course = Course::factory()->create(['status' => 'published']);
        Enrollment::create(['user_id' => $report->id, 'course_id' => $course->id]);

        $response = $this->actingAs($manager)->get(route('manager.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('CSV Testi', $response->streamedContent());
    }

    public function test_the_export_only_includes_the_managers_own_team(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $outsider = User::factory()->create(['role' => 'learner', 'name' => 'Baska Ekip']);
        $course = Course::factory()->create(['status' => 'published']);
        Enrollment::create(['user_id' => $outsider->id, 'course_id' => $course->id]);

        $response = $this->actingAs($manager)->get(route('manager.export'));

        $response->assertOk();
        $this->assertStringNotContainsString('Baska Ekip', $response->streamedContent());
    }

    public function test_a_learner_cannot_export_a_team_report(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);

        $this->actingAs($learner)->get(route('manager.export'))->assertForbidden();
    }
}
