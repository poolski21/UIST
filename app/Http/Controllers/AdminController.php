<?php

namespace App\Http\Controllers;

use App\Models\AnneeAcademique;
use App\Models\Annonce;
use App\Models\CategorieCours;
use App\Models\Cours;
use App\Models\Filiere;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'etudiants' => User::where('role', 'student')->count(),
                'enseignants' => User::where('role', 'teacher')->count(),
                'matieres' => Matiere::count(),
                'annonces' => Annonce::whereNotNull('publiee_le')->count(),
            ],
        ]);
    }

    public function manage(): Response
    {
        return Inertia::render('Admin/Manage', [
            'filieres' => Filiere::with('niveaux')->orderBy('nom')->get(),
            'niveaux' => Niveau::with('filiere')->orderBy('nom')->get(),
            'matieres' => Matiere::with(['filiere', 'niveau', 'enseignant'])->orderBy('nom')->get(),
            'enseignants' => User::where('role', 'teacher')->orderBy('name')->get(['id', 'name']),
            'annees' => AnneeAcademique::orderByDesc('date_debut')->get(['id', 'libelle', 'active']),
            'etudiants' => User::where('role', 'student')->with(['filiere', 'niveau'])->orderBy('name')->get(),
            'categories' => CategorieCours::orderBy('nom')->get(['id', 'nom']),
        ]);
    }

    public function storeFiliere(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:30', 'unique:filieres,code'],
            'description' => ['nullable', 'string'],
        ]);
        Filiere::create($data);

        return back()->with('success', 'Filière créée.');
    }

    public function storeNiveau(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'filiere_id' => ['required', 'exists:filieres,id'],
            'nom' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:30'],
            'credits_requis' => ['required', 'integer', 'min:1', 'max:300'],
        ]);
        Niveau::create($data);

        return back()->with('success', 'Niveau créé.');
    }

    public function storeMatiere(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'filiere_id' => ['required', 'exists:filieres,id'],
            'niveau_id' => ['required', Rule::exists('niveaux', 'id')->where('filiere_id', $request->input('filiere_id'))],
            'enseignant_id' => ['required', Rule::exists('users', 'id')->where('role', 'teacher')],
            'nom' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:30', 'unique:matieres,code'],
            'credits' => ['required', 'integer', 'min:1', 'max:60'],
            'coefficient' => ['required', 'numeric', 'gt:0', 'max:99'],
        ], [
            'filiere_id.required' => 'Veuillez choisir une filière pour la matière.',
            'filiere_id.exists' => 'La filière sélectionnée est invalide.',
            'niveau_id.required' => 'Veuillez choisir un niveau pour la matière.',
            'niveau_id.exists' => 'Le niveau sélectionné ne correspond pas à la filière.',
            'enseignant_id.required' => 'Veuillez choisir un enseignant référent pour la matière.',
            'enseignant_id.exists' => 'L’enseignant référent sélectionné est invalide.',
        ]);
        Matiere::create($data);

        return back()->with('success', 'Matière créée.');
    }

    public function storeStudent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'matricule' => ['required', 'string', 'max:80', 'unique:users,matricule'],
            'email' => ['nullable', 'email', 'unique:users,email'],
            'date_naissance' => ['nullable', 'date'],
            'filiere_id' => ['required', 'exists:filieres,id'],
            'niveau_id' => ['required', Rule::exists('niveaux', 'id')->where('filiere_id', $request->input('filiere_id'))],
            'annee_academique_id' => ['required', 'exists:annees_academiques,id'],
        ]);

        $student = User::create([
            ...$data,
            'role' => 'student',
            'password' => null,
        ]);
        Inscription::create([
            'etudiant_id' => $student->id,
            'niveau_id' => $data['niveau_id'],
            'annee_academique_id' => $data['annee_academique_id'],
        ]);

        return back()->with('success', 'Étudiant créé et inscrit.');
    }

    public function storeTeacher(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12'],
        ]);
        User::create([...$data, 'role' => 'teacher']);

        return back()->with('success', 'Compte enseignant créé.');
    }

    public function storeAnnee(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'libelle' => ['required', 'string', 'max:40', 'unique:annees_academiques,libelle'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after:date_debut'],
            'active' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($data): void {
            if ($data['active'] ?? false) {
                AnneeAcademique::query()->update(['active' => false]);
            }
            AnneeAcademique::create($data);
        });

        return back()->with('success', 'Année académique créée.');
    }

    public function storeCategorie(Request $request): RedirectResponse
    {
        $data = $request->validate(['nom' => ['required', 'string', 'max:150', 'unique:categories_cours,nom']]);
        CategorieCours::create(['nom' => $data['nom'], 'slug' => Str::slug($data['nom'])]);

        return back()->with('success', 'Catégorie de cours créée.');
    }

    public function storeCours(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'categorie_cours_id' => ['required', 'exists:categories_cours,id'],
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('cours', 'public');
        }
        Cours::create([...$data, 'actif' => true]);

        return back()->with('success', 'Cours publié sur la page d’accueil.');
    }
}
