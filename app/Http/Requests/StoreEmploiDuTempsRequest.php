<?php

namespace App\Http\Requests;

use App\Models\EmploiDuTemps;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidationValidator;

class StoreEmploiDuTempsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'filiere_id' => ['required', 'integer', 'exists:filieres,id'],
            'niveau_id' => [
                'required',
                'integer',
                Rule::exists('niveaux', 'id')->where('filiere_id', $this->input('filiere_id')),
            ],
            'week_start' => [
                'bail',
                'required',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (Carbon::parse($value)->dayOfWeekIso !== Carbon::MONDAY) {
                        $fail('La date de début de semaine doit être un lundi.');
                    }
                },
            ],
            'jour' => ['required', 'integer', 'between:1,6'],
            'matiere_id' => [
                'required',
                'integer',
                Rule::exists('matieres', 'id')
                    ->where('filiere_id', $this->input('filiere_id'))
                    ->where('niveau_id', $this->input('niveau_id')),
            ],
            'enseignant_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', 'teacher'),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'heure_debut' => ['required', 'date_format:H:i', 'after_or_equal:08:00'],
            'heure_fin' => ['required', 'date_format:H:i', 'before_or_equal:16:30', 'after:heure_debut'],
            'salle' => ['nullable', 'string', 'max:100'],
            'publier' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'filiere_id.required' => 'Veuillez choisir une filière.',
            'filiere_id.exists' => 'La filière sélectionnée est invalide.',
            'niveau_id.required' => 'Veuillez choisir un niveau.',
            'niveau_id.exists' => 'Ce niveau ne correspond pas à la filière sélectionnée.',
            'week_start.required' => 'Veuillez choisir la semaine.',
            'week_start.date_format' => 'La semaine doit être indiquée au format AAAA-MM-JJ.',
            'jour.required' => 'Veuillez choisir un jour.',
            'jour.between' => 'Le jour doit être compris entre lundi et samedi.',
            'matiere_id.required' => 'Veuillez choisir une matière.',
            'matiere_id.exists' => 'La matière ne correspond pas à la filière et au niveau sélectionnés.',
            'enseignant_id.exists' => 'L’enseignant sélectionné est invalide.',
            'heure_debut.required' => 'Veuillez renseigner l’heure de début.',
            'heure_debut.date_format' => 'L’heure de début doit respecter le format HH:MM.',
            'heure_debut.after_or_equal' => 'Une séance ne peut pas commencer avant 08:00.',
            'heure_fin.required' => 'Veuillez renseigner l’heure de fin.',
            'heure_fin.date_format' => 'L’heure de fin doit respecter le format HH:MM.',
            'heure_fin.before_or_equal' => 'Une séance ne peut pas se terminer après 16:30.',
            'heure_fin.after' => 'L’heure de fin doit être strictement après l’heure de début.',
        ];
    }

    public function withValidator(ValidationValidator $validator): void
    {
        $validator->after(function (ValidationValidator $validator): void {
            $fields = [
                'filiere_id',
                'niveau_id',
                'week_start',
                'jour',
                'heure_debut',
                'heure_fin',
            ];

            foreach ($fields as $field) {
                if ($validator->errors()->has($field)) {
                    return;
                }
            }

            if ($validator->errors()->has('matiere_id')) {
                return;
            }

            $date = Carbon::createFromFormat('!Y-m-d', $this->input('week_start'))
                ->addDays((int) $this->input('jour') - 1)
                ->toDateString();
            $startTime = $this->input('heure_debut').':00';
            $endTime = $this->input('heure_fin').':00';

            $overlaps = EmploiDuTemps::query()
                ->whereDate('date_cours', $date)
                ->whereTime('heure_debut', '<', $endTime)
                ->whereTime('heure_fin', '>', $startTime);

            $emploi = $this->route('emploiDuTemps');
            $emploiId = $emploi instanceof EmploiDuTemps
                ? $emploi->getKey()
                : (is_numeric($emploi) ? $emploi : null);

            if ($emploiId !== null) {
                $overlaps->whereKeyNot($emploiId);
            }

            $sameClass = (clone $overlaps)
                ->where('filiere_id', $this->input('filiere_id'))
                ->where('niveau_id', $this->input('niveau_id'))
                ->exists();

            if ($sameClass) {
                $validator->errors()->add('jour', 'Une autre séance de cette classe chevauche déjà cet horaire.');
            }

            if ($this->filled('enseignant_id')
                && (clone $overlaps)->where('enseignant_id', $this->input('enseignant_id'))->exists()) {
                $validator->errors()->add('enseignant_id', 'Cet enseignant a déjà une séance qui chevauche cet horaire.');
            }

            if ($this->filled('salle')
                && (clone $overlaps)->where('salle', $this->input('salle'))->exists()) {
                $validator->errors()->add('salle', 'Cette salle est déjà occupée pendant cet horaire.');
            }
        });
    }
}
