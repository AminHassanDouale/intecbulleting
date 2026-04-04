<?php

namespace App\Traits;

use App\Enums\BulletinStatusEnum;
use App\Models\User;
use App\Models\WorkflowApproval;
use App\Notifications\BulletinRejectedNotification;
use App\Notifications\WorkflowApprovedNotification;

trait HasWorkflow
{
    public function advanceWorkflow(int $userId, ?string $comment = null): bool
    {
        $next = $this->status->nextStep();
        if (! $next) {
            return false;
        }

        WorkflowApproval::create([
            'bulletin_id' => $this->id,
            'step'        => $this->status->value,
            'action'      => 'approved',
            'user_id'     => $userId,
            'comment'     => $comment,
        ]);

        $this->update(['status' => $next]);
        $this->notifyNextApprover($next);

        return true;
    }

    public function rejectWorkflow(int $userId, string $reason): bool
    {
        WorkflowApproval::create([
            'bulletin_id' => $this->id,
            'step'        => $this->status->value,
            'action'      => 'rejected',
            'user_id'     => $userId,
            'comment'     => $reason,
        ]);

        $this->update(['status' => BulletinStatusEnum::REJECTED]);

        $this->student->classroom->teacher?->notify(
            new BulletinRejectedNotification($this, $reason)
        );

        return true;
    }

    protected function notifyNextApprover(BulletinStatusEnum $status): void
    {
        $role = $status->requiredRole();
        if (! $role) {
            return;
        }

        User::role($role)->each(function ($user) use ($status) {
            $user->notify(new WorkflowApprovedNotification($this, $status));
        });
    }
}
