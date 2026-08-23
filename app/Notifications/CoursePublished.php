<?php

namespace App\Notifications;

use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CoursePublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Course $course) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Yeni eğitim yayınlandı: '.$this->course->title)
            ->greeting('Merhaba '.$notifiable->name.',')
            ->line('Portala yeni bir eğitim eklendi: "'.$this->course->title.'".')
            ->when($this->course->description, fn ($mail) => $mail->line($this->course->description))
            ->action('Eğitimi görüntüle', route('courses.show', $this->course));
    }

    public function toArray($notifiable): array
    {
        return ['message' => 'Yeni eğitim yayınlandı: '.$this->course->title, 'course_id' => $this->course->id];
    }
}
