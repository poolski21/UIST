<?php

namespace App\Http\Controllers;

use App\Models\AnneeAcademique;
use App\Models\Filiere;
use App\Models\Inscription;
use App\Models\Niveau;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ClassRosterController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Inscription::class);
        $selection = $this->validateSelection($request, false);
        $years = AnneeAcademique::query()->orderByDesc('date_debut')->get(['id', 'libelle', 'active']);
        $activeYear = $years->firstWhere('active', true);
        $yearId = $selection['annee_academique_id'] ?? $activeYear?->id;
        $filiereId = $selection['filiere_id'] ?? null;
        $niveauId = $selection['niveau_id'] ?? null;
        $students = $yearId && $filiereId && $niveauId
            ? $this->students($yearId, $filiereId, $niveauId)
            : collect();

        return Inertia::render('Admin/ClassRoster', [
            'annees' => $years,
            'anneeActiveId' => $activeYear?->id,
            'filieres' => Filiere::query()->orderBy('nom')->get(['id', 'nom']),
            'niveaux' => Niveau::query()
                ->when($filiereId, fn ($query) => $query->where('filiere_id', $filiereId))
                ->orderBy('nom')
                ->get(['id', 'nom', 'filiere_id']),
            'selection' => [
                'annee_academique_id' => $yearId,
                'filiere_id' => $filiereId,
                'niveau_id' => $niveauId,
            ],
            'etudiants' => $students,
            'pdfUrl' => $students->isNotEmpty() || ($yearId && $filiereId && $niveauId)
                ? route('admin.class-roster.pdf', [
                    'annee_academique_id' => $yearId,
                    'filiere_id' => $filiereId,
                    'niveau_id' => $niveauId,
                ])
                : null,
        ]);
    }

    public function pdf(Request $request): HttpResponse
    {
        Gate::authorize('download', Inscription::class);
        $selection = $this->validateSelection($request, true);
        $students = $this->students(
            $selection['annee_academique_id'],
            $selection['filiere_id'],
            $selection['niveau_id'],
        );
        $year = AnneeAcademique::findOrFail($selection['annee_academique_id']);
        $filiere = Filiere::findOrFail($selection['filiere_id']);
        $niveau = Niveau::query()
            ->whereKey($selection['niveau_id'])
            ->where('filiere_id', $filiere->id)
            ->firstOrFail();

        return Pdf::loadView('pdf.liste-classe', [
            'annee' => $year,
            'filiere' => $filiere,
            'niveau' => $niveau,
            'etudiants' => $students,
            'dateEdition' => now()->format('d/m/Y H:i'),
        ])->download('liste-classe-'.$filiere->code.'-'.$niveau->code.'-'.$year->libelle.'.pdf');
    }

    private function validateSelection(Request $request, bool $requireAll): array
    {
        return $request->validate([
            'annee_academique_id' => [$requireAll ? 'required' : 'nullable', 'integer', 'exists:annees_academiques,id'],
            'filiere_id' => [$requireAll ? 'required' : 'nullable', 'integer', 'exists:filieres,id'],
            'niveau_id' => [
                $requireAll ? 'required' : 'nullable',
                'integer',
                'required_with:filiere_id',
                Rule::exists('niveaux', 'id')->where('filiere_id', $request->query('filiere_id')),
            ],
        ]);
    }

    private function students(int|string $yearId, int|string $filiereId, int|string $niveauId)
    {
        return Inscription::query()
            ->join('users', 'users.id', '=', 'inscriptions.etudiant_id')
            ->join('niveaux', 'niveaux.id', '=', 'inscriptions.niveau_id')
            ->where('inscriptions.annee_academique_id', $yearId)
            ->where('inscriptions.niveau_id', $niveauId)
            ->where('niveaux.filiere_id', $filiereId)
            ->where('users.role', 'student')
            ->select('users.id as student_id', 'users.matricule', 'users.name')
            ->orderBy('users.matricule')
            ->orderBy('users.name')
            ->get()
            ->map(function ($student): array {
                $parts = preg_split('/\s+/u', trim((string) $student->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

                return [
                    'id' => $student->student_id,
                    'matricule' => $student->matricule,
                    'nom' => implode(' ', array_slice($parts, 1)),
                    'prenom' => $parts[0] ?? '',
                ];
            });
    }
}
