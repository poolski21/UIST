<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnneeAcademique extends Model
{
    protected $table = 'annees_academiques';

    protected $fillable = ['libelle', 'date_debut', 'date_fin', 'active'];

    protected function casts(): array
    {
        return ['date_debut' => 'date', 'date_fin' => 'date', 'active' => 'boolean'];
    }

    public function inscriptions()
    {
        return $this->hasMany(Inscription::class, 'annee_academique_id');
    }
}
