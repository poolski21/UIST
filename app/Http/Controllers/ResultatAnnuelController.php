<?php

namespace App\Http\Controllers;

use App\Http\Requests\DecisionAnnuelleRequest;
use App\Models\AnneeAcademique;
use App\Models\JournalModification;
use App\Models\ResultatAnnuel;
use App\Models\User;
use App\Notifications\PublicationNotification;
use App\Services\ValidationAnneeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ResultatAnnuelController extends Controller
{
    public function index(): Response
    {
        abort_unless(auth()->user()->role === 'admin', 403);

        return Inertia::render('Admin/Resultats', [
            'resultats' => ResultatAnnuel::with(['etudiant', 'anneeAcademique'])->latest()->get(),
            'etudiants' => User::where('role', 'student')->orderBy('name')->get(['id', 'name', 'matricule']),
            'annees' => AnneeAcademique::orderByDesc('date_debut')->get(['id', 'libelle']),
        ]);
    }

    public function calculate(User $etudiant, AnneeAcademique $annee, ValidationAnneeService $service): RedirectResponse
    {
        abort_unless(auth()->user()->role === 'admin' && $etudiant->role === 'student', 403);
        $service->calculer($etudiant, $annee);

        return back()->with('success', 'Décision automatique calculée.');
    }

    public function decide(DecisionAnnuelleRequest $request, ResultatAnnuel $resultat): RedirectResponse
    {
        $this->authorize('update', ResultatAnnuel::class);
        $data = $request->validated();

        DB::transaction(function () use ($request, $resultat, $data): void {
            $resultat = ResultatAnnuel::query()->lockForUpdate()->findOrFail($resultat->id);
            abort_if($resultat->a_recalculer, 409, 'Recalculez le résultat avant d’enregistrer une nouvelle décision.');
            $before = $resultat->only(['decision_auto', 'decision_finale', 'motif']);
            $resultat->update([
                'decision_finale' => $data['decision_finale'],
                'modifiee_par' => $request->user()->id,
                'motif' => $data['motif'],
                'a_recalculer' => false,
            ]);
            JournalModification::create([
                'modifie_par' => $request->user()->id,
                'element_type' => ResultatAnnuel::class,
                'element_id' => $resultat->id,
                'anciennes_valeurs' => $before,
                'nouvelles_valeurs' => $resultat->only(['decision_auto', 'decision_finale', 'motif']),
                'motif' => $data['motif'],
            ]);
        });

        return back()->with('success', 'Décision finale mise à jour et tracée.');
    }

    public function publish(ResultatAnnuel $resultat): RedirectResponse
    {
        abort_unless(auth()->user()->role === 'admin', 403);
        abort_if($resultat->a_recalculer, 409, 'Le résultat doit être recalculé avant sa publication.');
        if (! $resultat->publie_le) {
            $resultat->update(['publie_le' => now()]);
            $resultat->etudiant->notify(new PublicationNotification('Décision annuelle publiée', 'Votre décision de fin d’année est disponible avec votre relevé de notes.', '/espace'));
        }

        return back()->with('success', 'Décision publiée à l’étudiant.');
    }

    public function pdf(ResultatAnnuel $resultat)
    {
        $this->authorize('view', $resultat);
        $resultat->load(['etudiant.filiere', 'etudiant.niveau', 'anneeAcademique']);
        $notes = $resultat->etudiant->notes()
            ->with('matiere')
            ->where('annee_academique_id', $resultat->annee_academique_id)
            ->where('statut', 'publie')
            ->get();

        return Pdf::loadView('pdf.releve', compact('resultat', 'notes'))
            ->download('releve-'.$resultat->etudiant->matricule.'-'.$resultat->anneeAcademique->libelle.'.pdf');
    }
}
