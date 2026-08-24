<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\CertificateExpiringSoon;
use App\Notifications\CoursePublished;
use App\Notifications\TrainingDueSoon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P1 notification preferences: every notification type used to
 * unconditionally send mail+database with no way to opt out. Notification::
 * fake() (used by TrainingRemindersTest et al.) intercepts ->notify() before
 * via() is ever evaluated, so it can't see this behavior -- these tests call
 * via() directly against a real User instead.
 */
class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_wants_every_notification_type_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->wantsNotification('training_due_soon'));
        $this->assertTrue($user->wantsNotification('certificate_expiring_soon'));
        $this->assertTrue($user->wantsNotification('course_published'));
    }

    public function test_via_sends_mail_and_database_when_the_preference_is_on(): void
    {
        $user = User::factory()->create(['notification_preferences' => ['training_due_soon' => true]]);
        $course = Course::factory()->create();
        $enrollment = Enrollment::create(['user_id' => $user->id, 'course_id' => $course->id]);

        $channels = (new TrainingDueSoon($enrollment, 7))->via($user);

        $this->assertSame(['mail', 'database'], $channels);
    }

    public function test_via_sends_nothing_when_the_preference_is_off(): void
    {
        $user = User::factory()->create(['notification_preferences' => ['training_due_soon' => false]]);
        $course = Course::factory()->create();
        $enrollment = Enrollment::create(['user_id' => $user->id, 'course_id' => $course->id]);

        $channels = (new TrainingDueSoon($enrollment, 7))->via($user);

        $this->assertSame([], $channels);
    }

    public function test_certificate_expiring_soon_respects_the_preference(): void
    {
        $user = User::factory()->create(['notification_preferences' => ['certificate_expiring_soon' => false]]);
        $course = Course::factory()->create();
        $enrollment = Enrollment::create(['user_id' => $user->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);
        $certificate = Certificate::create(['enrollment_id' => $enrollment->id, 'certificate_number' => 'CERT-PREF1', 'issued_at' => now()]);

        $channels = (new CertificateExpiringSoon($certificate, 30))->via($user);

        $this->assertSame([], $channels);
    }

    public function test_course_published_respects_the_preference(): void
    {
        $user = User::factory()->create(['notification_preferences' => ['course_published' => false]]);
        $course = Course::factory()->create();

        $channels = (new CoursePublished($course))->via($user);

        $this->assertSame([], $channels);
    }

    public function test_a_user_can_turn_a_preference_off_from_the_account_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('account.notification-preferences.update'), [
            'preferences' => ['certificate_expiring_soon'],
        ]);

        $response->assertRedirect();
        $user->refresh();
        $this->assertTrue($user->wantsNotification('certificate_expiring_soon'));
        $this->assertFalse($user->wantsNotification('training_due_soon'));
        $this->assertFalse($user->wantsNotification('course_published'));
    }

    public function test_submitting_with_nothing_checked_turns_every_preference_off(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('account.notification-preferences.update'), []);

        $user->refresh();
        $this->assertFalse($user->wantsNotification('training_due_soon'));
        $this->assertFalse($user->wantsNotification('certificate_expiring_soon'));
        $this->assertFalse($user->wantsNotification('course_published'));
    }

    public function test_the_account_page_shows_the_current_preferences(): void
    {
        $user = User::factory()->create(['notification_preferences' => ['training_due_soon' => false]]);

        $response = $this->actingAs($user)->get(route('account.show'));

        $response->assertOk();
        $response->assertSee('Eğitim son tarihi hatırlatmaları');
    }
}
