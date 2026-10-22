<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnonceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'contenu' => $this->contenu,
            'piece_jointe' => $this->piece_jointe ? asset('storage/'.$this->piece_jointe) : null,
            'publiee_le' => $this->publiee_le?->toIso8601String(),
            'cible_type' => $this->cible_type,
        ];
    }
}
