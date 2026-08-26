<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Faz 110: the classroom's video-mesh signaling channel (class_signals /
 * sendSignal / classSignals -- already broadcast-capable via a nullable
 * recipient_id) now carries host-broadcast controls (mute all / cameras off
 * all / stop all screen shares) and a "present a file" flow. Since a P2P
 * mesh has no server-side media relay, "mute all" cannot literally force
 * anyone's hardware off -- it broadcasts a request that each participant's
 * own client honors on their own track, the same pattern real conferencing
 * apps without an SFU rely on. The UI only shows the host controls to the
 * instructor, but that alone doesn't stop a direct POST from a participant
 * still in the room, so it's enforced server-side too.
 */
class ClassroomHostControlsTest extends TestCase
{
    use RefreshDatabase;

    private function instructorCourseSession(): array
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['status' => 'published', 'delivery_type' => 'online', 'instructor_id' => $instructor->id]);
        $session = $course->sessions()->create(['title' => 'Seans 1']);

        return [$instructor, $course, $session];
    }

    public function test_the_instructor_sees_host_controls_and_the_present_button(): void
    {
        [$instructor, , $session] = $this->instructorCourseSession();

        $response = $this->actingAs($instructor)->get(route('sessions.classroom', $session));

        $response->assertOk();
        $response->assertSee('id="hostControls"', false);
        $response->assertSee('id="presentInput"', false);
        $response->assertSee('id="raiseHand"', false);
    }

    public function test_a_learner_does_not_see_host_controls_or_the_present_button_but_can_raise_a_hand(): void
    {
        [, $course, $session] = $this->instructorCourseSession();
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->get(route('sessions.classroom', $session));

        $response->assertOk();
        $response->assertDontSee('id="hostControls"', false);
        $response->assertDontSee('id="presentInput"', false);
        $response->assertSee('id="raiseHand"', false);
    }

    public function test_the_instructor_can_broadcast_mute_all(): void
    {
        [$instructor, , $session] = $this->instructorCourseSession();

        $this->actingAs($instructor)->postJson(route('sessions.signals.send', $session), [
            'type' => 'mute-all', 'payload' => [],
        ])->assertOk();
    }

    public function test_an_enrolled_learner_cannot_broadcast_mute_all(): void
    {
        [, $course, $session] = $this->instructorCourseSession();
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $this->actingAs($learner)->postJson(route('sessions.signals.send', $session), [
            'type' => 'mute-all', 'payload' => [],
        ])->assertForbidden();
    }

    public function test_an_enrolled_learner_cannot_broadcast_cameras_off_all_or_stop_all_screen_shares(): void
    {
        [, $course, $session] = $this->instructorCourseSession();
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $this->actingAs($learner)->postJson(route('sessions.signals.send', $session), ['type' => 'camera-off-all', 'payload' => []])->assertForbidden();
        $this->actingAs($learner)->postJson(route('sessions.signals.send', $session), ['type' => 'screenshare-stop-all', 'payload' => []])->assertForbidden();
    }

    public function test_a_learner_can_still_broadcast_a_hand_raise(): void
    {
        [, $course, $session] = $this->instructorCourseSession();
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $this->actingAs($learner)->postJson(route('sessions.signals.send', $session), [
            'type' => 'hand-raise', 'payload' => ['raised' => true],
        ])->assertOk();
    }

    public function test_an_enrolled_learner_cannot_fake_a_presentation_broadcast(): void
    {
        [, $course, $session] = $this->instructorCourseSession();
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $this->actingAs($learner)->postJson(route('sessions.signals.send', $session), [
            'type' => 'presentation-shown', 'payload' => ['url' => 'https://evil.example/x.pdf', 'type' => 'application/pdf', 'title' => 'x'],
        ])->assertForbidden();
    }

    public function test_uploading_a_presentation_file_returns_its_url_and_type_as_json(): void
    {
        [$instructor, , $session] = $this->instructorCourseSession();

        $response = $this->actingAs($instructor)->postJson(route('sessions.files.share', $session), [
            'file' => UploadedFile::fake()->create('slides.pdf', 500, 'application/pdf'),
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['title', 'type', 'url']);
        $this->assertSame('slides.pdf', $response->json('title'));
    }

    public function test_a_non_instructor_cannot_upload_a_presentation_file(): void
    {
        [, $course, $session] = $this->instructorCourseSession();
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $this->actingAs($learner)->postJson(route('sessions.files.share', $session), [
            'file' => UploadedFile::fake()->create('slides.pdf', 500, 'application/pdf'),
        ])->assertForbidden();
    }
}
