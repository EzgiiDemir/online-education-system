<?php

namespace Tests\Feature;

use App\Filament\Pages\ComplianceDashboard;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P1 compliance dashboard: an org-wide view of "required"/"compliance"
 * category training -- overdue, exempt, and certificate-expiry counts,
 * plus a per-course breakdown. Every number is read off Course::
 * isOverdueFor()/Enrollment::isExempt()/Certificate::isExpired() (already
 * covered individually elsewhere), so these tests focus on the
 * aggregation being correct, not re-proving those methods.
 */
class ComplianceDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_renders_for_an_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/compliance-dashboard')->assertOk();
    }

    public function test_a_non_admin_cannot_view_it(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);

        $this->actingAs($learner)->get('/admin/compliance-dashboard')->assertForbidden();
    }

    public function test_metrics_only_count_required_and_compliance_courses(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mandatory = Course::factory()->create(['category' => 'compliance']);
        $optional = Course::factory()->create(['category' => 'optional']);
        Enrollment::create(['user_id' => User::factory()->create()->id, 'course_id' => $mandatory->id]);
        Enrollment::create(['user_id' => User::factory()->create()->id, 'course_id' => $optional->id]);

        $metrics = Livewire::actingAs($admin)->test(ComplianceDashboard::class)->instance()->getMetrics();

        $this->assertSame(1, $metrics['total']);
    }

    public function test_metrics_count_overdue_completed_and_exempt_correctly(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['category' => 'required', 'due_days' => 5]);

        $overdueLearner = User::factory()->create();
        Enrollment::create(['user_id' => $overdueLearner->id, 'course_id' => $course->id, 'created_at' => now()->subDays(10)]);

        $completedLearner = User::factory()->create();
        Enrollment::create(['user_id' => $completedLearner->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);

        $exemptLearner = User::factory()->create();
        $exemptEnrollment = Enrollment::create(['user_id' => $exemptLearner->id, 'course_id' => $course->id, 'created_at' => now()->subDays(10)]);
        $exemptEnrollment->exempt($admin, 'Muaf.');

        $metrics = Livewire::actingAs($admin)->test(ComplianceDashboard::class)->instance()->getMetrics();

        $this->assertSame(3, $metrics['total']);
        $this->assertSame(1, $metrics['completed']);
        $this->assertSame(1, $metrics['overdue']);
        $this->assertSame(1, $metrics['exempt']);
    }

    public function test_metrics_count_expired_and_soon_to_expire_certificates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['category' => 'compliance', 'recertification_period_days' => 30]);

        $expiredEnrollment = Enrollment::create(['user_id' => User::factory()->create()->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);
        Certificate::create(['enrollment_id' => $expiredEnrollment->id, 'certificate_number' => 'CERT-COMP1', 'issued_at' => now()->subDays(40)]);

        $expiringSoonEnrollment = Enrollment::create(['user_id' => User::factory()->create()->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);
        Certificate::create(['enrollment_id' => $expiringSoonEnrollment->id, 'certificate_number' => 'CERT-COMP2', 'issued_at' => now()->subDays(15)]);

        $metrics = Livewire::actingAs($admin)->test(ComplianceDashboard::class)->instance()->getMetrics();

        $this->assertSame(1, $metrics['expired_certificates']);
        $this->assertSame(1, $metrics['expiring_soon_certificates']);
    }

    public function test_a_revoked_certificate_is_not_counted_as_expired_or_expiring(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['category' => 'compliance', 'recertification_period_days' => 30]);
        $enrollment = Enrollment::create(['user_id' => User::factory()->create()->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);
        $certificate = Certificate::create(['enrollment_id' => $enrollment->id, 'certificate_number' => 'CERT-COMP3', 'issued_at' => now()->subDays(40)]);
        $certificate->revoke($admin, 'Hatalı verildi.');

        $metrics = Livewire::actingAs($admin)->test(ComplianceDashboard::class)->instance()->getMetrics();

        $this->assertSame(0, $metrics['expired_certificates']);
        $this->assertSame(0, $metrics['expiring_soon_certificates']);
    }

    public function test_course_breakdown_reflects_per_course_totals(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['category' => 'required', 'title' => 'Yangın Güvenliği', 'due_days' => 5]);
        Enrollment::create(['user_id' => User::factory()->create()->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);
        Enrollment::create(['user_id' => User::factory()->create()->id, 'course_id' => $course->id, 'created_at' => now()->subDays(10)]);

        $breakdown = Livewire::actingAs($admin)->test(ComplianceDashboard::class)->instance()->getCourseBreakdown();
        $row = $breakdown->firstWhere(fn ($r) => $r['course']->is($course));

        $this->assertSame(2, $row['total']);
        $this->assertSame(1, $row['completed']);
        $this->assertSame(1, $row['overdue']);
        $this->assertSame(50, $row['completion_rate']);
    }
}
