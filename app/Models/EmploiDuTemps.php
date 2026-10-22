<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmploiDuTemps extends Model
{
    protected $table = 'emplois_du_temps';

    protected $fillable = [
        'titre', 'description', 'filiere_id', 'niveau_id', 'date_cours',
        'heure_debut', 'heure_fin', 'salle', 'statut', 'publie_par', 'publie_le',
    ];

    protected function casts(): array
    {
        return ['date_cours' => 'date', 'publie_le' => 'datetime'];
    }

    public function filiere()
    {
        return $this->belongsTo(Filiere::class);
    }

    public function niveau()
    {
        return $this->belongsTo(Niveau::class);
    }
}
