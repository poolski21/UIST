<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
