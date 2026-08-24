<?php

namespace Tests\Feature;

use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Models\Activity;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\QuizAttempt;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\QuizAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P1 instructor-facing quiz analytics (§ richer manager/instructor visibility):
 * before this, the only way to know how a quiz was actually performing was
 * to read raw quiz_attempts rows. This is the aggregation those numbers come
 * from, plus a smoke test that the admin table action wires it up correctly.
 */
class QuizAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuiz(): Activity
    {
        $course = Course::factory()->create(['status' => 'published']);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);
        $activity = Activity::create([
            'course_section_id' => $section->id,
            'title' => 'Değerlendirme Sınavı',
            'type' => 'quiz',
            'is_required' => true,
            'pass_score' => 50,
            'pool_size' => 1,
        ]);

        $q1 = QuizQuestion::create(['activity_id' => $activity->id, 'question' => '2+2 kaçtır?', 'type' => 'single_choice', 'sort_order' => 1]);
        QuizOption::create(['quiz_question_id' => $q1->id, 'label' => '4', 'is_correct' => true, 'sort_order' => 1]);
        QuizOption::create(['quiz_question_id' => $q1->id, 'label' => '3', 'is_correct' => false, 'sort_order' => 2]);

        $q2 = QuizQuestion::create(['activity_id' => $activity->id, 'question' => 'Başkent neresidir?', 'type' => 'single_choice', 'sort_order' => 2]);
        QuizOption::create(['quiz_question_id' => $q2->id, 'label' => 'Ankara', 'is_correct' => true, 'sort_order' => 1]);
        QuizOption::create(['quiz_question_id' => $q2->id, 'label' => 'İzmir', 'is_correct' => false, 'sort_order' => 2]);

        return $activity->fresh();
    }

    private function enrollment(Activity $activity): Enrollment
    {
        $learner = User::factory()->create(['role' => 'learner']);

        return Enrollment::create(['user_id' => $learner->id, 'course_id' => $activity->section->course_id]);
    }

    public function test_summarize_reports_zero_state_when_there_are_no_attempts(): void
    {
        $activity = $this->makeQuiz();

        $summary = app(QuizAnalyticsService::class)->summarize($activity);

        $this->assertSame(0, $summary['total_attempts']);
        $this->assertSame(0, $summary['unique_learners']);
        $this->assertNull($summary['pass_rate']);
        $this->assertNull($summary['average_score']);
    }

    public function test_summarize_computes_pass_rate_and_average_score_across_attempts(): void
    {
        $activity = $this->makeQuiz();
        $q1 = $activity->quizQuestions[0];

        QuizAttempt::create(['activity_id' => $activity->id, 'enrollment_id' => $this->enrollment($activity)->id, 'score' => 100, 'passed' => true, 'answers' => [$q1->id => [1]], 'submitted_at' => now()]);
        QuizAttempt::create(['activity_id' => $activity->id, 'enrollment_id' => $this->enrollment($activity)->id, 'score' => 0, 'passed' => false, 'answers' => [$q1->id => [2]], 'submitted_at' => now()]);

        $summary = app(QuizAnalyticsService::class)->summarize($activity);

        $this->assertSame(2, $summary['total_attempts']);
        $this->assertSame(2, $summary['unique_learners']);
        $this->assertSame(50, $summary['pass_rate']);
        $this->assertSame(50, $summary['average_score']);
    }

    /**
     * With pool_size active, not every attempt answers every question in the
     * bank -- the per-question denominator has to be "times this question
     * was actually drawn", not the total attempt count, or a question that
     * happened to never get drawn would misleadingly show a 0% rate instead
     * of "no data".
     */
    public function test_per_question_rate_is_denominated_by_how_often_that_question_was_drawn(): void
    {
        $activity = $this->makeQuiz();
        $q1 = $activity->quizQuestions[0];
        $q2 = $activity->quizQuestions[1];
        $q1CorrectId = $q1->options->firstWhere('is_correct', true)->id;

        QuizAttempt::create(['activity_id' => $activity->id, 'enrollment_id' => $this->enrollment($activity)->id, 'score' => 100, 'passed' => true, 'answers' => [$q1->id => [$q1CorrectId]], 'submitted_at' => now()]);

        $summary = app(QuizAnalyticsService::class)->summarize($activity);
        $stats = collect($summary['per_question']);
        $q1Stats = $stats->firstWhere('question', $q1->question);
        $q2Stats = $stats->firstWhere('question', $q2->question);

        $this->assertSame(1, $q1Stats['answered']);
        $this->assertSame(100, $q1Stats['correct_rate']);
        $this->assertSame(0, $q2Stats['answered']);
        $this->assertNull($q2Stats['correct_rate']);
    }

    public function test_the_analytics_action_is_only_visible_for_quiz_activities(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $quiz = $this->makeQuiz();
        $course = Course::factory()->create();
        $section = $course->sections()->create(['title' => 'Bölüm 1']);
        $video = $section->activities()->create(['title' => 'Video', 'type' => 'video', 'is_required' => true]);

        Livewire::actingAs($admin)->test(ListActivities::class)
            ->assertTableActionVisible('quizAnalytics', $quiz)
            ->assertTableActionHidden('quizAnalytics', $video);
    }

    public function test_the_analytics_modal_shows_the_computed_summary(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $activity = $this->makeQuiz();
        $q1 = $activity->quizQuestions[0];
        $q1CorrectId = $q1->options->firstWhere('is_correct', true)->id;
        QuizAttempt::create(['activity_id' => $activity->id, 'enrollment_id' => $this->enrollment($activity)->id, 'score' => 100, 'passed' => true, 'answers' => [$q1->id => [$q1CorrectId]], 'submitted_at' => now()]);

        // mountTableAction (not callTableAction): this action has no
        // ->action() to run, only content to display, and callTableAction
        // immediately submits+unmounts after mounting -- which for a
        // display-only modal just closes it again before there's anything
        // to assert against.
        //
        // Reading the rendered HTML off the mounted action's own modal
        // content (rather than asserting against the whole component's
        // ->assertSee()) sidesteps a Livewire-testing-only quirk: the
        // modals partial guards itself with a $hasActionsModalRendered
        // flag meant to stop it rendering twice within one real request,
        // but Livewire::test() keeps reusing the same component instance
        // across calls, so that flag is already true from the component's
        // initial mount and the partial silently never renders again for
        // the rest of the test chain -- true in production too, but there
        // each request rehydrates a fresh instance so the flag is always
        // false at the point it matters.
        $component = Livewire::actingAs($admin)->test(ListActivities::class)
            ->mountTableAction('quizAnalytics', $activity);

        $modalHtml = $component->instance()->getMountedAction()->getModalContent()->render();

        $this->assertStringContainsString('2+2 kaçtır?', $modalHtml);
    }
}
