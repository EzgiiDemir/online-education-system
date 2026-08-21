<?php

namespace App\Notifications;

use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CertificateExpiringSoon extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Certificate $certificate, public int $daysRemaining) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $course = $this->certificate->enrollment->course;

        return (new MailMessage)
            ->subject('Sertifikanızın süresi doluyor: '.$course->title)
            ->greeting('Merhaba '.$notifiable->name.',')
            ->line('"'.$course->title.'" eğitimi için aldığınız sertifikanın geçerlilik süresi '.$this->daysRemaining.' gün içinde doluyor.')
            ->line('Geçerliliğini korumak için eğitimi yeniden tamamlamanız (recertification) gerekiyor.')
            ->action('Eğitime git', route('courses.show', $course));
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => '"'.$this->certificate->enrollment->course->title.'" sertifikanızın süresi '.$this->daysRemaining.' gün içinde doluyor.',
            'certificate_id' => $this->certificate->id,
        ];
    }
}
