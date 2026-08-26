<?php

namespace App\Notifications;

use App\Models\Material;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SharedDocumentGraded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Material $material) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => '"'.$this->material->title.'" belgeniz '.$this->material->score.' puan aldı.',
            'material_id' => $this->material->id,
        ];
    }
}
