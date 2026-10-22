<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Filiere extends Model
{
    protected $fillable = ['nom', 'code', 'description'];

    public function niveaux()
    {
        return $this->hasMany(Niveau::class);
    }

    public function matieres()
    {
        return $this->hasMany(Matiere::class);
    }
}
