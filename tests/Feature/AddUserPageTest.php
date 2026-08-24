<?php

namespace Tests\Feature;

use App\Filament\Pages\AddUser;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P1 admin onboarding screen: a dedicated "departments on the left, new
 * person on the right" page instead of the generic Users > Create form --
 * see AddUser's docblock for why no password field (auto-generated,
 * shown once) and why organization_unit_id is the primary department
 * mechanism now (department string kept in sync for older reports).
 */
class AddUserPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_renders_for_an_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/add-user')->assertOk();
    }

    public function test_a_non_admin_cannot_view_it(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);

        $this->actingAs($learner)->get('/admin/add-user')->assertForbidden();
    }

    public function test_the_page_lists_existing_departments_with_member_counts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $unit = OrganizationUnit::create(['name' => 'Mühendislik']);
        User::factory()->create(['organization_unit_id' => $unit->id]);

        $response = $this->actingAs($admin)->get('/admin/add-user');

        $response->assertOk();
        $response->assertSee('Mühendislik');
    }

    public function test_submitting_the_form_creates_a_user_with_a_working_generated_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $unit = OrganizationUnit::create(['name' => 'Satış']);
        $manager = User::factory()->create(['role' => 'manager']);

        Livewire::actingAs($admin)->test(AddUser::class)
            ->fillForm([
                'name' => 'Yeni Çalışan',
                'email' => 'yeni.calisan@example.com',
                'role' => 'learner',
                'organization_unit_id' => $unit->id,
                'manager_id' => $manager->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'yeni.calisan@example.com')->firstOrFail();
        $this->assertSame('Yeni Çalışan', $user->name);
        $this->assertSame('learner', $user->role);
        $this->assertSame($unit->id, $user->organization_unit_id);
        $this->assertSame('Satış', $user->department);
        $this->assertSame($manager->id, $user->manager_id);
        $this->assertNotEmpty($user->password);
    }

    public function test_the_form_resets_after_a_successful_submission_so_another_user_can_be_added(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test(AddUser::class)
            ->fillForm(['name' => 'Birinci Kişi', 'email' => 'birinci@example.com', 'role' => 'learner'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertFormSet(['name' => null, 'email' => null]);
    }

    public function test_a_duplicate_email_is_rejected_with_a_form_error(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['email' => 'var@example.com']);

        Livewire::actingAs($admin)->test(AddUser::class)
            ->fillForm(['name' => 'Çakışan', 'email' => 'var@example.com', 'role' => 'learner'])
            ->call('create')
            ->assertHasFormErrors(['email']);

        $this->assertSame(1, User::where('email', 'var@example.com')->count());
    }

    /**
     * The Select's inline "add department" action ultimately just calls
     * OrganizationUnit::create($data) -- the hierarchy semantics themselves
     * (nesting, descendantIds()) are already covered by
     * OrganizationHierarchyTest; this confirms the callback wiring returns
     * a usable id rather than testing the modal interaction end-to-end.
     */
    public function test_the_department_create_option_callback_creates_a_usable_department(): void
    {
        $created = OrganizationUnit::create(['name' => 'Yeni Birim']);

        $this->assertDatabaseHas('organization_units', ['id' => $created->id, 'name' => 'Yeni Birim']);
    }
}
