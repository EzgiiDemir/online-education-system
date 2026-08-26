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
        $response->assertSee('id="documentInput"', false);
    }

    /**
     * "Present" (broadcasting to everyone's main stage) stays
     * instructor-only, but "Add document" -- a participant sharing their
     * own file into the session's Files panel for review -- is open to
     * everyone; a learner presenting their work is the whole point.
     */
    public function test_a_learner_does_not_see_host_controls_or_the_present_button_but_can_raise_a_hand_and_add_a_document(): void
    {
        [, $course, $session] = $this->instructorCourseSession();
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->get(route('sessions.classroom', $session));

        $response->assertOk();
        $response->assertDontSee('id="hostControls"', false);
        $response->assertDontSee('id="presentInput"', false);
        $response->assertSee('id="raiseHand"', false);
        $response->assertSee('id="documentInput"', false);
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

    /**
     * Uploading itself (into the Files panel, for anyone to see and the
     * instructor to grade) is open to any classroom participant -- a
     * student presenting their own work is the point of the "Add document"
     * button. Broadcasting a file to hijack everyone's shared main stage
     * (the 'presentation-shown' signal, see above) is the separate,
     * still instructor-only action.
     */
    public function test_an_enrolled_learner_can_upload_a_document(): void
    {
        [, $course, $session] = $this->instructorCourseSession();
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->postJson(route('sessions.files.share', $session), [
            'file' => UploadedFile::fake()->create('odev.pdf', 500, 'application/pdf'),
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['id', 'title', 'type', 'url', 'uploaderId', 'uploaderName']);
        $this->assertDatabaseHas('materials', ['course_session_id' => $session->id, 'uploaded_by_id' => $learner->id, 'title' => 'odev.pdf']);
    }

    public function test_an_outsider_cannot_upload_a_document(): void
    {
        [, , $session] = $this->instructorCourseSession();
        $outsider = User::factory()->create(['role' => 'learner']);

        $this->actingAs($outsider)->postJson(route('sessions.files.share', $session), [
            'file' => UploadedFile::fake()->create('x.pdf', 500, 'application/pdf'),
        ])->assertForbidden();
    }

    public function test_the_owning_instructor_can_grade_a_shared_document_and_the_uploader_is_notified(): void
    {
        [$instructor, $course, $session] = $this->instructorCourseSession();
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);
        $material = \App\Models\Material::create(['course_session_id' => $session->id, 'uploaded_by_id' => $learner->id, 'title' => 'odev.pdf', 'type' => 'application/pdf', 'path' => 'materials/odev.pdf']);

        $response = $this->actingAs($instructor)->postJson(route('materials.grade', $material), ['score' => 88]);

        $response->assertOk();
        $response->assertJson(['ok' => true, 'score' => 88]);
        $this->assertDatabaseHas('materials', ['id' => $material->id, 'score' => 88, 'graded_by_id' => $instructor->id]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $learner->id, 'type' => \App\Notifications\SharedDocumentGraded::class]);
    }

    public function test_a_non_owning_instructor_cannot_grade_a_shared_document(): void
    {
        [, $course, $session] = $this->instructorCourseSession();
        $otherInstructor = User::factory()->create(['role' => 'instructor']);
        $material = \App\Models\Material::create(['course_session_id' => $session->id, 'title' => 'odev.pdf', 'type' => 'application/pdf', 'path' => 'materials/odev.pdf']);

        $this->actingAs($otherInstructor)->postJson(route('materials.grade', $material), ['score' => 50])->assertForbidden();
    }

    public function test_a_learner_cannot_grade_a_shared_document(): void
    {
        [, $course, $session] = $this->instructorCourseSession();
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);
        $material = \App\Models\Material::create(['course_session_id' => $session->id, 'title' => 'odev.pdf', 'type' => 'application/pdf', 'path' => 'materials/odev.pdf']);

        $this->actingAs($learner)->postJson(route('materials.grade', $material), ['score' => 100])->assertForbidden();
    }

    public function test_score_must_be_within_zero_to_a_hundred(): void
    {
        [$instructor, , $session] = $this->instructorCourseSession();
        $material = \App\Models\Material::create(['course_session_id' => $session->id, 'title' => 'odev.pdf', 'type' => 'application/pdf', 'path' => 'materials/odev.pdf']);

        $this->actingAs($instructor)->postJson(route('materials.grade', $material), ['score' => 101])->assertStatus(422);
    }
}
