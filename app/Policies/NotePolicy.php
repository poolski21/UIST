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
            || ($user->role === 'student' && $note->etudiant_id === $user->id && $note->statut === 'publie')
            || ($user->role === 'teacher' && $note->matiere?->enseignant_id === $user->id);
    }

    public function create(User $user, Matiere $matiere): bool
    {
        return $user->role === 'admin'
            || ($user->role === 'teacher' && $matiere->enseignant_id === $user->id);
    }

    public function publish(User $user): bool
    {
        return $user->role === 'admin';
    }
}
