<?php

namespace App\Policies;

use App\Models\Bulletin;
use App\Models\User;

class GradePolicy
{
    public function create(User $user, Bulletin $bulletin): bool
    {
        if (! $bulletin->isEditable()) {
            return false;
        }

        return $user->hasRole('admin')
            || $bulletin->classroom->teacher_id === $user->id;
    }

    public function update(User $user, Bulletin $bulletin): bool
    {
        return $this->create($user, $bulletin);
    }
}
