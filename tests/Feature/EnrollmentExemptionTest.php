<?php

namespace Tests\Feature;

use App\Filament\Resources\Enrollments\Pages\ListEnrollments;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P1 exemption/waiver: an admin can mark a required course as not applying
 * to a specific learner (role change, prior certification, etc.) without
 * fabricating a completion -- Course::isOverdueFor()/SendTrainingReminders'
 * side of this is covered in ComplianceAndSkillsTest and
 * TrainingRemindersTest; this covers the admin-facing action itself.
 */
class EnrollmentExemptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_exempt_an_enrollment_with_a_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create();
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        Livewire::actingAs($admin)->test(ListEnrollments::class)
            ->callTableAction('exempt', $enrollment, data: ['reason' => 'Rol değişikliği nedeniyle gerekli değil.']);

        $enrollment->refresh();
        $this->assertTrue($enrollment->isExempt());
        $this->assertSame('Rol değişikliği nedeniyle gerekli değil.', $enrollment->exemption_reason);
        $this->assertSame($admin->id, $enrollment->exempted_by);
    }

    public function test_the_exempt_action_requires_a_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create();
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        Livewire::actingAs($admin)->test(ListEnrollments::class)
            ->callTableAction('exempt', $enrollment, data: ['reason' => ''])
            ->assertHasTableActionErrors(['reason' => 'required']);

        $this->assertFalse($enrollment->fresh()->isExempt());
    }

    public function test_the_exempt_action_is_hidden_for_an_already_exempted_enrollment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create();
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);
        $enrollment->exempt($admin, 'Zaten muaf.');

        Livewire::actingAs($admin)->test(ListEnrollments::class)
            ->assertTableActionHidden('exempt', $enrollment)
            ->assertTableActionVisible('unexempt', $enrollment);
    }

    public function test_the_exempt_action_is_hidden_for_a_completed_enrollment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create();
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);

        Livewire::actingAs($admin)->test(ListEnrollments::class)
            ->assertTableActionHidden('exempt', $enrollment);
    }

    public function test_admin_can_remove_an_exemption(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create();
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);
        $enrollment->exempt($admin, 'Geçici muafiyet.');

        Livewire::actingAs($admin)->test(ListEnrollments::class)
            ->callTableAction('unexempt', $enrollment);

        $this->assertFalse($enrollment->fresh()->isExempt());
    }

    public function test_the_account_page_shows_an_exempt_badge_instead_of_a_completion_percentage(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['title' => 'Muaf Kurs']);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);
        $enrollment->exempt($admin, 'Muaf.');

        $response = $this->actingAs($learner)->get(route('account.show'));

        $response->assertOk();
        $response->assertSee('Muaf');
    }
}
