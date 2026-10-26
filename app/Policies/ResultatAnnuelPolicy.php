<?php

namespace App\Policies;

use App\Models\ResultatAnnuel;
use App\Models\User;

class ResultatAnnuelPolicy
{
    public function view(User $user, ResultatAnnuel $resultat): bool
    {
        return $user->role === 'admin'
            || ($user->role === 'student'
                && (int) $resultat->etudiant_id === (int) $user->getKey()
                && $resultat->publie_le !== null);
    }

    public function update(User $user): bool
    {
        return $user->role === 'admin';
    }
}
