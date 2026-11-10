<?php

namespace App\Http\Controllers\Etudiant;

use App\Http\Controllers\Controller;
use App\Models\SupportCours;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportController extends Controller
{
    public function index(Request $request): Response
    {
        $student = $request->user();
        $supportsCours = SupportCours::query()
            ->with('niveaux')
            ->whereHas('niveaux', fn ($query) => $query
                ->whereKey($student->niveau_id)
                ->where('filiere_id', $student->filiere_id))
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (SupportCours $support): array => [
                'id' => $support->id,
                'titre' => $support->titre,
                'description' => $support->description,
                'nom_fichier' => $support->nom_fichier,
                'created_at' => $support->created_at?->toIso8601String(),
                'download_url' => route('supports-cours.download', $support),
            ]);

        return Inertia::render('Student/Supports', [
            'supportsCours' => $supportsCours,
        ]);
    }
}
