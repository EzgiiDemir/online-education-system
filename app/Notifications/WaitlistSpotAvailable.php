<?php

namespace App\Notifications;

use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WaitlistSpotAvailable extends Notification implements ShouldQueue
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
            ->subject('Bekleme listesinden kayıt: '.$course->title)
            ->greeting('Merhaba '.$notifiable->name.',')
            ->line('"'.$course->title.'" eğitiminde bir kontenjan açıldı ve bekleme listesinden kaydınız oluşturuldu.')
            ->action('Eğitime git', route('courses.show', $course));
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => '"'.$this->enrollment->course->title.'" eğitiminde bekleme listesinden kaydınız oluşturuldu.',
            'enrollment_id' => $this->enrollment->id,
            'course_id' => $this->enrollment->course_id,
        ];
    }
}
