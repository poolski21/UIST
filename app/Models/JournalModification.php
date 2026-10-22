<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalModification extends Model
{
    protected $table = 'journal_modifications';

    protected $fillable = [
        'modifie_par', 'element_type', 'element_id', 'anciennes_valeurs',
        'nouvelles_valeurs', 'motif',
    ];

    protected function casts(): array
    {
        return ['anciennes_valeurs' => 'array', 'nouvelles_valeurs' => 'array'];
    }
}
