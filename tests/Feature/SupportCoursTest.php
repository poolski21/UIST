<?php

namespace Tests\Feature;

use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\SupportCours;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupportCoursTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_teacher_can_send_one_private_file_to_multiple_classes_for_immediate_download(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $teacher = $this->createUser('teacher', 'teacher-support@test.ci');
        $targetFiliere = $this->createFiliere('Informatique', 'INFO-SUPPORT');
        $otherFiliere = $this->createFiliere('Gestion', 'GEST-SUPPORT');
        $targetLevel = $this->createNiveau($targetFiliere, 'Licence 1', 'L1');
        $secondTargetLevel = $this->createNiveau($targetFiliere, 'Licence 2', 'L2');
        $otherLevel = $this->createNiveau($otherFiliere, 'Licence 1', 'L1');
        $student = $this->createStudent('UIST-SUPPORT-TARGET', $targetFiliere, $targetLevel);
        $otherStudent = $this->createStudent('UIST-SUPPORT-OTHER', $otherFiliere, $otherLevel);

        $this->actingAs($teacher)
            ->from(route('enseignant.supports-cours.index'))
            ->post(route('enseignant.supports-cours.store'), [
                'titre' => 'Introduction à la programmation',
                'description' => 'Chapitre 1.',
                'fichier' => UploadedFile::fake()->create('chapitre-1.pdf', 100, 'application/pdf'),
                'niveaux_ids' => [$targetLevel->id, $secondTargetLevel->id],
            ])
            ->assertRedirect(route('enseignant.supports-cours.index'));

        $support = SupportCours::query()->firstOrFail();
        $this->assertDatabaseHas('support_cours_niveaux', [
            'support_cours_id' => $support->id,
            'niveau_id' => $targetLevel->id,
        ]);
        $this->assertDatabaseHas('support_cours_niveaux', [
            'support_cours_id' => $support->id,
            'niveau_id' => $secondTargetLevel->id,
        ]);
        $this->assertDatabaseMissing('support_cours_niveaux', [
            'support_cours_id' => $support->id,
            'niveau_id' => $otherLevel->id,
        ]);
        Storage::disk('local')->assertExists($support->chemin_fichier);
        Storage::disk('public')->assertMissing($support->chemin_fichier);

        $this->actingAs($student)
            ->get(route('etudiant.supports'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Student/Supports')
                ->where('supportsCours.data.0.titre', 'Introduction à la programmation')
                ->where('supportsCours.data.0.download_url', route('supports-cours.download', $support)));

        $this->get(route('supports-cours.download', $support))
            ->assertOk()
            ->assertDownload('chapitre-1.pdf');

        $this->actingAs($otherStudent)
            ->get(route('etudiant.supports'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('supportsCours.data', 0));
    }

    public function test_student_supports_are_paginated_by_twelve_and_scoped_to_their_class(): void
    {
        $filiere = $this->createFiliere('Informatique', 'INFO-PAGINATED-SUPPORTS');
        $niveau = $this->createNiveau($filiere, 'Licence 1', 'L1');
        $student = $this->createStudent('UIST-SUPPORT-PAGINATION', $filiere, $niveau);
        $teacher = $this->createUser('teacher', 'teacher-paginated-support@test.ci');

        foreach (range(1, 13) as $number) {
            $support = SupportCours::create([
                'enseignant_id' => $teacher->id,
                'titre' => 'Support '.$number,
                'chemin_fichier' => 'supports-cours/support-'.$number.'.pdf',
                'nom_fichier' => 'support-'.$number.'.pdf',
                'type_mime' => 'application/pdf',
            ]);
            $support->niveaux()->attach($niveau);
        }

        $this->actingAs($student)
            ->get(route('etudiant.supports'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Student/Supports')
                ->has('supportsCours.data', 12)
                ->where('supportsCours.per_page', 12)
                ->where('supportsCours.total', 13));

        $this->actingAs($student)
            ->get(route('etudiant.supports', ['page' => 2]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('supportsCours.data', 1)
                ->where('supportsCours.current_page', 2));
    }

    public function test_teacher_upload_accepts_300_mb_and_rejects_larger_files(): void
    {
        Storage::fake('local');
        $teacher = $this->createUser('teacher', 'teacher-large-support@test.ci');
        $filiere = $this->createFiliere('Informatique', 'INFO-LARGE-SUPPORT');
        $level = $this->createNiveau($filiere, 'Licence 1', 'L1');

        $this->actingAs($teacher)
            ->from(route('enseignant.supports-cours.index'))
            ->post(route('enseignant.supports-cours.store'), [
                'titre' => 'Document de 300 Mo',
                'fichier' => UploadedFile::fake()->create('cours.pdf', 307200, 'application/pdf'),
                'niveaux_ids' => [$level->id],
            ])
            ->assertRedirect(route('enseignant.supports-cours.index'));

        $this->assertDatabaseCount('support_cours', 1);

        $this->post(route('enseignant.supports-cours.store'), [
            'titre' => 'Document trop volumineux',
            'fichier' => UploadedFile::fake()->create('cours-trop-grand.pdf', 307201, 'application/pdf'),
            'niveaux_ids' => [$level->id],
        ])->assertSessionHasErrors('fichier');

        $this->assertDatabaseCount('support_cours', 1);
    }

    public function test_only_a_teacher_can_send_support_and_students_cannot_download_another_classes_file(): void
    {
        Storage::fake('local');
        $admin = $this->createUser('admin', 'admin-support@test.ci');
        $teacher = $this->createUser('teacher', 'owner-support@test.ci');
        $filiere = $this->createFiliere('Informatique', 'INFO-OWNER-SUPPORT');
        $targetLevel = $this->createNiveau($filiere, 'Licence 1', 'L1');
        $otherLevel = $this->createNiveau($filiere, 'Licence 2', 'L2');
        $otherStudent = $this->createStudent('UIST-SUPPORT-UNAUTHORIZED', $filiere, $otherLevel);
        $support = SupportCours::create([
            'enseignant_id' => $teacher->id,
            'titre' => 'Document privé',
            'chemin_fichier' => 'supports-cours/private.pdf',
            'nom_fichier' => 'private.pdf',
            'type_mime' => 'application/pdf',
        ]);
        $support->niveaux()->attach($targetLevel);
        Storage::disk('local')->put($support->chemin_fichier, '%PDF-1.4 document');

        $this->actingAs($admin)
            ->get(route('enseignant.supports-cours.index'))
            ->assertForbidden();

        $this->actingAs($otherStudent)
            ->get(route('supports-cours.download', $support))
            ->assertForbidden();
    }

    public function test_teacher_cannot_upload_unsupported_files_or_target_an_unknown_class(): void
    {
        Storage::fake('local');
        $teacher = $this->createUser('teacher', 'teacher-invalid-support@test.ci');
        $filiere = $this->createFiliere('Sciences', 'SCI-INVALID-SUPPORT');
        $level = $this->createNiveau($filiere, 'Licence 1', 'L1');

        $this->actingAs($teacher)
            ->from(route('enseignant.supports-cours.index'))
            ->post(route('enseignant.supports-cours.store'), [
                'titre' => 'Document non autorisé',
                'fichier' => UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream'),
                'niveaux_ids' => [$level->id, 999999],
            ])
            ->assertSessionHasErrors(['fichier', 'niveaux_ids.1']);

        $this->assertDatabaseCount('support_cours', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_support_upload_requires_at_least_one_class_and_login(): void
    {
        Storage::fake('local');
        $teacher = $this->createUser('teacher', 'teacher-empty-support@test.ci');

        $this->actingAs($teacher)
            ->from(route('enseignant.supports-cours.index'))
            ->post(route('enseignant.supports-cours.store'), [
                'titre' => 'Sans classe',
                'fichier' => UploadedFile::fake()->create('cours.pdf', 10, 'application/pdf'),
                'niveaux_ids' => [],
            ])
            ->assertSessionHasErrors('niveaux_ids');

        auth()->logout();
        $this->get(route('enseignant.supports-cours.index'))
            ->assertRedirect(route('login'));
    }

    private function createUser(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role).' Test',
            'email' => $email,
            'password' => 'MotDePasseSolide!',
            'role' => $role,
        ]);
    }

    private function createFiliere(string $name, string $code): Filiere
    {
        return Filiere::create(['nom' => $name, 'code' => $code]);
    }

    private function createNiveau(Filiere $filiere, string $name, string $code): Niveau
    {
        return Niveau::create([
            'filiere_id' => $filiere->id,
            'nom' => $name,
            'code' => $code,
            'credits_requis' => 60,
        ]);
    }

    private function createStudent(string $matricule, Filiere $filiere, Niveau $niveau): User
    {
        return User::create([
            'name' => 'Étudiant de test',
            'role' => 'student',
            'matricule' => $matricule,
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
            'password' => 'MotDePasseEtudiant!',
        ]);
    }
}
