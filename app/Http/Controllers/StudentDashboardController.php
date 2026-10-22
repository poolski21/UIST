<?php

namespace App\Http\Controllers;

use App\Http\Resources\AnnonceResource;
use App\Http\Resources\EmploiDuTempsResource;
use App\Http\Resources\NoteResource;
use App\Models\Annonce;
use App\Models\EmploiDuTemps;
use App\Models\ResultatAnnuel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $student = $request->user();
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();

        $annonces = Annonce::query()
            ->whereNotNull('publiee_le')
            ->where(fn ($query) => $query
                ->where('cible_type', 'tous')
                ->orWhere(fn ($q) => $q->where('cible_type', 'filiere')->where('cible_id', $student->filiere_id))
                ->orWhere(fn ($q) => $q->where('cible_type', 'niveau')->where('cible_id', $student->niveau_id)))
            ->latest('publiee_le')
            ->get();

        $schedule = EmploiDuTemps::query()
            ->with(['filiere', 'niveau'])
            ->where('statut', 'publie')
            ->whereBetween('date_cours', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->where(fn ($query) => $query
                ->whereNull('filiere_id')
                ->orWhere('filiere_id', $student->filiere_id))
            ->where(fn ($query) => $query
                ->whereNull('niveau_id')
                ->orWhere('niveau_id', $student->niveau_id))
            ->orderBy('date_cours')
            ->orderBy('heure_debut')
            ->get();

        $notes = $student->notes()
            ->with(['matiere', 'anneeAcademique'])
            ->where('statut', 'publie')
            ->latest('publie_le')
            ->get();
        $results = ResultatAnnuel::query()
            ->with('anneeAcademique')
            ->where('etudiant_id', $student->id)
            ->whereNotNull('publie_le')
            ->latest('publie_le')
            ->get();

        return Inertia::render('Student/Dashboard', [
            'annonces' => AnnonceResource::collection($annonces)->resolve(),
            'emploiDuTemps' => EmploiDuTempsResource::collection($schedule)->resolve(),
            'notes' => NoteResource::collection($notes)->resolve(),
            'resultats' => $results->map(fn ($resultat) => [
                'id' => $resultat->id,
                'annee' => $resultat->anneeAcademique->libelle,
                'moyenne' => (float) $resultat->moyenne_generale,
                'credits' => $resultat->credits_obtenus,
                'decision' => $resultat->decision_finale,
                'pdf' => route('releve.pdf', $resultat),
            ]),
            'notificationCount' => $student->unreadNotifications()->count(),
            'notifications' => $student->notifications()->latest()->take(10)->get(['id', 'data', 'read_at', 'created_at']),
            'week' => [$weekStart->format('d/m/Y'), $weekEnd->format('d/m/Y')],
        ]);
    }
}
