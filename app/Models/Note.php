<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    protected $fillable = [
        'etudiant_id', 'matiere_id', 'annee_academique_id', 'note', 'notes_td', 'note_examen',
        'statut', 'saisie_par', 'publie_le',
    ];

    protected function casts(): array
    {
        return [
            'note' => 'decimal:2',
            'notes_td' => 'array',
            'note_examen' => 'decimal:2',
            'publie_le' => 'datetime',
        ];
    }

    public function etudiant()
    {
        return $this->belongsTo(User::class, 'etudiant_id');
    }

    public function matiere()
    {
        return $this->belongsTo(Matiere::class);
    }

    public function anneeAcademique()
    {
        return $this->belongsTo(AnneeAcademique::class, 'annee_academique_id');
    }
}
