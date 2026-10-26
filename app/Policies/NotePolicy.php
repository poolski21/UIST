<?php

namespace App\Policies;

use App\Models\Matiere;
use App\Models\Note;
use App\Models\User;

class NotePolicy
{
    public function view(User $user, Note $note): bool
    {
        return $user->role === 'admin'
            || ($user->role === 'student' && (int) $note->etudiant_id === (int) $user->getKey() && $note->statut === 'publie')
            || ($user->role === 'teacher' && (int) $note->matiere?->enseignant_id === (int) $user->getKey());
    }

    public function create(User $user, Matiere $matiere): bool
    {
        return $user->role === 'admin'
            || ($user->role === 'teacher' && (int) $matiere->enseignant_id === (int) $user->getKey());
    }

    public function publish(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function correctPublished(User $user, Note $note): bool
    {
        return $user->role === 'admin' && $note->statut === 'publie';
    }
}
