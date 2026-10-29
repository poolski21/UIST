<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmploiDuTempsRequest;
use App\Models\EmploiDuTemps;
use App\Models\Filiere;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\User;
use App\Notifications\PublicationNotification;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmploiDuTempsController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(auth()->user()->role === 'admin', 403);

        $filters = $request->validate([
            'filiere_id' => ['nullable', 'integer', 'exists:filieres,id'],
            'niveau_id' => [
                'nullable',
                'integer',
                Rule::exists('niveaux', 'id')->where('filiere_id', $request->input('filiere_id')),
            ],
            'week_start' => [
                'bail',
                'nullable',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (Carbon::parse($value)->dayOfWeekIso !== Carbon::MONDAY) {
                        $fail('La semaine sélectionnée doit commencer un lundi.');
                    }
                },
            ],
        ], [
            'filiere_id.exists' => 'La filière sélectionnée est invalide.',
            'niveau_id.exists' => 'Le niveau ne correspond pas à la filière sélectionnée.',
            'week_start.date_format' => 'La semaine doit être indiquée au format AAAA-MM-JJ.',
        ]);

        $weekStart = Carbon::parse($filters['week_start'] ?? now()->startOfWeek(Carbon::MONDAY))->startOfDay();
        $hasClass = ! empty($filters['filiere_id']) && ! empty($filters['niveau_id']);
        $emplois = $hasClass
            ? EmploiDuTemps::query()
                ->with(['filiere', 'niveau', 'matiere', 'enseignant'])
                ->where('filiere_id', $filters['filiere_id'])
                ->where('niveau_id', $filters['niveau_id'])
                ->whereBetween('date_cours', [
                    $weekStart->toDateString(),
                    $weekStart->copy()->addDays(5)->toDateString(),
                ])
                ->orderBy('date_cours')
                ->orderBy('heure_debut')
                ->get()
            : collect();

        return Inertia::render('Admin/EmploisDuTemps', [
            'emplois' => $emplois,
            'filieres' => Filiere::orderBy('nom')->get(['id', 'nom']),
            'niveaux' => Niveau::orderBy('nom')->get(['id', 'nom', 'filiere_id']),
            'matieres' => $hasClass
                ? Matiere::query()
                    ->where('filiere_id', $filters['filiere_id'])
                    ->where('niveau_id', $filters['niveau_id'])
                    ->orderBy('nom')
                    ->get(['id', 'nom', 'filiere_id', 'niveau_id', 'enseignant_id'])
                : collect(),
            'enseignants' => User::query()
                ->where('role', 'teacher')
                ->orderBy('name')
                ->get(['id', 'name']),
            'conflits' => EmploiDuTemps::query()
                ->with([
                    'matiere:id,enseignant_id,nom',
                    'filiere:id,nom',
                    'niveau:id,nom',
                ])
                ->whereBetween('date_cours', [
                    $weekStart->toDateString(),
                    $weekStart->copy()->addDays(5)->toDateString(),
                ])
                ->get([
                    'id',
                    'titre',
                    'matiere_id',
                    'date_cours',
                    'heure_debut',
                    'heure_fin',
                    'filiere_id',
                    'niveau_id',
                    'enseignant_id',
                    'salle',
                ]),
            'selection' => [
                'filiere_id' => $filters['filiere_id'] ?? '',
                'niveau_id' => $filters['niveau_id'] ?? '',
                'week_start' => $weekStart->toDateString(),
            ],
        ]);
    }

    public function store(StoreEmploiDuTempsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $publier = $request->boolean('publier');
        $emploi = EmploiDuTemps::create([
            ...$this->sessionAttributes($data),
            'statut' => $publier ? 'publie' : 'brouillon',
            'publie_par' => $publier ? $request->user()->id : null,
            'publie_le' => $publier ? now() : null,
        ]);

        if ($publier) {
            $this->notifier($emploi);
        }

        return back()->with('success', $publier ? 'Emploi du temps publié.' : 'Brouillon enregistré.');
    }

    public function update(StoreEmploiDuTempsRequest $request, EmploiDuTemps $emploiDuTemps): RedirectResponse
    {
        $emploiDuTemps->update($this->sessionAttributes($request->validated()));

        return back()->with('success', 'Séance mise à jour.');
    }

    public function destroy(EmploiDuTemps $emploiDuTemps): RedirectResponse
    {
        $emploiDuTemps->delete();

        return back()->with('success', 'Séance supprimée.');
    }

    public function publish(EmploiDuTemps $emploiDuTemps): RedirectResponse
    {
        abort_unless(auth()->user()->role === 'admin', 403);
        if ($emploiDuTemps->statut !== 'publie') {
            $emploiDuTemps->loadMissing('matiere');
            if (($emploiDuTemps->enseignant_id ?? $emploiDuTemps->matiere?->enseignant_id) === null) {
                throw ValidationException::withMessages([
                    'enseignant_id' => 'La séance « '.$emploiDuTemps->titre.' » ne peut pas être publiée sans enseignant.',
                ]);
            }

            $emploiDuTemps->update([
                'statut' => 'publie',
                'publie_par' => auth()->id(),
                'publie_le' => now(),
            ]);
            $this->notifier($emploiDuTemps);
        }

        return back()->with('success', 'Emploi du temps publié.');
    }

    public function publishWeek(Request $request): RedirectResponse
    {
        $filters = $request->validate([
            'filiere_id' => ['required', 'integer', 'exists:filieres,id'],
            'niveau_id' => [
                'required',
                'integer',
                Rule::exists('niveaux', 'id')->where('filiere_id', $request->input('filiere_id')),
            ],
            'week_start' => [
                'bail',
                'required',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (Carbon::parse($value)->dayOfWeekIso !== Carbon::MONDAY) {
                        $fail('La semaine sélectionnée doit commencer un lundi.');
                    }
                },
            ],
        ], [
            'filiere_id.required' => 'Veuillez choisir une filière.',
            'niveau_id.required' => 'Veuillez choisir un niveau.',
            'week_start.required' => 'Veuillez choisir une semaine.',
            'week_start.date_format' => 'La semaine doit être indiquée au format AAAA-MM-JJ.',
        ]);

        $weekStart = Carbon::parse($filters['week_start']);
        $drafts = EmploiDuTemps::query()
            ->where('filiere_id', $filters['filiere_id'])
            ->where('niveau_id', $filters['niveau_id'])
            ->whereBetween('date_cours', [
                $weekStart->toDateString(),
                $weekStart->copy()->addDays(5)->toDateString(),
            ])
            ->where('statut', 'brouillon')
            ->with('matiere')
            ->orderBy('date_cours')
            ->orderBy('heure_debut')
            ->get();

        $unassignedSession = $drafts->first(fn (EmploiDuTemps $emploi): bool => (
            ($emploi->enseignant_id ?? $emploi->matiere?->enseignant_id) === null
        ));

        if ($unassignedSession !== null) {
            throw ValidationException::withMessages([
                'enseignant_id' => 'La séance « '.$unassignedSession->titre.' » ne peut pas être publiée sans enseignant.',
            ]);
        }

        foreach ($drafts as $emploi) {
            $emploi->update([
                'statut' => 'publie',
                'publie_par' => $request->user()->id,
                'publie_le' => now(),
            ]);
            $this->notifier($emploi);
        }

        return back()->with('success', $drafts->count().' séance(s) publiée(s).');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sessionAttributes(array $data): array
    {
        $matiere = Matiere::query()->findOrFail($data['matiere_id']);
        $date = Carbon::parse($data['week_start'])
            ->addDays((int) $data['jour'] - 1)
            ->toDateString();

        return [
            'titre' => $matiere->nom,
            'description' => $data['description'] ?? null,
            'filiere_id' => $data['filiere_id'],
            'niveau_id' => $data['niveau_id'],
            'matiere_id' => $matiere->id,
            'enseignant_id' => $data['enseignant_id'] ?? null,
            'date_cours' => $date,
            'heure_debut' => $data['heure_debut'],
            'heure_fin' => $data['heure_fin'],
            'salle' => $data['salle'] ?? null,
        ];
    }

    private function notifier(EmploiDuTemps $emploi): void
    {
        if (! $emploi->filiere_id || ! $emploi->niveau_id) {
            return;
        }

        $students = User::query()->where('role', 'student')
            ->where('filiere_id', $emploi->filiere_id)
            ->where('niveau_id', $emploi->niveau_id)
            ->get();
        Notification::send(
            $students,
            new PublicationNotification('Nouvel emploi du temps : '.$emploi->titre, 'Un emploi du temps a été publié pour votre formation.', '/espace'),
        );
    }
}
