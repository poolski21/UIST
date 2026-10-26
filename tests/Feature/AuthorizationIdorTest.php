<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Filiere;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\ResultatAnnuel;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class AuthorizationIdorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guest_cannot_download_an_annual_result(): void
    {
        $student = $this->createStudent();
        $resultat = $this->createResult($student);

        $this->get(route('releve.pdf', $resultat))
            ->assertRedirect(route('login'));
    }

    public function test_student_can_download_their_published_annual_result(): void
    {
        $student = $this->createStudent();
        $resultat = $this->createResult($student, now());

        $this->actingAs($student)
            ->get(route('releve.pdf', $resultat))
            ->assertOk()
            ->assertDownload();
    }

    public function test_student_cannot_download_another_students_annual_result(): void
    {
        $owner = $this->createStudent('owner');
        $resultat = $this->createResult($owner, now());
        $otherStudent = $this->createStudent('other');

        $this->actingAs($otherStudent)
            ->get(route('releve.pdf', $resultat))
            ->assertForbidden();
    }

    public function test_student_cannot_download_their_unpublished_annual_result(): void
    {
        $student = $this->createStudent();
        $resultat = $this->createResult($student);

        $this->actingAs($student)
            ->get(route('releve.pdf', $resultat))
            ->assertForbidden();
    }

    public function test_admin_can_download_a_students_annual_result(): void
    {
        $student = $this->createStudent();
        $resultat = $this->createResult($student);
        $admin = User::create([
            'name' => 'Administrateur',
            'email' => 'admin@test.ci',
            'password' => 'MotDePasseSolide!',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('releve.pdf', $resultat))
            ->assertOk()
            ->assertDownload();
    }

    public function test_pdf_receives_only_published_notes_for_the_result_owner_and_year(): void
    {
        $student = $this->createStudent();
        $resultat = $this->createResult($student, now());
        $otherStudent = $this->createStudent('other');
        $otherStudent->update([
            'filiere_id' => $student->filiere_id,
            'niveau_id' => $student->niveau_id,
        ]);
        $year = AnneeAcademique::findOrFail($resultat->annee_academique_id);
        $otherYear = AnneeAcademique::create([
            'libelle' => '2027-2028',
            'date_debut' => '2027-09-01',
            'date_fin' => '2028-07-31',
        ]);
        $subject = Matiere::create([
            'filiere_id' => $student->filiere_id,
            'niveau_id' => $student->niveau_id,
            'nom' => 'Algorithmique',
            'code' => 'ALG-1',
            'credits' => 3,
            'coefficient' => 1,
        ]);
        $draftSubject = Matiere::create([
            'filiere_id' => $student->filiere_id,
            'niveau_id' => $student->niveau_id,
            'nom' => 'Réseaux',
            'code' => 'RES-1',
            'credits' => 3,
            'coefficient' => 1,
        ]);
        $visibleNote = $this->createNote($student, $subject, $year, 'publie');
        $this->createNote($student, $draftSubject, $year, 'brouillon');
        $this->createNote($student, $subject, $otherYear, 'publie');
        $this->createNote($otherStudent, $subject, $year, 'publie');

        $pdf = Mockery::mock(DomPdf::class);
        $pdf->shouldReceive('download')
            ->once()
            ->andReturn(response('mock PDF', 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="releve.pdf"',
            ]));

        Pdf::shouldReceive('loadView')
            ->once()
            ->with('pdf.releve', Mockery::on(fn (array $data): bool => $data['resultat']->is($resultat)
                && $data['notes']->count() === 1
                && (int) $data['notes']->sole()->getKey() === (int) $visibleNote->getKey()))
            ->andReturn($pdf);

        $this->actingAs($student)
            ->get(route('releve.pdf', $resultat))
            ->assertOk()
            ->assertDownload();
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $owner = $this->createStudent('notification-owner');
        $attacker = $this->createStudent('notification-attacker');
        $notification = $owner->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\PublicationNotification',
            'data' => ['url' => '/espace'],
        ]);

        $this->actingAs($attacker)
            ->post(route('notifications.read', $notification))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_teacher_cannot_submit_a_note_for_a_subject_assigned_to_another_teacher(): void
    {
        $teacher = User::create([
            'name' => 'Enseignant connecté',
            'email' => 'teacher@test.ci',
            'password' => 'MotDePasseSolide!',
            'role' => 'teacher',
        ]);
        $otherTeacher = User::create([
            'name' => 'Autre enseignant',
            'email' => 'other-teacher@test.ci',
            'password' => 'MotDePasseSolide!',
            'role' => 'teacher',
        ]);
        $student = $this->createStudent();
        [$year] = $this->createAcademicContext($student);
        $subject = Matiere::create([
            'filiere_id' => $student->filiere_id,
            'niveau_id' => $student->niveau_id,
            'enseignant_id' => $otherTeacher->id,
            'nom' => 'Algorithmique',
            'code' => 'ALG-1',
            'credits' => 3,
            'coefficient' => 1,
        ]);
        Inscription::create([
            'etudiant_id' => $student->id,
            'niveau_id' => $student->niveau_id,
            'annee_academique_id' => $year->id,
        ]);

        $this->actingAs($teacher)
            ->post(route('enseignant.notes.store'), [
                'matiere_id' => $subject->id,
                'etudiant_id' => $student->id,
                'annee_academique_id' => $year->id,
                'notes_td' => [12, 14],
                'note_examen' => 15,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('notes', 0);
    }

    public function test_teacher_cannot_submit_a_note_for_a_student_not_enrolled_in_the_academic_year(): void
    {
        $teacher = User::create([
            'name' => 'Enseignant',
            'email' => 'teacher@test.ci',
            'password' => 'MotDePasseSolide!',
            'role' => 'teacher',
        ]);
        $student = $this->createStudent();
        [$year] = $this->createAcademicContext($student);
        $subject = Matiere::create([
            'filiere_id' => $student->filiere_id,
            'niveau_id' => $student->niveau_id,
            'enseignant_id' => $teacher->id,
            'nom' => 'Algorithmique',
            'code' => 'ALG-1',
            'credits' => 3,
            'coefficient' => 1,
        ]);

        $this->actingAs($teacher)
            ->from(route('enseignant.notes'))
            ->post(route('enseignant.notes.store'), [
                'matiere_id' => $subject->id,
                'etudiant_id' => $student->id,
                'annee_academique_id' => $year->id,
                'notes_td' => [12, 14],
                'note_examen' => 15,
            ])
            ->assertSessionHasErrors('etudiant_id');

        $this->assertDatabaseCount('notes', 0);
    }

    private function createStudent(string $suffix = 'student'): User
    {
        return User::create([
            'name' => 'Étudiant '.$suffix,
            'email' => $suffix.'@test.ci',
            'password' => 'MotDePasseSolide!',
            'role' => 'student',
            'matricule' => strtoupper($suffix),
        ]);
    }

    private function createResult(User $student, mixed $publishedAt = null): ResultatAnnuel
    {
        [$year] = $this->createAcademicContext($student);

        return ResultatAnnuel::create([
            'etudiant_id' => $student->id,
            'annee_academique_id' => $year->id,
            'moyenne_generale' => 12,
            'credits_obtenus' => 60,
            'decision_auto' => 'admis',
            'publie_le' => $publishedAt,
        ]);
    }

    private function createAcademicContext(User $student): array
    {
        $filiere = Filiere::create(['nom' => 'Informatique', 'code' => 'INFO']);
        $niveau = Niveau::create([
            'filiere_id' => $filiere->id,
            'nom' => 'Licence 1',
            'code' => 'L1',
            'credits_requis' => 60,
        ]);
        $student->update([
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
        ]);
        $year = AnneeAcademique::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
        ]);

        return [$year, $filiere, $niveau];
    }

    private function createNote(User $student, Matiere $subject, AnneeAcademique $year, string $status): Note
    {
        return Note::create([
            'etudiant_id' => $student->id,
            'matiere_id' => $subject->id,
            'annee_academique_id' => $year->id,
            'note' => 15,
            'statut' => $status,
        ]);
    }
}
