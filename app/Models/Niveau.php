<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Niveau extends Model
{
    protected $fillable = ['filiere_id', 'nom', 'code', 'credits_requis'];

    public function filiere()
    {
        return $this->belongsTo(Filiere::class);
    }

    public function matieres()
    {
        return $this->hasMany(Matiere::class);
    }

    public function supportsCours(): BelongsToMany
    {
        return $this->belongsToMany(SupportCours::class, 'support_cours_niveaux');
    }
}
