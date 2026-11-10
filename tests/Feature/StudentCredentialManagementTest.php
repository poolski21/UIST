<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentCredentialManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_creates_a_student_with_date_of_birth_and_without_a_password(): void
    {
        $admin = $this->createAdmin();
        [$filiere, $niveau, $annee] = $this->createAcademicRecords();

        $this->actingAs($admin)
            ->from(route('admin.manage'))
            ->post(route('admin.etudiants.store'), [
                'name' => 'Étudiant créé',
                'matricule' => 'UIST-CREATED-0001',
                'date_naissance' => '2004-03-15',
                'filiere_id' => $filiere->id,
                'niveau_id' => $niveau->id,
                'annee_academique_id' => $annee->id,
            ])
            ->assertRedirect(route('admin.manage'));

        $student = User::query()->where('matricule', 'UIST-CREATED-0001')->firstOrFail();
        $this->assertSame('2004-03-15', $student->date_naissance->toDateString());
        $this->assertNull($student->password);
        $this->assertFalse($student->password_change_required);
        $this->assertDatabaseMissing('student_password_changes', [
            'target_student_id' => $student->id,
        ]);
    }

    public function test_admin_cannot_create_a_student_without_date_of_birth(): void
    {
        $admin = $this->createAdmin();
        [$filiere, $niveau, $annee] = $this->createAcademicRecords();

        $this->actingAs($admin)
            ->post(route('admin.etudiants.store'), [
                'name' => 'Étudiant sans date',
                'matricule' => 'UIST-NO-DOB-0001',
                'filiere_id' => $filiere->id,
                'niveau_id' => $niveau->id,
                'annee_academique_id' => $annee->id,
            ])
            ->assertSessionHasErrors('date_naissance');

        $this->assertDatabaseMissing('users', ['matricule' => 'UIST-NO-DOB-0001']);
    }

    public function test_admin_cannot_reset_an_unused_student_password(): void
    {
        $admin = $this->createAdmin();
        $student = User::create([
            'name' => 'Étudiant',
            'role' => 'student',
            'matricule' => 'UIST-NO-PASSWORD-RESET',
            'date_naissance' => '2004-03-15',
            'password' => null,
        ]);

        $this->actingAs($admin)
            ->post('/admin/securite/mots-de-passe-etudiants/'.$student->id)
            ->assertNotFound();

        $this->assertNull($student->fresh()->password);
        $this->assertFalse($student->fresh()->password_change_required);
    }

    /**
     * @return array{Filiere, Niveau, AnneeAcademique}
     */
    private function createAcademicRecords(): array
    {
        $filiere = Filiere::create(['nom' => 'Informatique', 'code' => 'INFO-CREDENTIALS']);
        $niveau = Niveau::create([
            'filiere_id' => $filiere->id,
            'nom' => 'Licence 1',
            'code' => 'L1-CREDENTIALS',
            'credits_requis' => 60,
        ]);
        $annee = AnneeAcademique::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'active' => true,
        ]);

        return [$filiere, $niveau, $annee];
    }

    private function createAdmin(): User
    {
        return User::create([
            'name' => 'Administrateur',
            'email' => 'student-credentials-admin@test.ci',
            'password' => 'MotDePasseAdminSolide!',
            'role' => 'admin',
        ]);
    }
}
