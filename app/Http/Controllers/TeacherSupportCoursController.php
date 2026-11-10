<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupportCoursRequest;
use App\Models\Filiere;
use App\Models\SupportCours;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TeacherSupportCoursController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('create', SupportCours::class);

        $supports = SupportCours::query()
            ->with(['niveaux.filiere'])
            ->where('enseignant_id', $request->user()->id)
            ->latest()
            ->take(100)
            ->get()
            ->map(fn (SupportCours $support): array => [
                'id' => $support->id,
                'titre' => $support->titre,
                'description' => $support->description,
                'nom_fichier' => $support->nom_fichier,
                'created_at' => $support->created_at?->toIso8601String(),
                'niveaux' => $support->niveaux->map(fn ($niveau): array => [
                    'id' => $niveau->id,
                    'nom' => $niveau->nom,
                    'filiere' => $niveau->filiere->nom,
                ])->all(),
                'download_url' => route('supports-cours.download', $support),
            ])->all();

        return Inertia::render('Teacher/SupportsCours', [
            'filieres' => Filiere::query()
                ->with(['niveaux' => fn ($query) => $query->orderBy('nom')])
                ->orderBy('nom')
                ->get(['id', 'nom']),
            'supports' => $supports,
        ]);
    }

    public function store(StoreSupportCoursRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $fichier = $request->file('fichier');
        $chemin = $fichier->store('supports-cours', 'local');

        if (! is_string($chemin) || $chemin === '') {
            throw new RuntimeException('Le fichier de cours n’a pas pu être enregistré.');
        }

        $nomFichier = basename(str_replace('\\', '/', $fichier->getClientOriginalName()));
        $nomFichier = preg_replace('/[\x00-\x1F\x7F]/u', '', $nomFichier) ?: 'support-de-cours';

        try {
            DB::transaction(function () use ($data, $request, $chemin, $nomFichier, $fichier): void {
                $support = SupportCours::create([
                    'enseignant_id' => $request->user()->id,
                    'titre' => $data['titre'],
                    'description' => $data['description'] ?? null,
                    'chemin_fichier' => $chemin,
                    'nom_fichier' => $nomFichier,
                    'type_mime' => $fichier->getMimeType() ?: 'application/octet-stream',
                ]);
                $support->niveaux()->sync(array_map('intval', $data['niveaux_ids']));
            });
        } catch (Throwable $exception) {
            if (! Storage::disk('local')->delete($chemin)) {
                Log::error('Échec du nettoyage d’un fichier de cours après échec de sa sauvegarde.', [
                    'chemin' => $chemin,
                    'exception' => $exception,
                ]);
            }

            throw $exception;
        }

        return back()->with('success', 'Le support de cours a été envoyé aux classes sélectionnées.');
    }

    public function download(SupportCours $supportCours): StreamedResponse
    {
        $this->authorize('view', $supportCours);
        abort_unless(Storage::disk('local')->exists($supportCours->chemin_fichier), 404);

        return Storage::disk('local')->download(
            $supportCours->chemin_fichier,
            $supportCours->nom_fichier,
            [
                'Content-Type' => $supportCours->type_mime,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
