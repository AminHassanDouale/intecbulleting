<?php

namespace App\Actions\Bulletin;

use App\Models\Bulletin;
use App\Models\User;

class ApproveBulletinAction
{
    public function execute(Bulletin $bulletin, User $approver, ?string $comment = null): bool
    {
        if (! $bulletin->status->requiredRole()) {
            return false;
        }

        if (! $approver->hasAnyRole(['admin', $bulletin->status->requiredRole()])) {
            return false;
        }

        return $bulletin->advanceWorkflow($approver->id, $comment);
    }
}
