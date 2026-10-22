<?php

namespace App\Policies;

use App\Models\User;

class AdminPasswordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $user->role === 'admin' && $target->role === 'admin';
    }

    public function changePassword(User $user, User $target): bool
    {
        return $user->role === 'admin'
            && $target->role === 'admin'
            && $user->is($target);
    }
}
