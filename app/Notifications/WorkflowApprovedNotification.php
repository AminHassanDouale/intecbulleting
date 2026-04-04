<?php

namespace App\Notifications;

use App\Enums\BulletinStatusEnum;
use App\Models\Bulletin;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkflowApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Bulletin $bulletin,
        public BulletinStatusEnum $status
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('📝 Action requise : ' . $this->status->label())
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line('Un bulletin nécessite votre approbation.')
            ->line('Statut actuel : **' . $this->status->label() . '**')
            ->line('Élève : ' . $this->bulletin->student->full_name)
            ->action('Traiter le bulletin', route('bulletins.workflow', $this->bulletin->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'workflow_step',
            'bulletin_id' => $this->bulletin->id,
            'step'        => $this->status->value,
        ];
    }
}
