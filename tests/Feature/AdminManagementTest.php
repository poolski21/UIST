<?php

namespace Tests\Feature;

use App\Models\Filiere;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_matiere_creation_rejects_missing_filiere_and_niveau(): void
    {
        $admin = $this->createAdmin();
        $teacher = $this->createTeacher();

        $this->actingAs($admin)
            ->post(route('admin.matieres.store'), [
                'nom' => 'Matière incomplète',
                'code' => 'MAT-INCOMPLETE',
                'enseignant_id' => $teacher->id,
                'credits' => 3,
                'coefficient' => 1,
            ])
            ->assertSessionHasErrors(['filiere_id', 'niveau_id']);

        $this->assertDatabaseCount('matieres', 0);
    }

    public function test_matiere_creation_requires_an_enseignant_referent(): void
    {
        $admin = $this->createAdmin();
        $filiere = Filiere::create(['nom' => 'Informatique', 'code' => 'INFO']);
        $niveau = Niveau::create([
            'filiere_id' => $filiere->id,
            'nom' => 'Licence 3',
            'code' => 'L3',
            'credits_requis' => 60,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.matieres.store'), [
                'nom' => 'Matière sans enseignant',
                'code' => 'MAT-NO-TEACHER',
                'filiere_id' => $filiere->id,
                'niveau_id' => $niveau->id,
                'credits' => 3,
                'coefficient' => 1,
            ])
            ->assertSessionHasErrors('enseignant_id');

        $this->assertDatabaseCount('matieres', 0);
    }

    public function test_schedule_form_receives_only_subjects_for_the_selected_filiere_and_niveau(): void
    {
        $admin = $this->createAdmin();
        $teacher = $this->createTeacher();
        $filiere = Filiere::create(['nom' => 'Informatique', 'code' => 'INFO']);
        $selectedNiveau = Niveau::create([
            'filiere_id' => $filiere->id,
            'nom' => 'Licence 3',
            'code' => 'L3',
            'credits_requis' => 60,
        ]);
        $otherNiveau = Niveau::create([
            'filiere_id' => $filiere->id,
            'nom' => 'Master 1',
            'code' => 'M1',
            'credits_requis' => 60,
        ]);
        $selectedMatiere = $this->createMatiere($filiere, $selectedNiveau, $teacher, 'MAT-L3');
        $otherMatiere = $this->createMatiere($filiere, $otherNiveau, $teacher, 'MAT-M1');

        $this->actingAs($admin)
            ->get(route('admin.emplois', [
                'filiere_id' => $filiere->id,
                'niveau_id' => $selectedNiveau->id,
                'week_start' => '2026-10-26',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/EmploisDuTemps')
                ->where('selection.filiere_id', (string) $filiere->id)
                ->where('selection.niveau_id', (string) $selectedNiveau->id)
                ->has('matieres', 1)
                ->where('matieres.0.id', $selectedMatiere->id)
                ->where('matieres.0.enseignant_id', $teacher->id));

        $this->assertModelExists($otherMatiere);
    }

    private function createAdmin(): User
    {
        return User::create([
            'name' => 'Administrateur',
            'email' => 'admin-management@test.ci',
            'password' => 'MotDePasseSolide!',
            'role' => 'admin',
        ]);
    }

    private function createTeacher(): User
    {
        return User::create([
            'name' => 'Enseignant référent',
            'email' => 'teacher-management@test.ci',
            'password' => 'MotDePasseSolide!',
            'role' => 'teacher',
        ]);
    }

    private function createMatiere(Filiere $filiere, Niveau $niveau, User $teacher, string $code): Matiere
    {
        return Matiere::create([
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
            'enseignant_id' => $teacher->id,
            'nom' => 'Matière '.$code,
            'code' => $code,
            'credits' => 3,
            'coefficient' => 1,
        ]);
    }
}
