<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmploiDuTempsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'filiere_id' => ['nullable', 'exists:filieres,id', 'required_with:niveau_id'],
            'niveau_id' => [
                'nullable',
                'required_with:filiere_id',
                Rule::exists('niveaux', 'id')->where('filiere_id', $this->input('filiere_id')),
            ],
            'date_cours' => ['required', 'date'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
            'salle' => ['nullable', 'string', 'max:100'],
            'publier' => ['sometimes', 'boolean'],
        ];
    }
}
