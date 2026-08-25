<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz 101 QA sweep: classroom() (the live-session page) has always checked
 * "is this your live class" (instructor who owns the course, or an enrolled
 * learner) -- but the AJAX endpoints its own JS calls (classMessage,
 * classPresence, classSignals, sendSignal, sessionAttendanceStatus) had no
 * check of their own at all. A direct request to any of them with any
 * session id worked for any authenticated user, page access or not. This
 * covers the fix: an outsider (authenticated, but neither enrolled in nor
 * teaching the course) must be blocked from every one of them.
 */
class ClassroomAccessBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private function outsiderAndSession(): array
    {
        $outsider = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published', 'delivery_type' => 'online']);
        $session = $course->sessions()->create(['title' => 'Seans 1', 'room_key' => 'boundary-room']);

        return [$outsider, $session];
    }

    public function test_an_outsider_cannot_post_a_class_message(): void
    {
        [$outsider, $session] = $this->outsiderAndSession();

        $this->actingAs($outsider)->post(route('sessions.messages', $session), ['body' => 'merhaba'])->assertForbidden();
        $this->assertDatabaseMissing('class_messages', ['course_session_id' => $session->id]);
    }

    public function test_an_outsider_cannot_register_presence_or_see_who_is_present(): void
    {
        [$outsider, $session] = $this->outsiderAndSession();

        $this->actingAs($outsider)->get(route('sessions.presence', $session))->assertForbidden();
    }

    public function test_an_outsider_cannot_send_or_read_signals(): void
    {
        [$outsider, $session] = $this->outsiderAndSession();

        $this->actingAs($outsider)->post(route('sessions.signals.send', $session), ['type' => 'offer', 'payload' => ['sdp' => 'x']])->assertForbidden();
        $this->actingAs($outsider)->get(route('sessions.signals', $session))->assertForbidden();
        $this->assertDatabaseMissing('class_signals', ['course_session_id' => $session->id]);
    }

    public function test_an_outsider_cannot_read_attendance_status(): void
    {
        [$outsider, $session] = $this->outsiderAndSession();

        $this->actingAs($outsider)->get(route('sessions.attendance-status', $session))->assertForbidden();
    }

    public function test_an_enrolled_learner_can_still_use_every_classroom_endpoint(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published', 'delivery_type' => 'online']);
        $session = $course->sessions()->create(['title' => 'Seans 1', 'room_key' => 'legit-room']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $this->actingAs($learner)->post(route('sessions.messages', $session), ['body' => 'merhaba'])->assertRedirect();
        $this->actingAs($learner)->get(route('sessions.presence', $session))->assertOk();
        $this->actingAs($learner)->get(route('sessions.signals', $session))->assertOk();
        $this->actingAs($learner)->post(route('sessions.signals.send', $session), ['type' => 'offer', 'payload' => ['sdp' => 'x']])->assertOk();
        $this->actingAs($learner)->get(route('sessions.attendance-status', $session))->assertOk();
    }

    public function test_the_owning_instructor_can_still_use_every_classroom_endpoint(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['status' => 'published', 'delivery_type' => 'online', 'instructor_id' => $instructor->id]);
        $session = $course->sessions()->create(['title' => 'Seans 1', 'room_key' => 'instructor-room']);

        $this->actingAs($instructor)->post(route('sessions.messages', $session), ['body' => 'merhaba'])->assertRedirect();
        $this->actingAs($instructor)->get(route('sessions.presence', $session))->assertOk();
    }

    public function test_an_instructor_of_a_different_course_cannot_touch_someone_elses_session(): void
    {
        $otherInstructor = User::factory()->create(['role' => 'instructor']);
        [, $session] = $this->outsiderAndSession();

        $this->actingAs($otherInstructor)->post(route('sessions.messages', $session), ['body' => 'x'])->assertForbidden();
    }

    public function test_the_room_key_shortlink_redirects_an_enrolled_learner_to_the_classroom(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published', 'delivery_type' => 'online']);
        $session = $course->sessions()->create(['title' => 'Seans 1', 'room_key' => 'shortlink-room']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $this->actingAs($learner)->get(route('meet.join', 'shortlink-room'))->assertRedirect(route('sessions.classroom', $session));
    }

    public function test_the_room_key_shortlink_blocks_an_outsider(): void
    {
        [$outsider] = $this->outsiderAndSession();
        $course = Course::factory()->create(['status' => 'published', 'delivery_type' => 'online']);
        $course->sessions()->create(['title' => 'Seans 1', 'room_key' => 'blocked-shortlink']);

        $this->actingAs($outsider)->get(route('meet.join', 'blocked-shortlink'))->assertForbidden();
    }

    public function test_the_room_key_shortlink_requires_login(): void
    {
        $course = Course::factory()->create(['status' => 'published', 'delivery_type' => 'online']);
        $course->sessions()->create(['title' => 'Seans 1', 'room_key' => 'guest-shortlink']);

        $this->get(route('meet.join', 'guest-shortlink'))->assertForbidden();
    }

    /**
     * Regression: the classroom page renders inside the shared authenticated
     * layout, and relies on body.classroom-page to switch classroom-fullscreen.css
     * into its fullscreen mode -- hiding the persistent left sidebar/topbar so
     * the video stage and control bar get the full viewport. classroom.css
     * targeted the pre-sidebar markup (.site-nav, a bare 100vh .page-shell)
     * and never accounted for the sidebar/topbar now wrapping every page, so
     * the control bar was pushed below the fold. This just locks in the hook
     * the CSS depends on; it can't assert computed layout.
     */
    public function test_the_classroom_page_carries_the_fullscreen_body_class(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published', 'delivery_type' => 'online']);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->get(route('sessions.classroom', $session));

        $response->assertOk();
        $response->assertSee('<body class="classroom-page">', false);
    }
}
