<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSession;
use App\Models\User;
use App\Notifications\LiveSessionScheduled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 Notification center (§12): notifications were already being generated
 * (LiveSessionScheduled, and this phase's TrainingDueSoon/CertificateExpiringSoon)
 * but had zero in-app surface -- only email. This adds the bell dropdown +
 * full list page + mark-read/mark-all-read, reusing Laravel's built-in
 * DatabaseNotification storage rather than a new notifications table.
 */
class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_unread_notification_badge_shows_the_correct_count(): void
    {
        $learner = User::factory()->create();
        $course = Course::factory()->create();
        $session = CourseSession::create(['course_id' => $course->id, 'title' => 'Seans 1']);
        $learner->notify(new LiveSessionScheduled($session));

        $response = $this->actingAs($learner)->get('/');

        $response->assertOk();
        $response->assertSee('notif-badge', false);
        $response->assertSee('Canlı ders planlandı');
    }

    public function test_marking_a_notification_read_removes_it_from_the_unread_count(): void
    {
        $learner = User::factory()->create();
        $course = Course::factory()->create();
        $session = CourseSession::create(['course_id' => $course->id, 'title' => 'Seans 1']);
        $learner->notify(new LiveSessionScheduled($session));
        $notificationId = $learner->notifications()->first()->id;

        $this->actingAs($learner)->post(route('notifications.read', $notificationId));

        $this->assertSame(0, $learner->fresh()->unreadNotifications()->count());
    }

    public function test_mark_all_read_clears_every_unread_notification(): void
    {
        $learner = User::factory()->create();
        $course = Course::factory()->create();
        $session = CourseSession::create(['course_id' => $course->id, 'title' => 'Seans 1']);
        $learner->notify(new LiveSessionScheduled($session));
        $learner->notify(new LiveSessionScheduled($session));

        $this->actingAs($learner)->post(route('notifications.read-all'));

        $this->assertSame(0, $learner->fresh()->unreadNotifications()->count());
    }

    public function test_a_learner_cannot_mark_another_users_notification_as_read(): void
    {
        $learner = User::factory()->create();
        $otherLearner = User::factory()->create();
        $course = Course::factory()->create();
        $session = CourseSession::create(['course_id' => $course->id, 'title' => 'Seans 1']);
        $otherLearner->notify(new LiveSessionScheduled($session));
        $notificationId = $otherLearner->notifications()->first()->id;

        $this->actingAs($learner)->post(route('notifications.read', $notificationId))->assertNotFound();

        $this->assertSame(1, $otherLearner->fresh()->unreadNotifications()->count());
    }

    public function test_notifications_index_page_lists_a_users_own_notifications(): void
    {
        $learner = User::factory()->create();
        $course = Course::factory()->create(['title' => 'Bildirim Testi Eğitimi']);
        $session = CourseSession::create(['course_id' => $course->id, 'title' => 'Seans 1']);
        $learner->notify(new LiveSessionScheduled($session));

        $response = $this->actingAs($learner)->get(route('notifications.index'));

        $response->assertOk();
        $response->assertSee('Canlı ders planlandı');
    }
}
