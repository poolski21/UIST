<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Matiere extends Model
{
    protected $fillable = ['filiere_id', 'niveau_id', 'enseignant_id', 'nom', 'code', 'credits', 'coefficient'];

    /**
     * @return array<int, int>
     */
    public static function colorIndexesById(): array
    {
        return static::query()
            ->orderBy('id')
            ->pluck('id')
            ->values()
            ->mapWithKeys(fn (int $id, int $position): array => [$id => $position % 12])
            ->all();
    }

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
