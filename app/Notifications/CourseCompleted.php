<?php

namespace App\Notifications;

use App\Models\Certificate;
use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when CompletionEngine marks an enrollment completed.
 *
 * Completion already dispatched the enrollment.completed and
 * certificate.issued webhooks, so every integrated system learned about it
 * immediately -- but nobody told the learner, who had to happen to log in
 * and notice. Transactional like TrainingAssigned/LiveSessionScheduled
 * (something they earned, not a digest), so it is not opt-out.
 */
class CourseCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Enrollment $enrollment, public ?Certificate $certificate = null) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $title = $this->enrollment->course->title;

        $mail = (new MailMessage)
            ->subject('Eğitimi tamamladınız: '.$title)
            ->greeting('Merhaba '.$notifiable->name.',')
            ->line('"'.$title.'" eğitimini başarıyla tamamladınız.');

        if ($this->certificate) {
            return $mail
                ->line('Katılım belgeniz hazır. Belge numarası: '.$this->certificate->certificate_number)
                ->action('Katılım belgesini indir', route('certificates.download', $this->certificate))
                ->line('Belgenizin doğruluğunu '.route('certificates.verify', $this->certificate->certificate_number).' adresinden kontrol edebilirsiniz.');
        }

        return $mail->action('Eğitimlerimi görüntüle', route('learning.index'));
    }

    public function toArray($notifiable): array
    {
        return array_filter([
            'message' => $this->certificate
                ? 'Eğitimi tamamladınız, katılım belgeniz hazır: '.$this->enrollment->course->title
                : 'Eğitimi tamamladınız: '.$this->enrollment->course->title,
            'course_id' => $this->enrollment->course_id,
            'enrollment_id' => $this->enrollment->id,
            'certificate_id' => $this->certificate?->id,
            'certificate_number' => $this->certificate?->certificate_number,
        ], fn ($value) => $value !== null);
    }
}
