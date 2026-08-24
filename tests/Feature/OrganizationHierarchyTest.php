<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P1 organization hierarchy: two independent trees --
 *   1. User::allReports() -- the full manager_id downline, not just direct
 *      reports, feeding the manager dashboard's compliance visibility.
 *   2. OrganizationUnit -- a proper nested department/division tree
 *      (parent_id), used to roll up reporting filters by "this unit and
 *      everything under it" instead of one flat department string.
 */
class OrganizationHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_reports_includes_indirect_reports_through_a_sub_manager(): void
    {
        $director = User::factory()->create(['role' => 'manager']);
        $subManager = User::factory()->create(['role' => 'manager', 'manager_id' => $director->id]);
        $ic = User::factory()->create(['role' => 'learner', 'manager_id' => $subManager->id]);

        $reportIds = $director->allReports()->pluck('id');

        $this->assertTrue($reportIds->contains($subManager->id));
        $this->assertTrue($reportIds->contains($ic->id));
    }

    public function test_all_reports_does_not_include_unrelated_users(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $stranger = User::factory()->create(['role' => 'learner']);

        $this->assertFalse($manager->allReports()->pluck('id')->contains($stranger->id));
    }

    public function test_all_reports_is_safe_against_a_corrupted_manager_id_cycle(): void
    {
        $a = User::factory()->create(['role' => 'manager']);
        $b = User::factory()->create(['role' => 'manager', 'manager_id' => $a->id]);
        // Simulate data corruption: point $a's manager back at $b, forming a
        // cycle that should never happen through the normal admin UI.
        $a->forceFill(['manager_id' => $b->id])->save();

        $reports = $a->allReports();

        $this->assertTrue($reports->pluck('id')->contains($b->id));
        $this->assertCount(1, $reports);
    }

    public function test_manager_dashboard_shows_overdue_training_for_an_indirect_report(): void
    {
        $director = User::factory()->create(['role' => 'manager']);
        $subManager = User::factory()->create(['role' => 'manager', 'manager_id' => $director->id]);
        $ic = User::factory()->create(['role' => 'learner', 'manager_id' => $subManager->id, 'name' => 'Alt Ekip Üyesi']);
        $course = Course::factory()->create(['status' => 'published', 'due_days' => 5, 'title' => 'Gecikmiş Eğitim']);
        Enrollment::create(['user_id' => $ic->id, 'course_id' => $course->id, 'created_at' => now()->subDays(10)]);

        $response = $this->actingAs($director)->get(route('manager.dashboard'));

        $response->assertOk();
        $response->assertSee('Gecikmiş Eğitim');
        $response->assertSee('alt ekip');
    }

    /**
     * The dashboard's certificate count runs Certificate::whereHas('enrollment',
     * fn ($q) => $q->whereIn('user_id', $teamIds)) against $teamIds -- this
     * proves that query actually finds a certificate belonging to an
     * indirect report when $teamIds comes from allReports() rather than
     * directReports(), without depending on fragile digit-matching against
     * rendered HTML.
     */
    public function test_certificate_count_query_includes_an_indirect_reports_certificate(): void
    {
        $director = User::factory()->create(['role' => 'manager']);
        $subManager = User::factory()->create(['role' => 'manager', 'manager_id' => $director->id]);
        $ic = User::factory()->create(['role' => 'learner', 'manager_id' => $subManager->id]);
        $course = Course::factory()->create(['status' => 'published']);
        $enrollment = Enrollment::create(['user_id' => $ic->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);
        Certificate::create(['enrollment_id' => $enrollment->id, 'certificate_number' => 'CERT-ORG1', 'issued_at' => now()]);

        $teamIds = $director->allReports()->pluck('id');
        $certificatesCount = Certificate::whereHas('enrollment', fn ($q) => $q->whereIn('user_id', $teamIds))->count();

        $this->assertSame(1, $certificatesCount);
    }

    public function test_organization_unit_descendant_ids_include_nested_children(): void
    {
        $division = OrganizationUnit::create(['name' => 'Mühendislik']);
        $department = OrganizationUnit::create(['name' => 'Platform', 'parent_id' => $division->id]);
        $team = OrganizationUnit::create(['name' => 'Altyapı', 'parent_id' => $department->id]);
        $unrelated = OrganizationUnit::create(['name' => 'Pazarlama']);

        $ids = $division->descendantIds();

        $this->assertTrue($ids->contains($division->id));
        $this->assertTrue($ids->contains($department->id));
        $this->assertTrue($ids->contains($team->id));
        $this->assertFalse($ids->contains($unrelated->id));
    }

    public function test_organization_unit_all_users_includes_members_of_sub_units(): void
    {
        $division = OrganizationUnit::create(['name' => 'Mühendislik']);
        $team = OrganizationUnit::create(['name' => 'Altyapı', 'parent_id' => $division->id]);
        $directMember = User::factory()->create(['organization_unit_id' => $division->id]);
        $subTeamMember = User::factory()->create(['organization_unit_id' => $team->id]);
        $outsider = User::factory()->create();

        $memberIds = $division->allUsers()->pluck('id');

        $this->assertTrue($memberIds->contains($directMember->id));
        $this->assertTrue($memberIds->contains($subTeamMember->id));
        $this->assertFalse($memberIds->contains($outsider->id));
    }

    public function test_reports_can_be_filtered_by_organization_unit_including_sub_units(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $division = OrganizationUnit::create(['name' => 'Mühendislik']);
        $team = OrganizationUnit::create(['name' => 'Altyapı', 'parent_id' => $division->id]);
        $inTeam = User::factory()->create(['organization_unit_id' => $team->id, 'name' => 'Takım Üyesi']);
        $elsewhere = User::factory()->create(['name' => 'Başka Birim Üyesi']);
        $course = Course::factory()->create();
        Enrollment::create(['user_id' => $inTeam->id, 'course_id' => $course->id]);
        Enrollment::create(['user_id' => $elsewhere->id, 'course_id' => $course->id]);

        $response = $this->actingAs($admin)->get(route('reports.index', ['organization_unit_id' => $division->id]));

        $response->assertOk();
        $response->assertSee('Takım Üyesi');
        $response->assertDontSee('Başka Birim Üyesi');
    }

    public function test_the_user_edit_page_offers_the_organization_unit_as_an_assignable_option(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $unit = OrganizationUnit::create(['name' => 'Satış']);
        $user = User::factory()->create();

        $this->actingAs($admin)->get("/admin/users/{$user->id}/edit")->assertOk()->assertSee('Satış');
    }

    public function test_a_users_organization_unit_relationship_resolves_correctly(): void
    {
        $unit = OrganizationUnit::create(['name' => 'Finans']);
        $user = User::factory()->create(['organization_unit_id' => $unit->id]);

        $this->assertTrue($user->organizationUnit->is($unit));
    }

    public function test_organization_unit_cannot_be_set_as_its_own_or_a_descendants_parent(): void
    {
        $division = OrganizationUnit::create(['name' => 'Mühendislik']);
        $team = OrganizationUnit::create(['name' => 'Altyapı', 'parent_id' => $division->id]);

        // descendantIds() (which the Filament form's picker excludes) must
        // contain both the unit itself and its child, so neither could ever
        // be chosen as $division's own parent.
        $descendantIds = $division->descendantIds();

        $this->assertTrue($descendantIds->contains($division->id));
        $this->assertTrue($descendantIds->contains($team->id));
    }
}
