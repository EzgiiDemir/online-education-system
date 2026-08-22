<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * P0 Assessment system (§16), assignment slice: text/file/URL submission
 * with instructor grading. Unlike quizzes, this can never be auto-graded --
 * completion only happens once a real instructor (or admin) assigns a score,
 * and only that route is ever allowed to set score/graded_at.
 */
class AssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssignment(array $attrs = []): Activity
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['status' => 'published', 'instructor_id' => $instructor->id]);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);

        return Activity::create(array_merge([
            'course_section_id' => $section->id,
            'title' => 'Dönem Ödevi',
            'type' => 'assignment',
            'submission_type' => 'text',
            'is_required' => true,
            'pass_score' => 60,
        ], $attrs));
    }

    private function enroll(Activity $activity): User
    {
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $activity->section->course_id]);

        return $learner;
    }

    public function test_learner_can_submit_a_text_assignment(): void
    {
        $activity = $this->makeAssignment();
        $learner = $this->enroll($activity);

        $response = $this->actingAs($learner)->post(route('activities.submissions.store', $activity), ['content' => 'Bu benim cevabım.']);

        $response->assertRedirect(route('activities.view', $activity));
        $this->assertDatabaseHas('assignment_submissions', ['activity_id' => $activity->id, 'content' => 'Bu benim cevabım.']);
    }

    public function test_learner_can_submit_a_file_assignment(): void
    {
        $activity = $this->makeAssignment(['submission_type' => 'file']);
        $learner = $this->enroll($activity);

        $response = $this->actingAs($learner)->post(route('activities.submissions.store', $activity), ['file' => UploadedFile::fake()->create('odev.pdf', 100)]);

        $response->assertRedirect(route('activities.view', $activity));
        $submission = AssignmentSubmission::where('activity_id', $activity->id)->first();
        $this->assertNotNull($submission->file_path);
    }

    public function test_submission_does_not_auto_complete_the_activity(): void
    {
        $activity = $this->makeAssignment();
        $learner = $this->enroll($activity);

        $this->actingAs($learner)->post(route('activities.submissions.store', $activity), ['content' => 'Cevap']);

        $this->assertDatabaseMissing('activity_progress', ['enrollment_id' => $learner->enrollments()->first()->id, 'activity_id' => $activity->id, 'completed' => 1]);
    }

    public function test_a_learner_cannot_grade_their_own_submission(): void
    {
        $activity = $this->makeAssignment();
        $learner = $this->enroll($activity);
        $this->actingAs($learner)->post(route('activities.submissions.store', $activity), ['content' => 'Cevap']);
        $submission = AssignmentSubmission::where('activity_id', $activity->id)->first();

        $response = $this->actingAs($learner)->post(route('instructor.submissions.grade', $submission), ['score' => 100]);

        $response->assertForbidden();
        $this->assertDatabaseHas('assignment_submissions', ['id' => $submission->id, 'score' => null]);
    }

    public function test_owning_instructor_can_grade_a_submission(): void
    {
        $activity = $this->makeAssignment(['pass_score' => 60]);
        $learner = $this->enroll($activity);
        $this->actingAs($learner)->post(route('activities.submissions.store', $activity), ['content' => 'Cevap']);
        $submission = AssignmentSubmission::where('activity_id', $activity->id)->first();
        $instructor = $activity->section->course->instructor;

        $response = $this->actingAs($instructor)->post(route('instructor.submissions.grade', $submission), ['score' => 85, 'feedback' => 'Aferin']);

        $response->assertRedirect();
        $this->assertDatabaseHas('assignment_submissions', ['id' => $submission->id, 'score' => 85, 'feedback' => 'Aferin']);
    }

    public function test_a_passing_grade_marks_the_activity_complete(): void
    {
        $activity = $this->makeAssignment(['pass_score' => 60]);
        $learner = $this->enroll($activity);
        $this->actingAs($learner)->post(route('activities.submissions.store', $activity), ['content' => 'Cevap']);
        $submission = AssignmentSubmission::where('activity_id', $activity->id)->first();
        $instructor = $activity->section->course->instructor;

        $this->actingAs($instructor)->post(route('instructor.submissions.grade', $submission), ['score' => 75]);

        $enrollment = $learner->enrollments()->first();
        $this->assertDatabaseHas('activity_progress', ['enrollment_id' => $enrollment->id, 'activity_id' => $activity->id, 'completed' => 1]);
        $this->assertSame('completed', $enrollment->fresh()->status);
    }

    public function test_a_failing_grade_does_not_mark_the_activity_complete(): void
    {
        $activity = $this->makeAssignment(['pass_score' => 60]);
        $learner = $this->enroll($activity);
        $this->actingAs($learner)->post(route('activities.submissions.store', $activity), ['content' => 'Cevap']);
        $submission = AssignmentSubmission::where('activity_id', $activity->id)->first();
        $instructor = $activity->section->course->instructor;

        $this->actingAs($instructor)->post(route('instructor.submissions.grade', $submission), ['score' => 40]);

        $this->assertDatabaseMissing('activity_progress', ['enrollment_id' => $learner->enrollments()->first()->id, 'activity_id' => $activity->id, 'completed' => 1]);
    }

    public function test_an_instructor_who_does_not_own_the_course_cannot_grade(): void
    {
        $activity = $this->makeAssignment();
        $learner = $this->enroll($activity);
        $this->actingAs($learner)->post(route('activities.submissions.store', $activity), ['content' => 'Cevap']);
        $submission = AssignmentSubmission::where('activity_id', $activity->id)->first();
        $otherInstructor = User::factory()->create(['role' => 'instructor']);

        $this->actingAs($otherInstructor)->post(route('instructor.submissions.grade', $submission), ['score' => 100])->assertForbidden();
    }

    public function test_cannot_resubmit_after_already_passing(): void
    {
        $activity = $this->makeAssignment(['pass_score' => 60]);
        $learner = $this->enroll($activity);
        $this->actingAs($learner)->post(route('activities.submissions.store', $activity), ['content' => 'Cevap 1']);
        $submission = AssignmentSubmission::where('activity_id', $activity->id)->first();
        $instructor = $activity->section->course->instructor;
        $this->actingAs($instructor)->post(route('instructor.submissions.grade', $submission), ['score' => 90]);

        $response = $this->actingAs($learner)->post(route('activities.submissions.store', $activity), ['content' => 'Cevap 2']);

        $response->assertStatus(422);
    }

    public function test_the_watch_time_heartbeat_endpoint_rejects_assignment_activities(): void
    {
        $activity = $this->makeAssignment();
        $learner = $this->enroll($activity);

        $this->actingAs($learner)->post(route('activities.heartbeat', $activity))->assertStatus(422);
    }

    public function test_grading_queue_page_lists_submissions_for_the_owning_instructor(): void
    {
        $activity = $this->makeAssignment();
        $learner = User::factory()->create(['role' => 'learner', 'name' => 'Teslim Eden Öğrenci']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $activity->section->course_id]);
        $this->actingAs($learner)->post(route('activities.submissions.store', $activity), ['content' => 'Değerlendirilecek cevap']);
        $instructor = $activity->section->course->instructor;

        $response = $this->actingAs($instructor)->get(route('instructor.activities.submissions', $activity));

        $response->assertOk();
        $response->assertSee('Teslim Eden Öğrenci');
        $response->assertSee('Değerlendirilecek cevap');
    }
}
