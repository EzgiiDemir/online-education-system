<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\CertificateExpiringSoon;
use App\Notifications\TrainingDueSoon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * P0 Automation engine (§27): a small daily rule engine.
 *   WHEN due in exactly 7 days AND incomplete -> remind learner
 *   WHEN certificate expires in exactly 30 days -> remind learner + manager
 * "Exactly N days" is the point being tested here -- it must not fire early,
 * must not fire late, and (implicitly, since it only fires on one calendar
 * day) never double-sends.
 */
class TrainingRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminder_is_sent_when_a_course_is_due_in_exactly_seven_days(): void
    {
        Notification::fake();
        $learner = User::factory()->create();
        $course = Course::factory()->create(['due_days' => 7]);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'enrolled', 'created_at' => now()]);

        $this->artisan('app:send-training-reminders');

        Notification::assertSentTo($learner, TrainingDueSoon::class, fn ($n) => $n->enrollment->is($enrollment));
    }

    public function test_no_reminder_when_due_date_is_not_yet_seven_days_away(): void
    {
        Notification::fake();
        $learner = User::factory()->create();
        $course = Course::factory()->create(['due_days' => 10]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'created_at' => now()]);

        $this->artisan('app:send-training-reminders');

        Notification::assertNotSentTo($learner, TrainingDueSoon::class);
    }

    public function test_no_due_soon_reminder_for_a_completed_enrollment(): void
    {
        Notification::fake();
        $learner = User::factory()->create();
        $course = Course::factory()->create(['due_days' => 7]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now(), 'created_at' => now()]);

        $this->artisan('app:send-training-reminders');

        Notification::assertNotSentTo($learner, TrainingDueSoon::class);
    }

    public function test_certificate_expiry_reminder_is_sent_to_learner_and_their_manager(): void
    {
        Notification::fake();
        $manager = User::factory()->create(['role' => 'manager']);
        $learner = User::factory()->create(['manager_id' => $manager->id]);
        $course = Course::factory()->create(['recertification_period_days' => 30]);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);
        $certificate = Certificate::create(['enrollment_id' => $enrollment->id, 'certificate_number' => 'CERT-REM01', 'issued_at' => now()]);

        $this->artisan('app:send-training-reminders');

        Notification::assertSentTo($learner, CertificateExpiringSoon::class, fn ($n) => $n->certificate->is($certificate));
        Notification::assertSentTo($manager, CertificateExpiringSoon::class);
    }

    public function test_no_expiry_reminder_when_certificate_has_no_recertification_period(): void
    {
        Notification::fake();
        $learner = User::factory()->create();
        $course = Course::factory()->create(['recertification_period_days' => null]);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);
        Certificate::create(['enrollment_id' => $enrollment->id, 'certificate_number' => 'CERT-REM02', 'issued_at' => now()]);

        $this->artisan('app:send-training-reminders');

        Notification::assertNotSentTo($learner, CertificateExpiringSoon::class);
    }
}
