<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cours extends Model
{
    protected $table = 'cours';

    protected $fillable = ['categorie_cours_id', 'titre', 'image', 'description', 'actif'];

    public function categorie()
    {
        return $this->belongsTo(CategorieCours::class, 'categorie_cours_id');
    }
}
