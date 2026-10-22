<?php

namespace App\Http\Controllers;

use App\Models\CategorieCours;
use App\Models\Cours;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Home', [
            'cours' => Cours::query()->where('actif', true)->orderBy('id')->get(['id', 'titre', 'image', 'categorie_cours_id']),
            'categories' => CategorieCours::query()
                ->with(['cours' => fn ($query) => $query->where('actif', true)->orderBy('titre')->select(['id', 'titre', 'categorie_cours_id'])])
                ->withCount(['cours' => fn ($query) => $query->where('actif', true)])
                ->orderBy('id')
                ->get(['id', 'nom', 'slug']),
            'socialLinks' => config('uist2its.social_links'),
        ]);
    }
}
