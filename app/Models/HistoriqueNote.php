<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoriqueNote extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'note_id', 'ancienne_valeur', 'nouvelle_valeur', 'user_id', 'modifie_le', 'motif',
    ];

    protected function casts(): array
    {
        return [
            'ancienne_valeur' => 'array',
            'nouvelle_valeur' => 'array',
            'modifie_le' => 'datetime',
        ];
    }

    public function note()
    {
        return $this->belongsTo(Note::class);
    }

    public function utilisateur()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
