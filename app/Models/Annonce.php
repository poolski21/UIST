<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Annonce extends Model
{
    protected $fillable = [
        'titre', 'contenu', 'piece_jointe', 'cible_type', 'cible_id',
        'cree_par', 'publiee_le',
    ];

    protected function casts(): array
    {
        return ['publiee_le' => 'datetime'];
    }

    public function createur()
    {
        return $this->belongsTo(User::class, 'cree_par');
    }
}
