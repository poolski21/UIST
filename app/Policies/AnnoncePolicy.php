<?php

namespace App\Policies;

use App\Models\Annonce;
use App\Models\User;

class AnnoncePolicy
{
    public function view(User $user, Annonce $annonce): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $user->role === 'student'
            && $annonce->publiee_le !== null
            && ($annonce->cible_type === 'tous'
                || ($annonce->cible_type === 'filiere' && $annonce->cible_id === $user->filiere_id)
                || ($annonce->cible_type === 'niveau' && $annonce->cible_id === $user->niveau_id));
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }
}
