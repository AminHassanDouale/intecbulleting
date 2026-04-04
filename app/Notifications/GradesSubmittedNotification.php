<?php

namespace App\Notifications;

use App\Models\Bulletin;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GradesSubmittedNotification extends Notification
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
            ->subject('✅ Bulletin soumis — En attente de votre validation')
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line('Un bulletin a été soumis par l\'enseignant(e) **' . ($this->bulletin->submittedBy?->name ?? 'N/A') . '**.')
            ->line('Élève : **' . $this->bulletin->student->full_name . '** — Classe : ' . $this->bulletin->classroom->label)
            ->action('Vérifier le bulletin', route('bulletins.workflow', $this->bulletin->id))
            ->line('Merci de procéder à votre validation.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'grades_submitted',
            'bulletin_id' => $this->bulletin->id,
        ];
    }
}
