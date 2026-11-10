<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SupportCours extends Model
{
    protected $table = 'support_cours';

    protected $fillable = [
        'enseignant_id',
        'titre',
        'description',
        'chemin_fichier',
        'nom_fichier',
        'type_mime',
    ];

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }

    public function niveaux(): BelongsToMany
    {
        return $this->belongsToMany(Niveau::class, 'support_cours_niveaux');
    }
}
