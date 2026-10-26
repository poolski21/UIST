<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CorrectPublishedNoteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'notes_td' => ['required', 'array', 'min:1', 'max:20'],
            'notes_td.*' => ['required', 'numeric', 'between:0,20'],
            'note_examen' => ['required', 'numeric', 'between:0,20'],
            'motif' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
