<?php

namespace App\Notifications;

use App\Models\Bulletin;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BulletinReadyNotification extends Notification
{
    use Queueable;

    public function __construct(public Bulletin $bulletin) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('📋 Bulletin disponible — ' . $this->bulletin->student->full_name)
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line('Le bulletin de **' . $this->bulletin->student->full_name . '**')
            ->line('pour le **' . $this->bulletin->period . '** est maintenant disponible.')
            ->action('Télécharger le bulletin', $this->bulletin->getPdfUrl() ?? '#')
            ->line('INTEC École — Système de gestion des bulletins');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'bulletin_ready',
            'bulletin_id' => $this->bulletin->id,
            'student'     => $this->bulletin->student->full_name,
            'period'      => $this->bulletin->period,
        ];
    }
}
