<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\CourseCompleted;
use App\Services\CompletionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Completion already told every integrated system (enrollment.completed and
 * certificate.issued webhooks) but told nobody human: a learner finished a
 * course, earned a certificate, and only found out by logging in and
 * noticing. These cover the notification, and the course setting that says
 * whether a certificate should be minted at all.
 */
class CourseCompletionNotificationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Enrollment, 1: Activity} */
    private function completableCourse(bool $certificateEnabled = true): array
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $learner = User::factory()->create(['role' => 'learner']);

        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'certificate_enabled' => $certificateEnabled,
        ]);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1', 'sort_order' => 1]);
        $activity = Activity::create([
            'course_section_id' => $section->id,
            'title' => 'Okuma',
            'type' => 'resource',
            'is_required' => true,
            'sort_order' => 1,
        ]);

        $enrollment = Enrollment::create([
            'user_id' => $learner->id,
            'course_id' => $course->id,
            'status' => 'enrolled',
        ]);

        return [$enrollment, $activity];
    }

    private function complete(Enrollment $enrollment, Activity $activity): void
    {
        $progress = ActivityProgress::firstOrCreate([
            'enrollment_id' => $enrollment->id,
            'activity_id' => $activity->id,
        ]);
        app(CompletionEngine::class)->completeActivityProgress($progress, $enrollment);
    }

    public function test_completing_a_course_notifies_the_learner_with_their_certificate(): void
    {
        Notification::fake();
        [$enrollment, $activity] = $this->completableCourse();

        $this->complete($enrollment, $activity);

        $enrollment->refresh();
        $this->assertSame('completed', $enrollment->status);
        $certificate = Certificate::where('enrollment_id', $enrollment->id)->firstOrFail();

        Notification::assertSentTo(
            $enrollment->user,
            CourseCompleted::class,
            function (CourseCompleted $notification) use ($certificate) {
                return $notification->certificate?->is($certificate) === true;
            }
        );
    }

    public function test_the_notification_names_the_certificate_and_links_to_it(): void
    {
        [$enrollment, $activity] = $this->completableCourse();
        $this->complete($enrollment, $activity);

        $certificate = Certificate::where('enrollment_id', $enrollment->id)->firstOrFail();
        $mail = (new CourseCompleted($enrollment->refresh(), $certificate))->toMail($enrollment->user);
        $rendered = $mail->render();

        $this->assertStringContainsString($certificate->certificate_number, $rendered);
        $this->assertStringContainsString(route('certificates.download', $certificate), $rendered);
    }

    public function test_a_course_with_certificates_disabled_completes_without_issuing_one(): void
    {
        Notification::fake();
        [$enrollment, $activity] = $this->completableCourse(certificateEnabled: false);

        $this->complete($enrollment, $activity);

        $enrollment->refresh();
        $this->assertSame('completed', $enrollment->status, 'completion itself must still happen');
        $this->assertNull(
            Certificate::where('enrollment_id', $enrollment->id)->first(),
            'a course with "Sertifika üret" switched off must not mint a certificate'
        );

        Notification::assertSentTo(
            $enrollment->user,
            CourseCompleted::class,
            fn (CourseCompleted $notification) => $notification->certificate === null
        );
    }

    public function test_re_evaluating_a_completed_enrollment_does_not_notify_twice(): void
    {
        Notification::fake();
        [$enrollment, $activity] = $this->completableCourse();

        $this->complete($enrollment, $activity);
        app(CompletionEngine::class)->evaluateEnrollment($enrollment->refresh());
        app(CompletionEngine::class)->evaluateEnrollment($enrollment->refresh());

        Notification::assertSentToTimes($enrollment->user, CourseCompleted::class, 1);
    }
}
