<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategorieCours extends Model
{
    protected $table = 'categories_cours';

    protected $fillable = ['nom', 'slug'];

    public function cours()
    {
        return $this->hasMany(Cours::class, 'categorie_cours_id');
    }
}
