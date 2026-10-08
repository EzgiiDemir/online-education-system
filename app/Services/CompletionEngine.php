<?php

namespace App\Services;

use App\Models\ActivityProgress;
use App\Models\Certificate;
use App\Models\CourseSession;
use App\Models\Enrollment;
use App\Models\MaterialProgress;
use App\Models\SessionAttendance;
use App\Notifications\CourseCompleted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Single choke point for every "is this content complete" / "is this
 * enrollment complete" decision in the app. Before this existed, the same
 * three steps -- mark a progress row complete, re-check the enrollment's
 * aggregate stats, issue a certificate if everything required is now done --
 * were duplicated across completeMaterial(), completeActivity(),
 * gradeAssignment(), submitQuizAttempt() and completeSession() in
 * LmsController, each hand-rolling its own copy. Centralizing it here means
 * every completion path (watch-time, quiz pass, assignment grade, session
 * attendance, and any future path) goes through the same evaluation and
 * certificate-issuance logic instead of each caller re-implementing it.
 *
 * This is a pure extraction of existing, already-tested behavior -- no
 * completion rule changed. See tests/Feature/WatchTimeIntegrityTest.php,
 * CompletionTransparencyTest.php and ActivityProgressTest.php for the
 * anti-cheat guarantees this must keep holding.
 */
class CompletionEngine
{
    public function __construct(private WebhookDispatcher $webhooks = new WebhookDispatcher) {}

    /**
     * A video's completion threshold is 90% of its real, auto-detected
     * duration (min 10s so a very short clip is still watchable in full);
     * a duration-less video (should not normally happen) falls back to a
     * flat 20s so it's not trivially completable with a single heartbeat.
     */
    public function requiredWatchSeconds(?int $durationSeconds): int
    {
        return $durationSeconds ? max(10, (int) ceil($durationSeconds * 0.9)) : 20;
    }

    /**
     * A live session's completion threshold is 90% of its scheduled
     * duration; sessions with no scheduled end time fall back to a flat
     * 5 minutes so presence can't be satisfied instantly.
     */
    public function requiredSessionSeconds(CourseSession $session): int
    {
        if ($session->starts_at && $session->ends_at) {
            return max(60, (int) ceil($session->starts_at->diffInSeconds($session->ends_at) * 0.9));
        }

        return 300;
    }

    /**
     * The client-reported elapsed time is never trusted for anything: every
     * accepted heartbeat call adds a fixed, small increment, and consecutive
     * accepted calls are throttled to at least 4 seconds apart. This is what
     * makes "completed" mean the tab was genuinely open and visible for the
     * required real time, not whatever number a script chooses to send.
     *
     * The read-check-then-write here has to happen inside a locked
     * transaction: two near-simultaneous requests (e.g. the same material
     * open in two browser tabs) both reading the same $progress instance
     * would otherwise both see last_heartbeat_at as "old enough" and both
     * accrue +5, doubling the effective rate the 4-second throttle is
     * supposed to enforce. lockForUpdate() makes the second request wait
     * for the first to commit, then re-read the now-fresh timestamp itself.
     */
    public function accrueHeartbeat(MaterialProgress|ActivityProgress $progress): void
    {
        $class = $progress::class;
        DB::transaction(function () use ($class, $progress) {
            $fresh = $class::whereKey($progress->getKey())->lockForUpdate()->first();
            if (! $fresh || $fresh->completed) {
                return;
            }
            if ($fresh->last_heartbeat_at && $fresh->last_heartbeat_at->diffInSeconds(now()) < 4) {
                return;
            }
            $fresh->increment('watched_seconds', 5);
            $fresh->update(['last_heartbeat_at' => now()]);
            $progress->watched_seconds = $fresh->watched_seconds;
            $progress->last_heartbeat_at = $fresh->last_heartbeat_at;
        });
    }

    /**
     * Same anti-cheat posture and the same locking rationale as
     * accrueHeartbeat(): class_presence pings call this every ~5s while the
     * learner has camera/mic actually open in the room, and a learner with
     * two tabs open to the same live class must not be able to double the
     * accrual rate. A SELECT ... FOR UPDATE can't lock a row that doesn't
     * exist yet, so the very first ping for a given user+session still has
     * a race window between "not found" and INSERT; the unique index on
     * (course_session_id, user_id) is the backstop for that -- if two
     * requests both try to create the row, the loser's insert fails and it
     * just re-reads (now locked) what the winner created instead.
     */
    public function accrueSessionAttendance(CourseSession $session, int $userId): void
    {
        DB::transaction(function () use ($session, $userId) {
            $attendance = SessionAttendance::where('course_session_id', $session->id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (! $attendance) {
                try {
                    SessionAttendance::create([
                        'course_session_id' => $session->id,
                        'user_id' => $userId,
                        'joined_at' => now(),
                        'duration_seconds' => 5,
                        'left_at' => now(),
                    ]);
                } catch (QueryException) {
                    // Lost the create race; the winner's row already carries
                    // this ping's accrual, so there's nothing left to do.
                }

                return;
            }

            if ($attendance->left_at && $attendance->left_at->diffInSeconds(now()) < 4) {
                return;
            }
            $attendance->increment('duration_seconds', 5);
            $attendance->update(['left_at' => now()]);
        });
    }

    public function completeMaterialProgress(MaterialProgress $progress, Enrollment $enrollment): void
    {
        $progress->update(['completed' => true, 'completed_at' => now()]);
        $this->evaluateEnrollment($enrollment);
    }

    public function completeActivityProgress(ActivityProgress $progress, Enrollment $enrollment): void
    {
        $progress->update(['completed' => true, 'completed_at' => now()]);
        $this->evaluateEnrollment($enrollment);
    }

    /**
     * Re-checks whether every required piece of content for this enrollment
     * is now complete (Enrollment::completionStats() -- materials + required
     * activities + sessions); if so, marks the enrollment completed and
     * issues its certificate. Safe to call after every single completion
     * event since it's idempotent -- firstOrCreate is a no-op if a
     * certificate already exists, and the try/catch below covers the
     * narrow race where two completion events for the same enrollment
     * (e.g. a quiz pass and an instructor grading a submission) land close
     * enough together that both read "no certificate yet" before either
     * commits; certificates.enrollment_id is unique, so the loser's insert
     * fails cleanly instead of producing a duplicate.
     */
    public function evaluateEnrollment(Enrollment $enrollment): void
    {
        // Already completed: re-running this must not refresh completed_at
        // to "now" (it should stay the original completion date) or
        // re-fire the webhooks below for an event that already happened.
        if ($enrollment->status === 'completed') {
            return;
        }

        $stats = $enrollment->completionStats();
        if ($stats['total'] && $stats['completed'] >= $stats['total']) {
            $enrollment->update(['status' => 'completed', 'completed_at' => now()]);
            $this->webhooks->dispatch('enrollment.completed', [
                'enrollment_id' => $enrollment->id,
                'user_id' => $enrollment->user_id,
                'course_id' => $enrollment->course_id,
                'completed_at' => $enrollment->completed_at->toIso8601String(),
            ]);

            $certificate = null;

            // "Sertifika üret" is a real setting on the course (the creation
            // wizard asks for it, the Settings tab shows it), so a course with
            // it switched off -- an information session, a non-accredited
            // briefing -- completes without minting an official participation
            // certificate. The column is NOT NULL DEFAULT true, so every
            // ordinary course is unaffected.
            if ($enrollment->course->certificate_enabled) {
                try {
                    $certificate = Certificate::create([
                        'enrollment_id' => $enrollment->id,
                        'certificate_number' => 'CERT-'.strtoupper(Str::random(10)),
                        'issued_at' => now(),
                    ]);
                    $this->webhooks->dispatch('certificate.issued', [
                        'certificate_id' => $certificate->id,
                        'certificate_number' => $certificate->certificate_number,
                        'enrollment_id' => $enrollment->id,
                        'user_id' => $enrollment->user_id,
                        'course_id' => $enrollment->course_id,
                        'issued_at' => $certificate->issued_at->toIso8601String(),
                    ]);
                } catch (QueryException) {
                    // Lost the create race; a certificate for this enrollment
                    // already exists (that's the only thing this constraint
                    // guards), so there's nothing left to do -- including no
                    // second certificate.issued dispatch. Read back the
                    // winner's row so the notification still links to it.
                    $certificate = Certificate::where('enrollment_id', $enrollment->id)->first();
                }
            }

            // Completion dispatched webhooks above, so every integrated
            // system already knows -- tell the person it actually happened
            // to as well, otherwise they only find out by logging in and
            // noticing. Guarded so a mail/queue problem can never roll back
            // a completion that genuinely happened.
            try {
                $enrollment->loadMissing('user', 'course');
                $enrollment->user?->notify(new CourseCompleted($enrollment, $certificate));
            } catch (Throwable $e) {
                Log::warning('Course completion notification failed', [
                    'enrollment_id' => $enrollment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
