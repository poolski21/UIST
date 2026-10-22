<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnnonceRequest;
use App\Models\Annonce;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\User;
use App\Notifications\PublicationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class AnnonceController extends Controller
{
    public function index(): Response
    {
        $this->authorize('create', Annonce::class);

        return Inertia::render('Admin/Annonces', [
            'annonces' => Annonce::with('createur')->latest()->get(),
            'filieres' => Filiere::orderBy('nom')->get(['id', 'nom']),
            'niveaux' => Niveau::with('filiere')->orderBy('nom')->get(['id', 'nom', 'filiere_id']),
        ]);
    }

    public function store(StoreAnnonceRequest $request): RedirectResponse
    {
        $this->authorize('create', Annonce::class);
        $data = $request->validated();
        if ($data['cible_type'] === 'filiere') {
            abort_unless(Filiere::whereKey($data['cible_id'])->exists(), 422);
        } elseif ($data['cible_type'] === 'niveau') {
            abort_unless(Niveau::whereKey($data['cible_id'])->exists(), 422);
        } else {
            $data['cible_id'] = null;
        }

        $attachment = $request->file('piece_jointe')?->store('annonces', 'public');
        $annonce = Annonce::create([
            ...$data,
            'piece_jointe' => $attachment,
            'cree_par' => $request->user()->id,
            'publiee_le' => $request->boolean('publier') ? now() : null,
        ]);

        if ($annonce->publiee_le) {
            $this->notifier($annonce);
        }

        return back()->with('success', $annonce->publiee_le ? 'Annonce publiée.' : 'Brouillon enregistré.');
    }

    public function publish(Annonce $annonce): RedirectResponse
    {
        $this->authorize('create', Annonce::class);
        if (! $annonce->publiee_le) {
            $annonce->update(['publiee_le' => now()]);
            $this->notifier($annonce);
        }

        return back()->with('success', 'Annonce publiée.');
    }

    private function notifier(Annonce $annonce): void
    {
        $etudiants = User::query()->where('role', 'student')
            ->when($annonce->cible_type === 'filiere', fn ($query) => $query->where('filiere_id', $annonce->cible_id))
            ->when($annonce->cible_type === 'niveau', fn ($query) => $query->where('niveau_id', $annonce->cible_id))
            ->get();

        Notification::send(
            $etudiants,
            new PublicationNotification('Nouvelle information : '.$annonce->titre, 'Une nouvelle communication est disponible dans votre espace étudiant.', '/espace'),
        );
    }
}
