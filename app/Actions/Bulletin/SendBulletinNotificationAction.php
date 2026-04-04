<?php

namespace App\Actions\Bulletin;

use App\Models\Bulletin;
use App\Notifications\BulletinReadyNotification;
use App\Notifications\GradesSubmittedNotification;
use App\Models\User;

class SendBulletinNotificationAction
{
    public function notifyPedagogie(Bulletin $bulletin): void
    {
        User::role('pedagogie')->each(
            fn($u) => $u->notify(new GradesSubmittedNotification($bulletin))
        );
    }

    public function notifyParent(Bulletin $bulletin): void
    {
        // Adapter selon le modèle parent de l'école
        $bulletin->student->notify(new BulletinReadyNotification($bulletin));
    }
}
