<?php

namespace App\Http\Controllers\Etudiant;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnnonceResource;
use App\Http\Resources\NoteResource;
use App\Models\Annonce;
use App\Models\Note;
use App\Models\ResultatAnnuel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InfoController extends Controller
{
    public function index(Request $request): Response
    {
        $student = $request->user();
        $annonces = Annonce::query()
            ->whereNotNull('publiee_le')
            ->where(fn ($query) => $query
                ->where('cible_type', 'tous')
                ->orWhere(fn ($q) => $q->where('cible_type', 'filiere')->where('cible_id', $student->filiere_id))
                ->orWhere(fn ($q) => $q->where('cible_type', 'niveau')->where('cible_id', $student->niveau_id)))
            ->latest('publiee_le')
            ->paginate(12, ['*'], 'infos_page')
            ->withQueryString()
            ->through(fn (Annonce $annonce): array => (new AnnonceResource($annonce))->resolve());

        $notes = $student->notes()
            ->with(['matiere', 'anneeAcademique'])
            ->where('statut', 'publie')
            ->latest('publie_le')
            ->paginate(12, ['*'], 'notes_page')
            ->withQueryString()
            ->through(fn (Note $note): array => (new NoteResource($note))->resolve());

        $resultats = ResultatAnnuel::query()
            ->with('anneeAcademique')
            ->where('etudiant_id', $student->id)
            ->whereNotNull('publie_le')
            ->latest('publie_le')
            ->paginate(12, ['*'], 'resultats_page')
            ->withQueryString()
            ->through(fn (ResultatAnnuel $resultat): array => [
                'id' => $resultat->id,
                'annee' => $resultat->anneeAcademique->libelle,
                'moyenne' => (float) $resultat->moyenne_generale,
                'credits' => $resultat->credits_obtenus,
                'decision' => $resultat->decision_finale,
                'pdf' => route('releve.pdf', $resultat),
            ]);

        return Inertia::render('Student/Infos', [
            'annonces' => $annonces,
            'notes' => $notes,
            'resultats' => $resultats,
            'notificationCount' => $student->unreadNotifications()->count(),
            'notifications' => $student->notifications()->latest()->take(10)->get(['id', 'data', 'read_at', 'created_at']),
        ]);
    }
}
