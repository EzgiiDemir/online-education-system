<?php
namespace App\Notifications;
use App\Models\CourseSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
class LiveSessionScheduled extends Notification implements ShouldQueue {
    use Queueable;
    public function __construct(public CourseSession $session) {}
    public function via($notifiable): array { return ['mail','database']; }
    public function toMail($notifiable): MailMessage {
        $session=$this->session->load('course'); $start=$session->starts_at?->utc()->format('Ymd\\THis\\Z')??now()->utc()->format('Ymd\\THis\\Z'); $end=$session->ends_at?->utc()->format('Ymd\\THis\\Z')??now()->addHour()->utc()->format('Ymd\\THis\\Z'); $url=$session->meeting_url;
        $ics="BEGIN:VCALENDAR\\r\\nVERSION:2.0\\r\\nPRODID:-//Campus LMS//Live Class//EN\\r\\nBEGIN:VEVENT\\r\\nUID:session-{$session->id}@campus-lms\\r\\nDTSTAMP:".now()->utc()->format('Ymd\\THis\\Z')."\\r\\nDTSTART:{$start}\\r\\nDTEND:{$end}\\r\\nSUMMARY:".addslashes($session->course->title.' - '.$session->title)."\\r\\nDESCRIPTION:Campus LMS canlı sınıfı\\n{$url}\\r\\nURL:{$url}\\r\\nEND:VEVENT\\r\\nEND:VCALENDAR\\r\\n";
        return (new MailMessage)->subject('Canlı ders daveti: '.$session->course->title)->greeting('Merhaba '.$notifiable->name.',')->line('Kayıt olduğunuz eğitim için canlı ders planlandı.')->line('Ders: '.$session->title)->line('Tarih: '.($session->starts_at?->format('d.m.Y H:i')??'Belirlenmedi'))->action('Canlı sınıfa katıl',$url)->line('Dersi takviminize eklemek için bu e-postadaki ICS dosyasını kullanabilirsiniz.')->attachData($ics,'campus-lms-canli-ders.ics',['mime'=>'text/calendar']);
    }
    public function toArray($notifiable): array { return ['message'=>'Canlı ders planlandı: '.$this->session->title,'session_id'=>$this->session->id,'meeting_url'=>$this->session->meeting_url]; }
}
