<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmploiDuTemps extends Model
{
    protected $table = 'emplois_du_temps';

    protected $fillable = [
        'titre', 'description', 'filiere_id', 'niveau_id', 'matiere_id', 'enseignant_id',
        'date_cours', 'heure_debut', 'heure_fin', 'salle', 'statut', 'publie_par', 'publie_le',
    ];

    protected function casts(): array
    {
        return ['date_cours' => 'date', 'publie_le' => 'datetime'];
    }

    public function filiere(): BelongsTo
    {
        return $this->belongsTo(Filiere::class);
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }
}
