<?php

namespace Tests\Feature;

use App\Filament\Pages\AddUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\OrganizationUnit;
use App\Models\PortalSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Faz 91: users.role stays the single "primary" role every pre-existing
 * role === 'x' check reads (so single-role behaviour below is a pure
 * regression guard); the role_user pivot is what lets someone hold more
 * than one role and switch between workspaces without a second login.
 */
class MultiRoleWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user): TestResponse
    {
        PortalSetting::firstOrCreate(['id' => 1], ['accepted_domains' => ['example.com'], 'local_login_enabled' => true]);

        return $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    }

    public function test_a_single_role_instructor_login_redirects_straight_to_the_instructor_dashboard_and_shows_no_switcher(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor', 'email' => 'tek.rol@example.com', 'password' => 'password']);

        $this->login($instructor)->assertRedirect(route('instructor.dashboard'));

        $page = $this->actingAs($instructor)->get(route('instructor.dashboard'));
        $page->assertOk();
        $page->assertDontSee('Çalışma alanı:');
    }

    public function test_login_redirects_a_single_role_manager_to_the_manager_dashboard_as_before(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'email' => 'yonetici@example.com', 'password' => 'password']);

        $this->login($manager)->assertRedirect(route('manager.dashboard'));
    }

    public function test_a_multi_role_user_lands_on_their_primary_workspace_and_sees_a_switcher_to_the_other(): void
    {
        $user = User::factory()->create(['role' => 'instructor', 'email' => 'coklu.rol@example.com', 'password' => 'password']);
        $user->roles()->attach(Role::where('key', 'manager')->first());

        $this->login($user)->assertRedirect(route('instructor.dashboard'));

        $page = $this->actingAs($user)->get(route('instructor.dashboard'));
        $page->assertOk();
        // Faz 102: the old topbar "Çalışma alanı: X" badge was replaced by a
        // dedicated sidebar section listing every other workspace this user
        // can switch into.
        $page->assertSee('DİĞER ÇALIŞMA ALANLARIM');
        $page->assertSee(route('manager.dashboard'), false);
    }

    public function test_a_multi_role_user_can_actually_open_their_second_workspace_not_just_see_a_dead_link(): void
    {
        $user = User::factory()->create(['role' => 'instructor', 'email' => 'gecis.yapan@example.com']);
        $user->roles()->attach(Role::where('key', 'manager')->first());

        $response = $this->actingAs($user)->get(route('manager.dashboard'));

        $response->assertOk();
    }

    public function test_a_user_without_the_manager_role_still_cannot_open_the_manager_dashboard(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);

        $this->actingAs($instructor)->get(route('manager.dashboard'))->assertForbidden();
    }

    public function test_admin_can_grant_a_second_role_to_an_existing_user_from_the_user_edit_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $instructor = User::factory()->create(['role' => 'instructor']);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $instructor->getRouteKey()])
            ->fillForm(['extra_role_keys' => ['manager']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($instructor->fresh()->hasRole('manager'));
        $this->assertTrue($instructor->fresh()->hasRole('instructor'));
    }

    public function test_the_add_user_page_creates_a_person_with_every_selected_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test(AddUser::class)
            ->fillForm(['name' => 'Çoklu Rol', 'email' => 'coklurol@example.com', 'role' => 'instructor', 'extra_role_keys' => ['manager']])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'coklurol@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('instructor'));
        $this->assertTrue($user->hasRole('manager'));
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_the_add_user_page_pre_scopes_a_new_person_to_the_selected_department_and_lists_its_members(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $unit = OrganizationUnit::create(['name' => 'Ar-Ge']);
        $existingMember = User::factory()->create(['organization_unit_id' => $unit->id, 'role' => 'learner']);

        $component = Livewire::actingAs($admin)->test(AddUser::class)
            ->call('selectDepartment', $unit->id);

        $component->assertSee($existingMember->name);
        $component->assertSet('data.organization_unit_id', $unit->id);
    }
}
