<?php

namespace App\Notifications;

use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrainingDueSoon extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Enrollment $enrollment, public int $daysRemaining) {}

    public function via($notifiable): array
    {
        return $notifiable->wantsNotification('training_due_soon') ? ['mail', 'database'] : [];
    }

    public function toMail($notifiable): MailMessage
    {
        $course = $this->enrollment->course;

        return (new MailMessage)
            ->subject('Eğitim son tarihi yaklaşıyor: '.$course->title)
            ->greeting('Merhaba '.$notifiable->name.',')
            ->line('"'.$course->title.'" eğitimini tamamlamanız için '.$this->daysRemaining.' gününüz kaldı.')
            ->action('Eğitime devam et', route('courses.show', $course))
            ->line('Süresi içinde tamamlanmayan zorunlu eğitimler geciken olarak işaretlenir.');
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => '"'.$this->enrollment->course->title.'" eğitiminin son tarihine '.$this->daysRemaining.' gün kaldı.',
            'enrollment_id' => $this->enrollment->id,
            'course_id' => $this->enrollment->course_id,
        ];
    }
}
