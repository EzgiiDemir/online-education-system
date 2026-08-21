<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 Assessment system (§16), quiz slice only -- assignments (text/file/URL
 * submissions with instructor grading) are explicitly out of scope for this
 * phase, not silently half-built alongside it.
 *
 * Grading must be entirely server-side: the client only ever posts which
 * option IDs it selected, never a score or a pass/fail claim, matching the
 * same anti-cheat posture as the watch-time system elsewhere in this app.
 */
class QuizTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuiz(array $activityAttrs = []): Activity
    {
        $course = Course::factory()->create(['status' => 'published']);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);
        $activity = Activity::create(array_merge([
            'course_section_id' => $section->id,
            'title' => 'Değerlendirme Sınavı',
            'type' => 'quiz',
            'is_required' => true,
            'pass_score' => 70,
        ], $activityAttrs));

        $q1 = QuizQuestion::create(['activity_id' => $activity->id, 'question' => '2+2 kaçtır?', 'type' => 'single_choice', 'sort_order' => 1]);
        QuizOption::create(['quiz_question_id' => $q1->id, 'label' => '3', 'is_correct' => false, 'sort_order' => 1]);
        QuizOption::create(['quiz_question_id' => $q1->id, 'label' => '4', 'is_correct' => true, 'sort_order' => 2]);

        $q2 = QuizQuestion::create(['activity_id' => $activity->id, 'question' => 'Aşağıdakilerden hangileri asal sayıdır?', 'type' => 'multi_choice', 'sort_order' => 2]);
        QuizOption::create(['quiz_question_id' => $q2->id, 'label' => '2', 'is_correct' => true, 'sort_order' => 1]);
        QuizOption::create(['quiz_question_id' => $q2->id, 'label' => '3', 'is_correct' => true, 'sort_order' => 2]);
        QuizOption::create(['quiz_question_id' => $q2->id, 'label' => '4', 'is_correct' => false, 'sort_order' => 3]);

        return $activity->fresh();
    }

    private function enroll(Activity $activity): User
    {
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $activity->section->course_id]);

        return $learner;
    }

    public function test_all_correct_answers_score_100_percent_and_pass(): void
    {
        $activity = $this->makeQuiz();
        $learner = $this->enroll($activity);
        $q1 = $activity->quizQuestions[0];
        $q2 = $activity->quizQuestions[1];
        $correctQ1 = $q1->options->firstWhere('is_correct', true)->id;
        $correctQ2Ids = $q2->options->where('is_correct', true)->pluck('id')->all();

        $response = $this->actingAs($learner)->post(route('activities.quiz-attempts.store', $activity), [
            'answers' => [$q1->id => $correctQ1, $q2->id => $correctQ2Ids],
        ]);

        $response->assertRedirect(route('activities.view', $activity));
        $this->assertDatabaseHas('quiz_attempts', ['activity_id' => $activity->id, 'enrollment_id' => $learner->enrollments()->first()->id, 'score' => 100, 'passed' => true]);
    }

    public function test_partial_correct_answers_score_below_threshold_and_fail(): void
    {
        $activity = $this->makeQuiz(['pass_score' => 70]);
        $learner = $this->enroll($activity);
        $q1 = $activity->quizQuestions[0];
        $q2 = $activity->quizQuestions[1];
        $wrongQ1 = $q1->options->firstWhere('is_correct', false)->id;
        $correctQ2Ids = $q2->options->where('is_correct', true)->pluck('id')->all();

        $this->actingAs($learner)->post(route('activities.quiz-attempts.store', $activity), [
            'answers' => [$q1->id => $wrongQ1, $q2->id => $correctQ2Ids],
        ]);

        $this->assertDatabaseHas('quiz_attempts', ['activity_id' => $activity->id, 'score' => 50, 'passed' => false]);
    }

    public function test_multi_choice_requires_the_exact_set_of_correct_options_not_a_subset(): void
    {
        $activity = $this->makeQuiz();
        $learner = $this->enroll($activity);
        $q1 = $activity->quizQuestions[0];
        $q2 = $activity->quizQuestions[1];
        $correctQ1 = $q1->options->firstWhere('is_correct', true)->id;
        $onlyOneOfTwoCorrect = [$q2->options->where('is_correct', true)->first()->id];

        $this->actingAs($learner)->post(route('activities.quiz-attempts.store', $activity), [
            'answers' => [$q1->id => $correctQ1, $q2->id => $onlyOneOfTwoCorrect],
        ]);

        $this->assertDatabaseHas('quiz_attempts', ['activity_id' => $activity->id, 'score' => 50]);
    }

    public function test_passing_a_required_quiz_marks_the_activity_progress_complete(): void
    {
        $activity = $this->makeQuiz();
        $learner = $this->enroll($activity);
        $q1 = $activity->quizQuestions[0];
        $q2 = $activity->quizQuestions[1];
        $correctQ1 = $q1->options->firstWhere('is_correct', true)->id;
        $correctQ2Ids = $q2->options->where('is_correct', true)->pluck('id')->all();

        $this->actingAs($learner)->post(route('activities.quiz-attempts.store', $activity), [
            'answers' => [$q1->id => $correctQ1, $q2->id => $correctQ2Ids],
        ]);

        $enrollment = $learner->enrollments()->first();
        $this->assertDatabaseHas('activity_progress', ['enrollment_id' => $enrollment->id, 'activity_id' => $activity->id, 'completed' => 1]);
        $this->assertSame('completed', $enrollment->fresh()->status);
    }

    public function test_a_failing_attempt_does_not_mark_the_activity_complete(): void
    {
        $activity = $this->makeQuiz(['pass_score' => 70]);
        $learner = $this->enroll($activity);
        $q1 = $activity->quizQuestions[0];
        $wrongQ1 = $q1->options->firstWhere('is_correct', false)->id;

        $this->actingAs($learner)->post(route('activities.quiz-attempts.store', $activity), [
            'answers' => [$q1->id => $wrongQ1, $activity->quizQuestions[1]->id => []],
        ]);

        $this->assertDatabaseMissing('activity_progress', ['enrollment_id' => $learner->enrollments()->first()->id, 'activity_id' => $activity->id, 'completed' => 1]);
    }

    public function test_client_supplied_score_or_passed_fields_are_ignored(): void
    {
        $activity = $this->makeQuiz(['pass_score' => 70]);
        $learner = $this->enroll($activity);
        $q1 = $activity->quizQuestions[0];
        $wrongQ1 = $q1->options->firstWhere('is_correct', false)->id;

        $this->actingAs($learner)->post(route('activities.quiz-attempts.store', $activity), [
            'answers' => [$q1->id => $wrongQ1, $activity->quizQuestions[1]->id => []],
            'score' => 100,
            'passed' => true,
        ]);

        $this->assertDatabaseHas('quiz_attempts', ['activity_id' => $activity->id, 'passed' => false]);
    }

    public function test_max_attempts_is_enforced(): void
    {
        $activity = $this->makeQuiz(['pass_score' => 70, 'max_attempts' => 1]);
        $learner = $this->enroll($activity);
        $q1 = $activity->quizQuestions[0];
        $wrongQ1 = $q1->options->firstWhere('is_correct', false)->id;
        $payload = ['answers' => [$q1->id => $wrongQ1, $activity->quizQuestions[1]->id => []]];

        $this->actingAs($learner)->post(route('activities.quiz-attempts.store', $activity), $payload);
        $second = $this->actingAs($learner)->post(route('activities.quiz-attempts.store', $activity), $payload);

        $second->assertStatus(422);
        $this->assertSame(1, \App\Models\QuizAttempt::where('activity_id', $activity->id)->count());
    }

    public function test_cannot_reattempt_after_already_passing(): void
    {
        $activity = $this->makeQuiz();
        $learner = $this->enroll($activity);
        $q1 = $activity->quizQuestions[0];
        $q2 = $activity->quizQuestions[1];
        $correctQ1 = $q1->options->firstWhere('is_correct', true)->id;
        $correctQ2Ids = $q2->options->where('is_correct', true)->pluck('id')->all();
        $payload = ['answers' => [$q1->id => $correctQ1, $q2->id => $correctQ2Ids]];

        $this->actingAs($learner)->post(route('activities.quiz-attempts.store', $activity), $payload);
        $second = $this->actingAs($learner)->post(route('activities.quiz-attempts.store', $activity), $payload);

        $second->assertStatus(422);
        $this->assertSame(1, \App\Models\QuizAttempt::where('activity_id', $activity->id)->count());
    }

    public function test_a_learner_not_enrolled_in_the_course_cannot_submit_an_attempt(): void
    {
        $activity = $this->makeQuiz();
        $outsider = User::factory()->create(['role' => 'learner']);

        $response = $this->actingAs($outsider)->post(route('activities.quiz-attempts.store', $activity), [
            'answers' => [$activity->quizQuestions[0]->id => 999],
        ]);

        $response->assertStatus(404);
    }

    public function test_quiz_view_shows_questions_and_options_to_an_enrolled_learner(): void
    {
        $activity = $this->makeQuiz();
        $learner = $this->enroll($activity);

        $response = $this->actingAs($learner)->get(route('activities.view', $activity));

        $response->assertOk();
        $response->assertSee('2+2 kaçtır?');
        $response->assertSee('asal sayıdır');
    }

    public function test_the_watch_time_heartbeat_endpoint_rejects_quiz_activities(): void
    {
        $activity = $this->makeQuiz();
        $learner = $this->enroll($activity);

        $this->actingAs($learner)->post(route('activities.heartbeat', $activity))->assertStatus(422);
    }
}
