<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Enrollment;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use Illuminate\Support\Collection;

/**
 * Question-bank support: when an activity's pool_size is set, an attempt
 * only has to answer a random subset of the full question bank instead of
 * every question every time. The subset has to be *derived*, not
 * client-supplied and not stored in a session -- a learner could otherwise
 * shrink the denominator of their own score by omitting questions they
 * don't know, or a page reload between "shown" and "submitted" could
 * silently change which questions count. Deriving it from a hash of
 * (enrollment, activity, attempt number) means the exact same subset is
 * picked both when the quiz page renders and when the submission is graded,
 * with nothing for the client to influence.
 *
 * Not seeded via mt_srand()/Collection::shuffle(): Laravel's shuffle() uses
 * PHP 8.2's Random\Randomizer under the hood, which ignores mt_srand()
 * entirely. Sorting by a per-question hash achieves the same "looks
 * random, is actually deterministic" result without depending on any
 * global RNG state.
 */
class QuizService
{
    /**
     * The exact set of questions (with options loaded) this attempt must
     * answer -- either every question in the bank, or a deterministic
     * random subset of size pool_size.
     */
    public function questionsForAttempt(Activity $activity, Enrollment $enrollment): Collection
    {
        $all = $activity->quizQuestions()->with('options')->orderBy('sort_order')->get();

        if (! $activity->pool_size || $activity->pool_size >= $all->count()) {
            return $all;
        }

        $attemptNumber = QuizAttempt::where('activity_id', $activity->id)->where('enrollment_id', $enrollment->id)->count() + 1;
        $seed = $enrollment->id.':'.$activity->id.':'.$attemptNumber;

        return $all
            ->sortBy(fn (QuizQuestion $question) => crc32($seed.':'.$question->id))
            ->take($activity->pool_size)
            ->values();
    }
}
