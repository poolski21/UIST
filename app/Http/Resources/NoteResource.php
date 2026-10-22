<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'note' => (float) $this->note,
            'notes_td' => $this->notes_td,
            'note_examen' => $this->note_examen !== null ? (float) $this->note_examen : null,
            'validee' => (float) $this->note >= 10,
            'matiere' => $this->matiere?->nom,
            'credits' => $this->matiere?->credits,
            'annee' => $this->anneeAcademique?->libelle,
            'publiee_le' => $this->publie_le?->toIso8601String(),
        ];
    }
}
