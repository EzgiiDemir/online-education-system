<?php

namespace App\Notifications;

use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when an admin assigns a course directly (Course roster's "Toplu
 * ata" action) rather than the learner enrolling themselves -- they need
 * to be told this happened at all, since nothing else in the UI would
 * otherwise surface a training they didn't sign up for.
 */
class TrainingAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Enrollment $enrollment) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $course = $this->enrollment->course;

        return (new MailMessage)
            ->subject('Yeni eğitim atandı: '.$course->title)
            ->greeting('Merhaba '.$notifiable->name.',')
            ->line('"'.$course->title.'" eğitimine kaydınız oluşturuldu.')
            ->when($course->due_days, fn ($mail) => $mail->line('Bu eğitimi '.$course->due_days.' gün içinde tamamlamanız gerekiyor.'))
            ->action('Eğitime git', route('courses.show', $course));
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => '"'.$this->enrollment->course->title.'" eğitimine atandınız.',
            'enrollment_id' => $this->enrollment->id,
            'course_id' => $this->enrollment->course_id,
        ];
    }
}
