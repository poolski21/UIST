<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResultatAnnuel extends Model
{
    protected $table = 'resultats_annuels';

    protected $fillable = [
        'etudiant_id', 'annee_academique_id', 'moyenne_generale',
        'credits_obtenus', 'decision_auto', 'decision_finale', 'modifiee_par',
        'motif', 'publie_le',
    ];

    protected function casts(): array
    {
        return ['moyenne_generale' => 'decimal:2', 'publie_le' => 'datetime'];
    }

    public function etudiant()
    {
        return $this->belongsTo(User::class, 'etudiant_id');
    }

    public function anneeAcademique()
    {
        return $this->belongsTo(AnneeAcademique::class, 'annee_academique_id');
    }
}
