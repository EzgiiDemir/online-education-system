<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseRun;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Notifications\LiveSessionScheduled;
use Illuminate\Support\Facades\DB;

/**
 * The one place an Enrollment gets created, whether the learner enrolled
 * themselves (LmsController::enroll) or an admin assigned the course to
 * them (Filament's course roster "Toplu ata" action) -- both need the same
 * capacity/waitlist/recertification handling, so it lives here once
 * instead of drifting apart across two call sites.
 */
class EnrollmentService
{
    public function enroll(Course $course, User $user, bool $checkPrerequisites = true): Enrollment|WaitlistEntry
    {
        if ($checkPrerequisites) {
            $unmet = $course->unmetPrerequisitesFor($user);
            abort_if($unmet->isNotEmpty(), 422, 'Bu eğitime kaydolmadan önce şu eğitim(ler)i tamamlamanız gerekiyor: '.$unmet->pluck('title')->implode(', ').'.');
        }

        // A stale current cycle (completed, certificate expired) doesn't
        // block a new one -- that's the whole point of recertification.
        // Anything else (still in progress, or completed with nothing to
        // expire) is the same "already enrolled, no-op" behavior as before.
        $current = $user->currentEnrollmentFor($course->id);
        if ($current && ! $current->isStale()) {
            return $current;
        }

        $openRun = $course->runs()->whereIn('status', ['open', 'running'])->orderBy('starts_at')->first();

        // A capacity-limited run needs the "is there a seat" check and the
        // enrollment insert to happen as one atomic unit -- otherwise two
        // concurrent requests can both read "1 seat left" and both insert,
        // overselling the run. lockForUpdate() on the run row (mirrors
        // CompletionEngine's heartbeat/attendance locking) serializes them.
        if ($openRun && $openRun->max_seats !== null) {
            return DB::transaction(function () use ($course, $openRun, $user) {
                $run = CourseRun::whereKey($openRun->id)->lockForUpdate()->first();
                if (! $run->hasOpenSeats()) {
                    return WaitlistEntry::firstOrCreate(['course_run_id' => $run->id, 'user_id' => $user->id]);
                }

                return $this->createEnrollmentAndNotify($course, $run->id, $user);
            });
        }

        return $this->createEnrollmentAndNotify($course, $openRun?->id, $user);
    }

    private function createEnrollmentAndNotify(Course $course, ?int $runId, User $user): Enrollment
    {
        // Plain create(), not firstOrCreate(): the caller has already
        // established (via currentEnrollmentFor()/isStale()) that a fresh
        // cycle is what's needed here, so every call always inserts a new
        // row and always sends the live-session invites for it.
        $enrollment = $user->enrollments()->create(['course_id' => $course->id, 'course_run_id' => $runId]);
        foreach ($course->sessions()->where('meeting_status', '!=', 'not_applicable')->get() as $session) {
            $user->notify(new LiveSessionScheduled($session));
        }

        return $enrollment;
    }
}
