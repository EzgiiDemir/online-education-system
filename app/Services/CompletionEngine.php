<?php

namespace App\Services;

use App\Models\ActivityProgress;
use App\Models\Certificate;
use App\Models\CourseSession;
use App\Models\Enrollment;
use App\Models\MaterialProgress;
use App\Models\SessionAttendance;
use Illuminate\Support\Str;

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
     */
    public function accrueHeartbeat(MaterialProgress|ActivityProgress $progress): void
    {
        if ($progress->completed) {
            return;
        }
        if ($progress->last_heartbeat_at && $progress->last_heartbeat_at->diffInSeconds(now()) < 4) {
            return;
        }
        $progress->increment('watched_seconds', 5);
        $progress->update(['last_heartbeat_at' => now()]);
    }

    /**
     * Same anti-cheat posture as accrueHeartbeat(), for live-session
     * presence: class_presence pings call this every ~5s while the learner
     * has camera/mic actually open in the room.
     */
    public function accrueSessionAttendance(CourseSession $session, int $userId): void
    {
        $attendance = SessionAttendance::firstOrCreate(
            ['course_session_id' => $session->id, 'user_id' => $userId],
            ['joined_at' => now(), 'duration_seconds' => 0]
        );
        if ($attendance->left_at && $attendance->left_at->diffInSeconds(now()) < 4) {
            return;
        }
        $attendance->increment('duration_seconds', 5);
        $attendance->update(['left_at' => now()]);
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
     * event since it's idempotent (firstOrCreate on the certificate, no-op
     * if the enrollment is already below 100%).
     */
    public function evaluateEnrollment(Enrollment $enrollment): void
    {
        $stats = $enrollment->completionStats();
        if ($stats['total'] && $stats['completed'] >= $stats['total']) {
            $enrollment->update(['status' => 'completed', 'completed_at' => now()]);
            Certificate::firstOrCreate(['enrollment_id' => $enrollment->id], [
                'certificate_number' => 'CERT-'.strtoupper(Str::random(10)),
                'issued_at' => now(),
            ]);
        }
    }
}
