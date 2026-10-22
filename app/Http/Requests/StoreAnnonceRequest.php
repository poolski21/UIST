<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnnonceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'contenu' => ['required', 'string', 'max:50000'],
            'piece_jointe' => ['nullable', 'file', 'mimes:pdf,doc,docx,png,jpg,jpeg', 'max:10240'],
            'cible_type' => ['required', Rule::in(['tous', 'filiere', 'niveau'])],
            'cible_id' => ['nullable', 'integer', 'required_unless:cible_type,tous'],
            'publier' => ['sometimes', 'boolean'],
        ];
    }
}
