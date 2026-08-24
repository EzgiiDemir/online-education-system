<?php

namespace Tests\Feature;

use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\RelationManagers\EnrollmentsRelationManager;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\TrainingAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P1 mandatory-training assignment: previously a learner could only ever
 * get onto a course roster by enrolling themselves. This is the admin-side
 * "assign this course directly to these people" flow, reusing
 * EnrollmentService so it gets the same capacity/waitlist handling as
 * self-service enroll instead of a second, drifting copy of that logic.
 */
class TrainingAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_bulk_assign_a_course_to_multiple_users(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['status' => 'published']);
        $learnerA = User::factory()->create(['role' => 'learner']);
        $learnerB = User::factory()->create(['role' => 'learner']);

        Livewire::actingAs($admin)->test(EnrollmentsRelationManager::class, [
            'ownerRecord' => $course,
            'pageClass' => EditCourse::class,
        ])->callTableAction('bulkAssign', data: ['user_ids' => [$learnerA->id, $learnerB->id]]);

        $this->assertDatabaseHas('enrollments', ['user_id' => $learnerA->id, 'course_id' => $course->id]);
        $this->assertDatabaseHas('enrollments', ['user_id' => $learnerB->id, 'course_id' => $course->id]);
        Notification::assertSentTo($learnerA, TrainingAssigned::class);
        Notification::assertSentTo($learnerB, TrainingAssigned::class);
    }

    public function test_bulk_assign_skips_a_prerequisite_check_that_would_block_self_enrollment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $fundamentals = Course::factory()->create(['status' => 'published']);
        $advanced = Course::factory()->create(['status' => 'published']);
        $advanced->prerequisites()->attach($fundamentals);
        $learner = User::factory()->create(['role' => 'learner']);

        Livewire::actingAs($admin)->test(EnrollmentsRelationManager::class, [
            'ownerRecord' => $advanced,
            'pageClass' => EditCourse::class,
        ])->callTableAction('bulkAssign', data: ['user_ids' => [$learner->id]]);

        $this->assertDatabaseHas('enrollments', ['user_id' => $learner->id, 'course_id' => $advanced->id]);
    }

    public function test_bulk_assign_is_idempotent_for_an_already_enrolled_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['status' => 'published']);
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        Livewire::actingAs($admin)->test(EnrollmentsRelationManager::class, [
            'ownerRecord' => $course,
            'pageClass' => EditCourse::class,
        ])->callTableAction('bulkAssign', data: ['user_ids' => [$learner->id]]);

        $this->assertSame(1, Enrollment::where('user_id', $learner->id)->where('course_id', $course->id)->count());
    }

    /**
     * P1 recertification: a learner whose current cycle is stale (completed,
     * certificate expired) must still appear in the bulk-assign picker --
     * they're exactly who an admin would want to re-assign -- and get a
     * fresh cycle, not be silently excluded as "already enrolled".
     */
    public function test_bulk_assign_can_re_assign_a_learner_whose_certificate_has_expired(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['status' => 'published', 'recertification_period_days' => 30]);
        $learner = User::factory()->create(['role' => 'learner']);
        $oldEnrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()->subDays(40)]);
        Certificate::create(['enrollment_id' => $oldEnrollment->id, 'certificate_number' => 'CERT-BULK1', 'issued_at' => now()->subDays(40)]);

        Livewire::actingAs($admin)->test(EnrollmentsRelationManager::class, [
            'ownerRecord' => $course,
            'pageClass' => EditCourse::class,
        ])->callTableAction('bulkAssign', data: ['user_ids' => [$learner->id]]);

        $this->assertSame(2, Enrollment::where('user_id', $learner->id)->where('course_id', $course->id)->count());
    }

    public function test_bulk_assign_waitlists_when_the_runs_capacity_is_already_full(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['status' => 'published']);
        $run = $course->runs()->create(['name' => 'Dönem 1', 'code' => 'RUN-ASSIGN1', 'status' => 'open', 'max_seats' => 1]);
        $seated = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $seated->id, 'course_id' => $course->id, 'course_run_id' => $run->id]);
        $toAssign = User::factory()->create(['role' => 'learner']);

        Livewire::actingAs($admin)->test(EnrollmentsRelationManager::class, [
            'ownerRecord' => $course,
            'pageClass' => EditCourse::class,
        ])->callTableAction('bulkAssign', data: ['user_ids' => [$toAssign->id]]);

        $this->assertDatabaseMissing('enrollments', ['user_id' => $toAssign->id, 'course_id' => $course->id]);
        $this->assertDatabaseHas('waitlist_entries', ['user_id' => $toAssign->id, 'course_run_id' => $run->id]);
    }
}
