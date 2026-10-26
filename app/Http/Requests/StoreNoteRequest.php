<?php

namespace App\Http\Requests;

use App\Models\Matiere;
use Illuminate\Foundation\Http\FormRequest;

class StoreNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $matiere = Matiere::find($this->input('matiere_id'));

        return $this->user()?->role === 'admin'
            || ($this->user()?->role === 'teacher'
                && (int) ($matiere?->enseignant_id ?? 0) === (int) $this->user()->getKey());
    }

    public function rules(): array
    {
        return [
            'matiere_id' => ['required', 'exists:matieres,id'],
            'etudiant_id' => ['required', 'exists:users,id'],
            'annee_academique_id' => ['required', 'exists:annees_academiques,id'],
            'notes_td' => ['required', 'array', 'min:1', 'max:20'],
            'notes_td.*' => ['required', 'numeric', 'between:0,20'],
            'note_examen' => ['required', 'numeric', 'between:0,20'],
        ];
    }
}
