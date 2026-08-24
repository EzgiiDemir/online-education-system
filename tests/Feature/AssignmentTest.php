<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\RubricCriterion;
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

    /**
     * P1 rubric grading: a rubric is opt-in per activity (RubricCriteria
     * RelationManager rows). It's just a structured way of arriving at the
     * same 0-100 `score` column everything else already reads -- these
     * tests focus on that derivation and on the plain-score path staying
     * exactly as it was for an activity with no rubric.
     */
    public function test_grading_with_a_rubric_computes_the_score_from_criteria_points(): void
    {
        $activity = $this->makeAssignment(['pass_score' => 60]);
        $content = RubricCriterion::create(['activity_id' => $activity->id, 'label' => 'İçerik', 'max_points' => 60, 'sort_order' => 1]);
        $clarity = RubricCriterion::create(['activity_id' => $activity->id, 'label' => 'Anlaşılırlık', 'max_points' => 40, 'sort_order' => 2]);
        $learner = $this->enroll($activity);
        $this->actingAs($learner)->post(route('activities.submissions.store', $activity), ['content' => 'Cevap']);
        $submission = AssignmentSubmission::where('activity_id', $activity->id)->first();
        $instructor = $activity->section->course->instructor;

        $response = $this->actingAs($instructor)->post(route('instructor.submissions.grade', $submission), [
            'rubric_scores' => [$content->id => 45, $clarity->id => 30],
        ]);

        $response->assertRedirect();
        // (45 + 30) / (60 + 40) * 100 = 75
        $this->assertDatabaseHas('assignment_submissions', ['id' => $submission->id, 'score' => 75]);
        $this->assertSame([45, 30], array_values($submission->fresh()->rubric_scores));
    }

    public function test_rubric_points_are_clamped_to_each_criterions_max_server_side(): void
    {
        $activity = $this->makeAssignment(['pass_score' => 60]);
        $criterion = RubricCriterion::create(['activity_id' => $activity->id, 'label' => 'İçerik', 'max_points' => 20]);
        $learner = $this->enroll($activity);
        $this->actingAs($learner)->post(route('activities.submissions.store', $activity), ['content' => 'Cevap']);
        $submission = AssignmentSubmission::where('activity_id', $activity->id)->first();
        $instructor = $activity->section->course->instructor;

        $this->actingAs($instructor)->post(route('instructor.submissions.grade', $submission), [
            'rubric_scores' => [$criterion->id => 999],
        ]);

        $this->assertDatabaseHas('assignment_submissions', ['id' => $submission->id, 'score' => 100]);
        $this->assertSame(20, $submission->fresh()->rubric_scores[$criterion->id]);
    }

    public function test_a_rubric_graded_passing_score_still_marks_the_activity_complete(): void
    {
        $activity = $this->makeAssignment(['pass_score' => 60]);
        $criterion = RubricCriterion::create(['activity_id' => $activity->id, 'label' => 'İçerik', 'max_points' => 100]);
        $learner = $this->enroll($activity);
        $this->actingAs($learner)->post(route('activities.submissions.store', $activity), ['content' => 'Cevap']);
        $submission = AssignmentSubmission::where('activity_id', $activity->id)->first();
        $instructor = $activity->section->course->instructor;

        $this->actingAs($instructor)->post(route('instructor.submissions.grade', $submission), ['rubric_scores' => [$criterion->id => 80]]);

        $enrollment = $learner->enrollments()->first();
        $this->assertSame('completed', $enrollment->fresh()->status);
    }

    public function test_an_activity_without_a_rubric_grades_with_a_plain_score_and_no_breakdown(): void
    {
        $activity = $this->makeAssignment(['pass_score' => 60]);
        $learner = $this->enroll($activity);
        $this->actingAs($learner)->post(route('activities.submissions.store', $activity), ['content' => 'Cevap']);
        $submission = AssignmentSubmission::where('activity_id', $activity->id)->first();
        $instructor = $activity->section->course->instructor;

        $this->actingAs($instructor)->post(route('instructor.submissions.grade', $submission), ['score' => 85]);

        $submission->refresh();
        $this->assertSame(85, $submission->score);
        $this->assertNull($submission->rubric_scores);
    }

    public function test_grading_queue_page_shows_rubric_inputs_when_a_rubric_exists(): void
    {
        $activity = $this->makeAssignment();
        RubricCriterion::create(['activity_id' => $activity->id, 'label' => 'Yaratıcılık', 'max_points' => 50]);
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $activity->section->course_id]);
        $this->actingAs($learner)->post(route('activities.submissions.store', $activity), ['content' => 'Cevap']);
        $instructor = $activity->section->course->instructor;

        $response = $this->actingAs($instructor)->get(route('instructor.activities.submissions', $activity));

        $response->assertOk();
        $response->assertSee('Yaratıcılık');
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
