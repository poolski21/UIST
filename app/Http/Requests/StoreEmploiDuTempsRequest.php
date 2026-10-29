<?php

namespace App\Http\Requests;

use App\Models\EmploiDuTemps;
use App\Models\Matiere;
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

            if ($validator->errors()->has('enseignant_id') || $validator->errors()->has('publier')) {
                return;
            }

            $matiere = Matiere::query()->find($this->input('matiere_id'));
            if ($matiere === null) {
                return;
            }

            $emploi = $this->route('emploiDuTemps');
            $emploi = $emploi instanceof EmploiDuTemps ? $emploi : null;
            $enseignantId = $this->input('enseignant_id') ?: $matiere->enseignant_id;
            $willBePublished = $this->boolean('publier') || $emploi?->statut === 'publie';

            if ($willBePublished && $enseignantId === null) {
                $validator->errors()->add(
                    'enseignant_id',
                    'Une séance publiée doit avoir un enseignant, directement ou via sa matière.',
                );

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

            if ($emploi !== null) {
                $overlaps->whereKeyNot($emploi->getKey());
            }

            $overlappingSessions = (clone $overlaps)
                ->with(['matiere', 'filiere', 'niveau'])
                ->get();

            $sameClassConflict = $overlappingSessions->first(fn (EmploiDuTemps $session): bool => (
                (string) $session->filiere_id === (string) $this->input('filiere_id')
                && (string) $session->niveau_id === (string) $this->input('niveau_id')
            ));

            if ($sameClassConflict !== null) {
                $validator->errors()->add('jour', 'Une autre séance de cette classe chevauche déjà cet horaire.');
            }

            $teacherConflict = $enseignantId === null
                ? null
                : $overlappingSessions->first(function (EmploiDuTemps $session) use ($enseignantId, $matiere, $date, $startTime, $endTime): bool {
                    $existingTeacherId = $session->enseignant_id ?? $session->matiere?->enseignant_id;

                    return (string) $existingTeacherId === (string) $enseignantId
                        && ! $this->isSharedCourse(
                            $session,
                            $matiere,
                            $date,
                            $startTime,
                            $endTime,
                        );
                });

            if ($teacherConflict !== null) {
                $validator->errors()->add(
                    'enseignant_id',
                    'Cet enseignant a déjà une séance en conflit : '.$this->describeSession($teacherConflict).'.',
                );
            }

            $room = trim((string) $this->input('salle'));
            $roomConflict = $room === ''
                ? null
                : $overlappingSessions->first(fn (EmploiDuTemps $session): bool => (
                    trim((string) $session->salle) === $room
                    && ! $this->isSharedCourse($session, $matiere, $date, $startTime, $endTime)
                ));

            if ($roomConflict !== null) {
                $validator->errors()->add(
                    'salle',
                    'Cette salle est déjà occupée par la séance « '.$this->describeSession($roomConflict).'».',
                );
            }
        });
    }

    private function isSharedCourse(
        EmploiDuTemps $session,
        Matiere $matiere,
        string $date,
        string $startTime,
        string $endTime,
    ): bool {
        $existingCourseName = $session->matiere?->nom ?? $session->titre;
        $effectiveTeacherId = $this->input('enseignant_id') ?: $matiere->enseignant_id;
        $existingTeacherId = $session->enseignant_id ?? $session->matiere?->enseignant_id;

        return $session->date_cours?->toDateString() === $date
            && $effectiveTeacherId !== null
            && (string) $existingTeacherId === (string) $effectiveTeacherId
            && $existingCourseName === $matiere->nom
            && substr((string) $session->heure_debut, 0, 5) === substr($startTime, 0, 5)
            && substr((string) $session->heure_fin, 0, 5) === substr($endTime, 0, 5)
            && trim((string) $session->salle) !== ''
            && trim((string) $session->salle) === trim((string) $this->input('salle'))
            && (
                (string) $session->filiere_id !== (string) $this->input('filiere_id')
                || (string) $session->niveau_id !== (string) $this->input('niveau_id')
            );
    }

    private function describeSession(EmploiDuTemps $session): string
    {
        $class = collect([$session->filiere?->nom, $session->niveau?->nom])
            ->filter()
            ->implode(' · ');

        return $session->titre.' — '.$session->date_cours?->toDateString()
            .' '.substr((string) $session->heure_debut, 0, 5)
            .'–'.substr((string) $session->heure_fin, 0, 5)
            .($class !== '' ? ' ('.$class.')' : '');
    }
}
