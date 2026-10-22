<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmploiDuTempsRequest;
use App\Models\EmploiDuTemps;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\User;
use App\Notifications\PublicationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class EmploiDuTempsController extends Controller
{
    public function index(): Response
    {
        abort_unless(auth()->user()->role === 'admin', 403);

        return Inertia::render('Admin/EmploisDuTemps', [
            'emplois' => EmploiDuTemps::with(['filiere', 'niveau'])->latest('date_cours')->get(),
            'filieres' => Filiere::orderBy('nom')->get(['id', 'nom']),
            'niveaux' => Niveau::orderBy('nom')->get(['id', 'nom', 'filiere_id']),
        ]);
    }

    public function store(StoreEmploiDuTempsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $publier = $request->boolean('publier');
        $emploi = EmploiDuTemps::create([
            ...$data,
            'statut' => $publier ? 'publie' : 'brouillon',
            'publie_par' => $publier ? $request->user()->id : null,
            'publie_le' => $publier ? now() : null,
        ]);

        if ($publier) {
            $this->notifier($emploi);
        }

        return back()->with('success', $publier ? 'Emploi du temps publié.' : 'Brouillon enregistré.');
    }

    public function publish(EmploiDuTemps $emploiDuTemps): RedirectResponse
    {
        abort_unless(auth()->user()->role === 'admin', 403);
        if ($emploiDuTemps->statut !== 'publie') {
            $emploiDuTemps->update([
                'statut' => 'publie',
                'publie_par' => auth()->id(),
                'publie_le' => now(),
            ]);
            $this->notifier($emploiDuTemps);
        }

        return back()->with('success', 'Emploi du temps publié.');
    }

    private function notifier(EmploiDuTemps $emploi): void
    {
        $students = User::query()->where('role', 'student')
            ->when($emploi->filiere_id, fn ($query) => $query->where('filiere_id', $emploi->filiere_id))
            ->when($emploi->niveau_id, fn ($query) => $query->where('niveau_id', $emploi->niveau_id))
            ->get();
        Notification::send(
            $students,
            new PublicationNotification('Nouvel emploi du temps : '.$emploi->titre, 'Un emploi du temps a été publié pour votre formation.', '/espace'),
        );
    }
}
