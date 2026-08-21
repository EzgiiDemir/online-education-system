<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 Audit log (§20): a generic Eloquent-observer trait (Auditable) writes an
 * immutable row on create/update/delete for Course, Enrollment, Certificate,
 * User, and PortalSetting -- covering both Filament admin edits and
 * Blade-panel-driven changes (e.g. instructor course creation) through one
 * mechanism, rather than hand-instrumenting every mutation point.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_course_writes_an_audit_log_entry(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $this->actingAs($instructor);

        $course = Course::factory()->create(['title' => 'Denetlenen Eğitim']);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $instructor->id,
            'action' => 'created',
            'entity_type' => 'Course',
            'entity_id' => $course->id,
            'entity_label' => 'Denetlenen Eğitim',
        ]);
    }

    public function test_updating_an_enrollment_records_before_and_after_values(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create();
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'enrolled']);

        $enrollment->update(['status' => 'completed', 'completed_at' => now()]);

        $log = AuditLog::where('entity_type', 'Enrollment')->where('entity_id', $enrollment->id)->where('action', 'updated')->first();
        $this->assertNotNull($log);
        $this->assertSame('enrolled', $log->changes['status']['from']);
        $this->assertSame('completed', $log->changes['status']['to']);
    }

    public function test_password_changes_are_never_written_to_the_audit_log(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $user->update(['password' => 'brand-new-password']);

        $log = AuditLog::where('entity_type', 'User')->where('entity_id', $user->id)->where('action', 'updated')->first();
        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('password', $log->changes ?? []);
    }

    public function test_admin_can_view_the_audit_log_in_the_admin_panel(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Course::factory()->create(['title' => 'Görünür Eğitim']);

        $response = $this->actingAs($admin)->get('/admin/audit-logs');

        $response->assertOk();
        $response->assertSee('Görünür Eğitim');
    }

    public function test_non_admin_cannot_view_the_audit_log(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);

        $this->actingAs($learner)->get('/admin/audit-logs')->assertForbidden();
    }
}
