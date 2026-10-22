<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PortalSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Faz 101 (QA) finding #2: the admin Users table's delete action had no
 * safety checks at all. courses.instructor_id has no cascade, so deleting
 * an instructor with courses threw a raw DB foreign-key error; enrollments.
 * user_id DOES cascadeOnDelete, so deleting a learner with history silently
 * destroyed exactly the completion/certificate evidence
 * AccountController::destroy()'s self-service anonymize-not-delete path
 * exists to protect -- an inconsistency between the two removal paths.
 * User::blockingRemovalReason() is now the single rule both the Filament
 * table and the edit page's delete/deactivate controls read.
 */
class UserRemovalSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_the_last_admin_is_blocked(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertNotNull($admin->blockingRemovalReason(isDelete: true));

        Livewire::actingAs($admin)->test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->callAction('delete');

        $this->assertModelExists($admin);
    }

    public function test_deleting_an_instructor_with_courses_is_blocked(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $instructor = User::factory()->create(['role' => 'instructor']);
        Course::factory()->create(['instructor_id' => $instructor->id]);

        Livewire::actingAs($admin)->test(EditUser::class, ['record' => $instructor->getRouteKey()])
            ->callAction('delete');

        $this->assertModelExists($instructor);
    }

    public function test_deleting_a_learner_with_enrollment_history_is_blocked(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => Course::factory()->create()->id]);

        Livewire::actingAs($admin)->test(EditUser::class, ['record' => $learner->getRouteKey()])
            ->callAction('delete');

        $this->assertModelExists($learner);
        $this->assertDatabaseHas('enrollments', ['user_id' => $learner->id]);
    }

    public function test_deleting_a_user_with_no_dependent_records_succeeds(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $freshLearner = User::factory()->create(['role' => 'learner']);

        Livewire::actingAs($admin)->test(EditUser::class, ['record' => $freshLearner->getRouteKey()])
            ->callAction('delete');

        $this->assertModelMissing($freshLearner);
    }

    public function test_deactivating_the_last_admin_is_blocked(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->fillForm(['is_deactivated' => true])
            ->call('save');

        $this->assertNull($admin->fresh()->deactivated_at);
    }

    public function test_deactivating_an_instructor_with_courses_is_blocked(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $instructor = User::factory()->create(['role' => 'instructor']);
        Course::factory()->create(['instructor_id' => $instructor->id]);

        Livewire::actingAs($admin)->test(EditUser::class, ['record' => $instructor->getRouteKey()])
            ->fillForm(['is_deactivated' => true])
            ->call('save');

        $this->assertNull($instructor->fresh()->deactivated_at);
    }

    /**
     * Unlike hard delete, deactivating a learner with enrollment history is
     * fine (and the recommended path) -- it never touches the enrollment
     * rows, just blocks future login.
     */
    public function test_deactivating_a_learner_with_enrollment_history_succeeds(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => Course::factory()->create()->id]);

        Livewire::actingAs($admin)->test(EditUser::class, ['record' => $learner->getRouteKey()])
            ->fillForm(['is_deactivated' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNotNull($learner->fresh()->deactivated_at);
        $this->assertDatabaseHas('enrollments', ['user_id' => $learner->id]);
    }

    public function test_a_deactivated_user_cannot_log_in(): void
    {
        $learner = User::factory()->create(['role' => 'learner', 'email' => 'deactivated@example.com', 'password' => 'password', 'deactivated_at' => now()]);
        PortalSetting::create(['id' => 1, 'accepted_domains' => ['example.com'], 'local_login_enabled' => true]);

        $response = $this->post('/login', ['email' => $learner->email, 'password' => 'password']);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_an_active_user_with_correct_credentials_can_still_log_in(): void
    {
        $learner = User::factory()->create(['role' => 'learner', 'email' => 'active@example.com', 'password' => 'password']);
        PortalSetting::create(['id' => 1, 'accepted_domains' => ['example.com'], 'local_login_enabled' => true]);

        $this->post('/login', ['email' => $learner->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($learner);
    }
}
