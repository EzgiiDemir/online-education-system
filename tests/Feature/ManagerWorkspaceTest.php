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

    /**
     * Faz 102 (item 7 of the UI/UX feedback): the Team tab's cards only
     * ever showed aggregate counts -- this is the click-through detail page
     * they were missing.
     */
    public function test_a_manager_can_view_a_direct_reports_training_record(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $report = User::factory()->create(['role' => 'learner', 'manager_id' => $manager->id]);
        $course = Course::factory()->create(['title' => 'Ekip Üyesi Eğitimi']);
        Enrollment::create(['user_id' => $report->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);

        $response = $this->actingAs($manager)->get(route('manager.team-member', $report));

        $response->assertOk();
        $response->assertSee($report->name);
        $response->assertSee('Ekip Üyesi Eğitimi');
    }

    public function test_a_manager_can_view_a_sub_teams_report_through_the_reporting_chain(): void
    {
        $seniorManager = User::factory()->create(['role' => 'manager']);
        $subManager = User::factory()->create(['role' => 'manager', 'manager_id' => $seniorManager->id]);
        $report = User::factory()->create(['role' => 'learner', 'manager_id' => $subManager->id]);

        $this->actingAs($seniorManager)->get(route('manager.team-member', $report))->assertOk();
    }

    public function test_a_manager_cannot_view_an_unrelated_persons_training_record(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $outsider = User::factory()->create(['role' => 'learner']);

        $this->actingAs($manager)->get(route('manager.team-member', $outsider))->assertForbidden();
    }

    public function test_a_learner_cannot_view_anyones_training_record(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $other = User::factory()->create(['role' => 'learner']);

        $this->actingAs($learner)->get(route('manager.team-member', $other))->assertForbidden();
    }
}
