<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Notifications\CertificateExpiringSoon;
use App\Notifications\TrainingDueSoon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Small rule engine (§27), run once a day by the scheduler:
 *   WHEN a course's due date is exactly 7 days away AND still incomplete -> remind the learner
 *   WHEN a certificate expires in exactly 30 days -> remind the learner (and their manager, if any)
 * "Exactly N days away" (rather than "N or fewer") is deliberate: since this
 * runs once daily, each enrollment/certificate crosses that threshold on
 * only one calendar day, so nobody gets the same reminder twice without
 * needing a separate "already notified" tracking table.
 */
#[Signature('app:send-training-reminders')]
#[Description('Sends due-date and certificate-expiry reminder notifications')]
class SendTrainingReminders extends Command
{
    private const DUE_SOON_DAYS = 7;

    private const EXPIRY_SOON_DAYS = 30;

    public function handle(): void
    {
        $dueSoonCount = $this->sendDueSoonReminders();
        $expiringSoonCount = $this->sendExpiringSoonReminders();

        $this->info("Gönderilen hatırlatmalar: {$dueSoonCount} son tarih, {$expiringSoonCount} sertifika süresi.");
    }

    private function sendDueSoonReminders(): int
    {
        $sent = 0;
        $target = today()->addDays(self::DUE_SOON_DAYS);

        Enrollment::where('status', '!=', 'completed')
            ->whereNull('exempted_at')
            ->whereHas('course', fn ($q) => $q->whereNotNull('due_days'))
            ->with(['course', 'user'])
            ->each(function (Enrollment $enrollment) use (&$sent, $target) {
                $dueDate = $enrollment->course->dueDateFor($enrollment);
                if ($dueDate && $dueDate->isSameDay($target)) {
                    $enrollment->user->notify(new TrainingDueSoon($enrollment, self::DUE_SOON_DAYS));
                    $sent++;
                }
            });

        return $sent;
    }

    private function sendExpiringSoonReminders(): int
    {
        $sent = 0;
        $target = today()->addDays(self::EXPIRY_SOON_DAYS);

        Certificate::whereHas('enrollment.course', fn ($q) => $q->whereNotNull('recertification_period_days'))
            ->with(['enrollment.course', 'enrollment.user.manager'])
            ->each(function (Certificate $certificate) use (&$sent, $target) {
                $expiresAt = $certificate->expiresAt();
                if ($expiresAt && $expiresAt->isSameDay($target)) {
                    $learner = $certificate->enrollment->user;
                    $learner->notify(new CertificateExpiringSoon($certificate, self::EXPIRY_SOON_DAYS));
                    $learner->manager?->notify(new CertificateExpiringSoon($certificate, self::EXPIRY_SOON_DAYS));
                    $sent++;
                }
            });

        return $sent;
    }
}
