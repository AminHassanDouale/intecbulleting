<?php

namespace App\Notifications;

use App\Models\Bulletin;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BulletinRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Bulletin $bulletin,
        public string $reason
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('❌ Bulletin rejeté — ' . $this->bulletin->student->full_name)
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line('Le bulletin de **' . $this->bulletin->student->full_name . '** a été **rejeté**.')
            ->line('Motif : ' . $this->reason)
            ->action('Corriger le bulletin', route('bulletins.grade-form'))
            ->line('Veuillez corriger et resoumettre le bulletin.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'bulletin_rejected',
            'bulletin_id' => $this->bulletin->id,
            'reason'      => $this->reason,
        ];
    }
}
