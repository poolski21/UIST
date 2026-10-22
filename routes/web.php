<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminPasswordController;
use App\Http\Controllers\ClassRosterController;
use App\Http\Controllers\AnnonceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmploiDuTempsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ResultatAnnuelController;
use App\Http\Controllers\StudentDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::post('/langue', [LocaleController::class, 'update'])->name('locale.update');
Route::middleware('guest')->group(function () {
    Route::get('/connexion', [AuthController::class, 'create'])->name('login');
    Route::post('/connexion/etudiant', [AuthController::class, 'student'])->middleware('throttle:20,1')->name('login.student');
    Route::post('/connexion/personnel', [AuthController::class, 'user'])->middleware('throttle:10,1')->name('login.user');
});
Route::post('/deconnexion', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/espace', function () {
        return match (auth()->user()->role) {
            'student' => redirect()->route('etudiant.dashboard'),
            'teacher' => redirect()->route('enseignant.notes'),
            'admin' => redirect()->route('admin.dashboard'),
            default => abort(403),
        };
    })->name('espace');

    Route::get('/etudiant', StudentDashboardController::class)
        ->middleware('role:student')->name('etudiant.dashboard');
    Route::post('/notifications/{notification}/lire', [NotificationController::class, 'read'])
        ->name('notifications.read');
    Route::get('/resultats/{resultat}/releve.pdf', [ResultatAnnuelController::class, 'pdf'])
        ->name('releve.pdf');

    Route::prefix('enseignant')->name('enseignant.')->middleware('role:teacher')->group(function () {
        Route::get('/notes', [NoteController::class, 'index'])->name('notes');
        Route::post('/notes', [NoteController::class, 'store'])->name('notes.store');
    });

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('dashboard');
        Route::get('/gestion', [AdminController::class, 'manage'])->name('manage');
        Route::post('/filieres', [AdminController::class, 'storeFiliere'])->name('filieres.store');
        Route::post('/niveaux', [AdminController::class, 'storeNiveau'])->name('niveaux.store');
        Route::post('/matieres', [AdminController::class, 'storeMatiere'])->name('matieres.store');
        Route::post('/etudiants', [AdminController::class, 'storeStudent'])->name('etudiants.store');
        Route::post('/enseignants', [AdminController::class, 'storeTeacher'])->name('enseignants.store');
        Route::post('/annees-academiques', [AdminController::class, 'storeAnnee'])->name('annees.store');
        Route::post('/categories-cours', [AdminController::class, 'storeCategorie'])->name('categories.store');
        Route::post('/cours', [AdminController::class, 'storeCours'])->name('cours.store');
        Route::get('/annonces', [AnnonceController::class, 'index'])->name('annonces');
        Route::post('/annonces', [AnnonceController::class, 'store'])->name('annonces.store');
        Route::post('/annonces/{annonce}/publier', [AnnonceController::class, 'publish'])->name('annonces.publish');
        Route::get('/emplois-du-temps', [EmploiDuTempsController::class, 'index'])->name('emplois');
        Route::post('/emplois-du-temps', [EmploiDuTempsController::class, 'store'])->name('emplois.store');
        Route::post('/emplois-du-temps/{emploiDuTemps}/publier', [EmploiDuTempsController::class, 'publish'])->name('emplois.publish');
        Route::get('/notes', [NoteController::class, 'index'])->name('notes');
        Route::post('/notes', [NoteController::class, 'store'])->name('notes.store');
        Route::post('/notes/{matiere}/{annee}/publier', [NoteController::class, 'publish'])->name('notes.publish');
        Route::get('/resultats', [ResultatAnnuelController::class, 'index'])->name('resultats');
        Route::post('/resultats/calculer/{etudiant}/{annee}', [ResultatAnnuelController::class, 'calculate'])->name('resultats.calculate');
        Route::put('/resultats/{resultat}/decision', [ResultatAnnuelController::class, 'decide'])->name('resultats.decide');
        Route::post('/resultats/{resultat}/publier', [ResultatAnnuelController::class, 'publish'])->name('resultats.publish');
        Route::get('/listes-classe', [ClassRosterController::class, 'index'])->name('class-roster.index');
        Route::get('/listes-classe/pdf', [ClassRosterController::class, 'pdf'])->name('class-roster.pdf');
        Route::get('/securite/mots-de-passe', [AdminPasswordController::class, 'index'])->name('passwords.index');
        Route::post('/securite/mots-de-passe/{user}', [AdminPasswordController::class, 'reset'])->name('passwords.reset');
        Route::put('/securite/mot-de-passe', [AdminPasswordController::class, 'update'])->name('passwords.update');
    });
});
