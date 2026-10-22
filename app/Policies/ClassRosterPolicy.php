<?php

namespace App\Policies;

use App\Models\User;

class ClassRosterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function download(User $user): bool
    {
        return $user->role === 'admin';
    }
}
