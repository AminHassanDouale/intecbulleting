<?php

namespace App\Policies;

use App\Enums\BulletinStatusEnum;
use App\Models\Bulletin;
use App\Models\User;

class BulletinPolicy
{
    public function view(User $user, Bulletin $bulletin): bool
    {
        return $user->hasAnyRole(['admin', 'direction', 'pedagogie', 'finance'])
            || $bulletin->classroom->teacher_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'teacher']);
    }

    public function update(User $user, Bulletin $bulletin): bool
    {
        if (! $bulletin->isEditable()) {
            return false;
        }

        return $user->hasRole('admin')
            || $bulletin->classroom->teacher_id === $user->id;
    }

    public function approve(User $user, Bulletin $bulletin): bool
    {
        $requiredRole = $bulletin->status->requiredRole();
        if (! $requiredRole) {
            return false;
        }

        return $user->hasAnyRole(['admin', $requiredRole]);
    }

    public function generatePdf(User $user, Bulletin $bulletin): bool
    {
        return in_array($bulletin->status, [BulletinStatusEnum::APPROVED, BulletinStatusEnum::PUBLISHED])
            && $user->hasAnyRole(['admin', 'direction']);
    }

    public function delete(User $user, Bulletin $bulletin): bool
    {
        return $user->hasRole('admin');
    }
}
