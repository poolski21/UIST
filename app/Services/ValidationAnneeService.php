<?php

namespace App\Services;

use App\Models\AnneeAcademique;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\ResultatAnnuel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ValidationAnneeService
{
    public function calculer(User $etudiant, AnneeAcademique $annee): ResultatAnnuel
    {
        $inscription = Inscription::query()
            ->with('niveau')
            ->where('etudiant_id', $etudiant->id)
            ->where('annee_academique_id', $annee->id)
            ->first();

        if (! $inscription) {
            throw ValidationException::withMessages([
                'etudiant_id' => 'Cet étudiant n’est pas inscrit pour cette année académique.',
            ]);
        }

        $notes = Note::query()
            ->with('matiere')
            ->where('etudiant_id', $etudiant->id)
            ->where('annee_academique_id', $annee->id)
            ->where('statut', 'publie')
            ->whereHas('matiere', fn ($query) => $query
                ->where('filiere_id', $inscription->niveau->filiere_id)
                ->where('niveau_id', $inscription->niveau_id))
            ->get();

        if ($notes->isEmpty()) {
            throw ValidationException::withMessages([
                'notes' => 'Aucune note publiée ne permet de calculer une décision annuelle.',
            ]);
        }

        $matieresAttendues = Matiere::query()
            ->where('filiere_id', $inscription->niveau->filiere_id)
            ->where('niveau_id', $inscription->niveau_id)
            ->get();
        if ($matieresAttendues->isEmpty() || $matieresAttendues->contains(
            fn (Matiere $matiere) => ! $notes->contains('matiere_id', $matiere->id),
        )) {
            throw ValidationException::withMessages([
                'notes' => 'Toutes les notes du niveau doivent être publiées avant le calcul de la décision annuelle.',
            ]);
        }

        $totalCoefficients = $notes->sum(fn (Note $note) => (float) $note->matiere->coefficient);
        if ($totalCoefficients <= 0 || $notes->contains(
            fn (Note $note) => (float) $note->matiere->coefficient <= 0,
        )) {
            throw ValidationException::withMessages(['notes' => 'Les coefficients des matières doivent être positifs.']);
        }

        $moyenne = $notes->sum(
            fn (Note $note) => (float) $note->note * (float) $note->matiere->coefficient,
        ) / $totalCoefficients;
        $credits = $notes
            ->filter(fn (Note $note) => (float) $note->note >= 10)
            ->sum(fn (Note $note) => (int) $note->matiere->credits);
        $decision = $moyenne >= config('uist2its.admission_average')
            && $credits >= $inscription->niveau->credits_requis
            ? 'admis'
            : 'ajourne';

        return DB::transaction(function () use ($etudiant, $annee, $moyenne, $credits, $decision): ResultatAnnuel {
            $resultat = ResultatAnnuel::firstOrNew([
                'etudiant_id' => $etudiant->id,
                'annee_academique_id' => $annee->id,
            ]);
            $decisionFinaleModifiee = $resultat->exists && $resultat->modifiee_par !== null;
            $decisionPubliee = $resultat->exists && $resultat->publie_le !== null;

            $resultat->moyenne_generale = round($moyenne, 2);
            $resultat->credits_obtenus = $credits;
            if (! $decisionPubliee) {
                $resultat->decision_auto = $decision;
                if (! $decisionFinaleModifiee) {
                    $resultat->decision_finale = $decision;
                    $resultat->modifiee_par = null;
                    $resultat->motif = null;
                }
            } elseif ($resultat->decision_finale !== null) {
                $resultat->decision_auto = $decision;
            }
            $resultat->a_recalculer = false;
            $resultat->save();

            return $resultat;
        });
    }
}
