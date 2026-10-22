<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNoteRequest;
use App\Models\AnneeAcademique;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\User;
use App\Services\CalculNoteMatiere;
use App\Notifications\PublicationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NoteController extends Controller
{
    public function index(): Response
    {
        abort_unless(in_array(auth()->user()->role, ['admin', 'teacher'], true), 403);
        $matiereQuery = Matiere::with('niveau.filiere')->withCount('notes')->orderBy('nom');
        if (auth()->user()->role === 'teacher') {
            $matiereQuery->where('enseignant_id', auth()->id());
        }
        $matieres = $matiereQuery->get();
        $etudiantsQuery = User::where('role', 'student')->orderBy('name');
        if (auth()->user()->role === 'teacher') {
            $etudiantsQuery->where(function ($query) use ($matieres): void {
                foreach ($matieres as $matiere) {
                    $query->orWhere(fn ($studentQuery) => $studentQuery
                        ->where('filiere_id', $matiere->filiere_id)
                        ->where('niveau_id', $matiere->niveau_id));
                }
                if ($matieres->isEmpty()) {
                    $query->whereRaw('1 = 0');
                }
            });
        }

        return Inertia::render('Teacher/Notes', [
            'matieres' => $matieres,
            'etudiants' => $etudiantsQuery->get(['id', 'name', 'matricule', 'filiere_id', 'niveau_id']),
            'annees' => AnneeAcademique::orderByDesc('date_debut')->get(['id', 'libelle', 'active']),
            'notes' => Note::with(['etudiant', 'matiere', 'anneeAcademique'])
                ->when(auth()->user()->role === 'teacher', fn ($q) => $q->whereHas('matiere', fn ($m) => $m->where('enseignant_id', auth()->id())))
                ->latest()->take(200)->get(),
        ]);
    }

    public function store(StoreNoteRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $matiere = Matiere::findOrFail($data['matiere_id']);
        $etudiant = User::where('role', 'student')->findOrFail($data['etudiant_id']);
        $inscrit = Inscription::query()
            ->where('etudiant_id', $etudiant->id)
            ->where('annee_academique_id', $data['annee_academique_id'])
            ->where('niveau_id', $matiere->niveau_id)
            ->exists();
        if ($etudiant->filiere_id !== $matiere->filiere_id || ! $inscrit) {
            throw ValidationException::withMessages(['etudiant_id' => 'Cet étudiant n’est pas inscrit dans la filière et le niveau de cette matière.']);
        }

        Note::updateOrCreate(
            ['etudiant_id' => $etudiant->id, 'matiere_id' => $matiere->id, 'annee_academique_id' => $data['annee_academique_id']],
            [
                'note' => app(CalculNoteMatiere::class)->calculer($data['notes_td'], (float) $data['note_examen']),
                'notes_td' => array_map('floatval', $data['notes_td']),
                'note_examen' => $data['note_examen'],
                'statut' => 'brouillon',
                'saisie_par' => $request->user()->id,
                'publie_le' => null,
            ],
        );

        return back()->with('success', 'Note enregistrée en brouillon. Sa publication la rendra visible à l’étudiant.');
    }

    public function publish(Matiere $matiere, AnneeAcademique $annee): RedirectResponse
    {
        $this->authorize('publish', Note::class);
        $notes = Note::query()
            ->where('matiere_id', $matiere->id)
            ->where('annee_academique_id', $annee->id)
            ->where('statut', 'brouillon')
            ->get();

        foreach ($notes as $note) {
            $note->update(['statut' => 'publie', 'publie_le' => now()]);
            $note->etudiant->notify(new PublicationNotification('Nouvelle note publiée', 'Une note de '.$matiere->nom.' est disponible dans votre espace étudiant.', '/espace'));
        }

        return back()->with('success', $notes->count().' note(s) publiée(s).');
    }
}
