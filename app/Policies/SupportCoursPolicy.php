<?php

namespace App\Policies;

use App\Models\SupportCours;
use App\Models\User;

class SupportCoursPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['teacher', 'student'], true);
    }

    public function view(User $user, SupportCours $supportCours): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'teacher') {
            return (int) $supportCours->enseignant_id === (int) $user->getKey();
        }

        return $user->role === 'student'
            && $user->niveau_id !== null
            && $user->filiere_id !== null
            && $supportCours->niveaux()
                ->whereKey($user->niveau_id)
                ->where('filiere_id', $user->filiere_id)
                ->exists();
    }

    public function create(User $user): bool
    {
        return $user->role === 'teacher';
    }
}
