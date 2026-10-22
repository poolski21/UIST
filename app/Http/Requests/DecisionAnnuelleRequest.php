<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecisionAnnuelleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'decision_finale' => ['required', Rule::in(['admis', 'ajourne'])],
            'motif' => ['required', 'string', 'max:5000'],
        ];
    }
}
