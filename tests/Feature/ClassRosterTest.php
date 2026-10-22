<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Filiere;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassRosterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_roster_defaults_to_active_year_and_filters_by_historical_enrollment(): void
    {
        $filiere = Filiere::create(['nom' => 'Informatique', 'code' => 'INFO']);
        $otherFiliere = Filiere::create(['nom' => 'Gestion', 'code' => 'GEST']);
        $niveau = Niveau::create(['filiere_id' => $filiere->id, 'nom' => 'Licence 1', 'code' => 'L1', 'credits_requis' => 60]);
        $otherNiveau = Niveau::create(['filiere_id' => $otherFiliere->id, 'nom' => 'Licence 1', 'code' => 'L1', 'credits_requis' => 60]);
        $activeYear = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'active' => true]);
        $oldYear = AnneeAcademique::create(['libelle' => '2025-2026', 'date_debut' => '2025-09-01', 'date_fin' => '2026-07-31']);
        $currentStudent = User::create(['name' => 'Aya Traoré', 'role' => 'student', 'matricule' => 'ETU-002', 'filiere_id' => $filiere->id, 'niveau_id' => $niveau->id]);
        $oldStudent = User::create(['name' => 'Koffi Kouamé', 'role' => 'student', 'matricule' => 'ETU-001', 'filiere_id' => $filiere->id, 'niveau_id' => $niveau->id]);
        $otherStudent = User::create(['name' => 'Mariam Diallo', 'role' => 'student', 'matricule' => 'ETU-003', 'filiere_id' => $otherFiliere->id, 'niveau_id' => $otherNiveau->id]);
        Inscription::create(['etudiant_id' => $currentStudent->id, 'niveau_id' => $niveau->id, 'annee_academique_id' => $activeYear->id]);
        Inscription::create(['etudiant_id' => $oldStudent->id, 'niveau_id' => $niveau->id, 'annee_academique_id' => $oldYear->id]);
        Inscription::create(['etudiant_id' => $otherStudent->id, 'niveau_id' => $otherNiveau->id, 'annee_academique_id' => $activeYear->id]);
        $admin = User::create(['name' => 'Admin', 'email' => 'roster-admin@test.ci', 'password' => 'MotDePasseTresSolide!', 'role' => 'admin']);

        $this->actingAs($admin)->get('/admin/listes-classe?filiere_id='.$filiere->id.'&niveau_id='.$niveau->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/ClassRoster')
                ->where('selection.annee_academique_id', $activeYear->id)
                ->has('etudiants', 1)
                ->where('etudiants.0.matricule', 'ETU-002'));

        $this->get(route('admin.class-roster.pdf', [
            'annee_academique_id' => $oldYear->id,
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
        ]))->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs($oldStudent)->get('/admin/listes-classe')->assertForbidden();
        $this->get(route('admin.class-roster.pdf', [
            'annee_academique_id' => $oldYear->id,
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
        ]))->assertForbidden();
    }
}
