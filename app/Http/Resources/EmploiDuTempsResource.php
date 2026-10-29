<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmploiDuTempsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'matiere_id' => $this->matiere_id,
            'matiere' => $this->matiere?->nom ?? $this->titre,
            'subject_color_index' => $this->subject_color_index,
            'description' => $this->description,
            'date' => $this->date_cours?->toDateString(),
            'heure_debut' => $this->heure_debut,
            'heure_fin' => $this->heure_fin,
            'salle' => $this->salle,
            'enseignant' => $this->enseignant?->name
                ?? $this->matiere?->enseignant?->name
                ?? 'Enseignant à confirmer',
            'filiere' => $this->filiere?->nom,
            'niveau' => $this->niveau?->nom,
        ];
    }
}
