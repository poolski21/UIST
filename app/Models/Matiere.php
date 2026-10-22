<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Matiere extends Model
{
    protected $fillable = ['filiere_id', 'niveau_id', 'enseignant_id', 'nom', 'code', 'credits', 'coefficient'];

    public function filiere()
    {
        return $this->belongsTo(Filiere::class);
    }

    public function niveau()
    {
        return $this->belongsTo(Niveau::class);
    }

    public function enseignant()
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }
}
