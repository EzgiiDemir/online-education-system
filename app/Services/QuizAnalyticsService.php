<?php

namespace App\Services;

use App\Models\Activity;
use Illuminate\Support\Collection;

/**
 * Read-only aggregation over an activity's quiz_attempts, for the instructor-
 * facing "how is this quiz actually performing" view. Deliberately recomputed
 * on demand rather than cached/stored anywhere: attempts are append-only and
 * per-quiz volumes are small (classroom-scale LMS, not a public exam
 * platform), so a stored/denormalized count would just be another place for
 * drift to creep in for no real performance win.
 */
class QuizAnalyticsService
{
    public function summarize(Activity $activity): array
    {
        $attempts = $activity->quizAttempts()->with('enrollment')->get();
        $totalAttempts = $attempts->count();

        return [
            'total_attempts' => $totalAttempts,
            // By user_id, not enrollment_id: a learner who retakes this
            // quiz in a recertification cycle gets a new enrollment_id, but
            // is still one person, not two "unique learners".
            'unique_learners' => $attempts->pluck('enrollment.user_id')->unique()->count(),
            'pass_rate' => $totalAttempts ? (int) round($attempts->where('passed', true)->count() / $totalAttempts * 100) : null,
            'average_score' => $totalAttempts ? (int) round($attempts->avg('score')) : null,
            'per_question' => $this->perQuestionStats($activity, $attempts),
        ];
    }

    /**
     * Per-question correct rate, counted only over attempts that actually
     * included that question -- with pool_size in play, not every attempt
     * answers every question in the bank, so the denominator has to be
     * "times this question was drawn", not "total attempts".
     */
    private function perQuestionStats(Activity $activity, Collection $attempts): Collection
    {
        return $activity->quizQuestions->map(function ($question) use ($attempts) {
            $correctOptionIds = $question->options->where('is_correct', true)->pluck('id')->sort()->values()->all();

            $answered = 0;
            $correct = 0;
            foreach ($attempts as $attempt) {
                if (! array_key_exists($question->id, $attempt->answers)) {
                    continue;
                }
                $answered++;
                $selected = collect($attempt->answers[$question->id])->map(fn ($id) => (int) $id)->sort()->values()->all();
                if ($selected === $correctOptionIds) {
                    $correct++;
                }
            }

            return [
                'question' => $question->question,
                'answered' => $answered,
                'correct_rate' => $answered ? (int) round($correct / $answered * 100) : null,
            ];
        });
    }
}
