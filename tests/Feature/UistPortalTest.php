<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Filiere;
use App\Models\Inscription;
use App\Models\JournalModification;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\User;
use App\Services\ValidationAnneeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UistPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_student_can_sign_in_with_permanent_matricule_only(): void
    {
        $student = User::create([
            'name' => 'Étudiante Démo',
            'role' => 'student',
            'matricule' => 'UIST-TEST-0001',
        ]);

        $response = $this->post('/connexion/etudiant', ['matricule' => $student->matricule]);

        $response->assertRedirect(route('espace'));
        $this->assertAuthenticatedAs($student);
        $this->assertDatabaseHas('connexions_log', [
            'matricule' => $student->matricule,
            'type_connexion' => 'matricule',
            'reussie' => true,
        ]);
    }

    public function test_staff_can_sign_in_with_email_and_password(): void
    {
        $teacher = User::create([
            'name' => 'Enseignant Démo',
            'email' => 'teacher-login@test.ci',
            'password' => 'MotDePasseTresSolide!',
            'role' => 'teacher',
        ]);

        $this->post('/connexion/personnel', [
            'email' => $teacher->email,
            'password' => 'MotDePasseTresSolide!',
        ])->assertRedirect(route('espace'));

        $this->assertAuthenticatedAs($teacher);
    }

    public function test_student_space_entry_redirects_to_login_then_returns_to_the_student_dashboard(): void
    {
        $student = User::create([
            'name' => 'Étudiant Espace',
            'role' => 'student',
            'matricule' => 'UIST-SPACE-0001',
        ]);

        $this->get('/espace')->assertRedirect(route('login'));

        $this->post('/connexion/etudiant', ['matricule' => $student->matricule])
            ->assertRedirect('/espace');

        $this->get('/espace')->assertRedirect('/etudiant');
        $this->get('/etudiant')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Student/Dashboard'));
    }

    public function test_public_homepage_exposes_dynamic_course_category_counts(): void
    {
        $this->seed();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->has('cours', 101)
                ->has('categories', 9)
                ->where('categories.0.cours_count', 15));
    }

    public function test_locale_can_be_changed_and_is_kept_in_the_session(): void
    {
        $this->from('/')->post('/langue', ['locale' => 'en'])->assertRedirect('/');

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('locale', 'en'));

        $this->from('/')->post('/langue', ['locale' => 'fr'])->assertRedirect('/');

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('locale', 'fr'));
    }

    public function test_locale_change_rejects_unsupported_languages(): void
    {
        $this->from('/')->post('/langue', ['locale' => 'es'])
            ->assertSessionHasErrors('locale');
    }

    public function test_invalid_student_logins_are_rate_limited_and_logged(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/connexion/etudiant', ['matricule' => 'UNKNOWN-001'])
                ->assertSessionHasErrors('matricule');
        }

        $this->post('/connexion/etudiant', ['matricule' => 'UNKNOWN-001'])
            ->assertSessionHasErrors('matricule');

        $this->assertDatabaseCount('connexions_log', 6);
    }

    public function test_role_permissions_and_student_notes_are_scoped(): void
    {
        $filiere = Filiere::create(['nom' => 'Informatique', 'code' => 'INFO']);
        $niveau = Niveau::create(['filiere_id' => $filiere->id, 'nom' => 'Licence 1', 'code' => 'L1', 'credits_requis' => 60]);
        $teacher = User::create(['name' => 'Enseignant', 'email' => 'teacher@test.ci', 'password' => 'password-password', 'role' => 'teacher']);
        $student = User::create(['name' => 'Étudiant A', 'role' => 'student', 'matricule' => 'ETU-A', 'filiere_id' => $filiere->id, 'niveau_id' => $niveau->id]);
        $otherStudent = User::create(['name' => 'Étudiant B', 'role' => 'student', 'matricule' => 'ETU-B', 'filiere_id' => $filiere->id, 'niveau_id' => $niveau->id]);
        $year = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31']);
        $subject = Matiere::create(['filiere_id' => $filiere->id, 'niveau_id' => $niveau->id, 'enseignant_id' => $teacher->id, 'nom' => 'Algorithmique', 'code' => 'ALG-1', 'credits' => 60, 'coefficient' => 1]);
        Inscription::create(['etudiant_id' => $student->id, 'niveau_id' => $niveau->id, 'annee_academique_id' => $year->id]);
        Note::create(['etudiant_id' => $student->id, 'matiere_id' => $subject->id, 'annee_academique_id' => $year->id, 'note' => 15, 'statut' => 'publie']);
        Note::create(['etudiant_id' => $otherStudent->id, 'matiere_id' => $subject->id, 'annee_academique_id' => $year->id, 'note' => 18, 'statut' => 'publie']);

        $this->actingAs($student)->get('/etudiant')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Student/Dashboard')
                ->has('notes', 1)
                ->where('notes.0.note', 15));
        $this->actingAs($student)->get('/admin')->assertForbidden();
        $this->actingAs($teacher)->get('/admin')->assertForbidden();

        $anotherSubject = Matiere::create([
            'filiere_id' => $filiere->id, 'niveau_id' => $niveau->id, 'nom' => 'Réseaux',
            'code' => 'NET-1', 'credits' => 3, 'coefficient' => 1,
        ]);
        $this->actingAs($teacher)->post('/enseignant/notes', [
            'matiere_id' => $anotherSubject->id,
            'etudiant_id' => $student->id,
            'annee_academique_id' => $year->id,
            'note' => 10,
        ])->assertForbidden();
    }

    public function test_subject_average_uses_all_tutorial_grades_and_final_exam(): void
    {
        $filiere = Filiere::create(['nom' => 'Informatique', 'code' => 'INFO']);
        $niveau = Niveau::create(['filiere_id' => $filiere->id, 'nom' => 'Licence 1', 'code' => 'L1', 'credits_requis' => 60]);
        $teacher = User::create(['name' => 'Enseignant', 'email' => 'teacher-grades@test.ci', 'password' => 'password-password', 'role' => 'teacher']);
        $student = User::create(['name' => 'Étudiant', 'role' => 'student', 'matricule' => 'ETU-GRADE', 'filiere_id' => $filiere->id, 'niveau_id' => $niveau->id]);
        $year = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31']);
        Inscription::create(['etudiant_id' => $student->id, 'niveau_id' => $niveau->id, 'annee_academique_id' => $year->id]);
        $subject = Matiere::create([
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
            'enseignant_id' => $teacher->id,
            'nom' => 'Algorithmique',
            'code' => 'ALG-GRADE',
            'credits' => 60,
            'coefficient' => 1,
        ]);

        $this->actingAs($teacher)->post('/enseignant/notes', [
            'matiere_id' => $subject->id,
            'etudiant_id' => $student->id,
            'annee_academique_id' => $year->id,
            'notes_td' => [10, 14, 16],
            'note_examen' => 8,
        ])->assertRedirect();

        $this->assertDatabaseHas('notes', [
            'etudiant_id' => $student->id,
            'matiere_id' => $subject->id,
            'note' => 10.13,
            'note_examen' => 8,
            'statut' => 'brouillon',
        ]);
        $note = Note::firstOrFail();
        $this->assertSame([10, 14, 16], $note->notes_td);

        $admin = User::create(['name' => 'Admin', 'email' => 'admin-grades@test.ci', 'password' => 'password-password', 'role' => 'admin']);
        $this->actingAs($admin)->post('/admin/notes/'.$subject->id.'/'.$year->id.'/publier')->assertRedirect();

        $this->actingAs($student)->get('/etudiant')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('notes', 1)
                ->where('notes.0.note', 10.13)
                ->where('notes.0.notes_td', [10, 14, 16])
                ->where('notes.0.note_examen', 8)
                ->where('notes.0.validee', true));
    }

    public function test_annual_decision_uses_weighted_average_and_required_credits(): void
    {
        $filiere = Filiere::create(['nom' => 'Informatique', 'code' => 'INFO']);
        $niveau = Niveau::create(['filiere_id' => $filiere->id, 'nom' => 'Master 1', 'code' => 'M1', 'credits_requis' => 60]);
        $student = User::create(['name' => 'Étudiant Test', 'role' => 'student', 'matricule' => 'ETU-CALC', 'filiere_id' => $filiere->id, 'niveau_id' => $niveau->id]);
        $year = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31']);
        Inscription::create(['etudiant_id' => $student->id, 'niveau_id' => $niveau->id, 'annee_academique_id' => $year->id]);
        $subject1 = Matiere::create(['filiere_id' => $filiere->id, 'niveau_id' => $niveau->id, 'nom' => 'Maths', 'code' => 'MAT-1', 'credits' => 30, 'coefficient' => 2]);
        $subject2 = Matiere::create(['filiere_id' => $filiere->id, 'niveau_id' => $niveau->id, 'nom' => 'Réseaux', 'code' => 'RES-1', 'credits' => 30, 'coefficient' => 1]);
        $first = Note::create(['etudiant_id' => $student->id, 'matiere_id' => $subject1->id, 'annee_academique_id' => $year->id, 'note' => 12, 'statut' => 'publie']);
        $second = Note::create(['etudiant_id' => $student->id, 'matiere_id' => $subject2->id, 'annee_academique_id' => $year->id, 'note' => 6, 'statut' => 'publie']);

        $result = app(ValidationAnneeService::class)->calculer($student, $year);
        $this->assertSame('ajourne', $result->decision_auto);
        $this->assertSame('10.00', $result->moyenne_generale);
        $this->assertSame(30, $result->credits_obtenus);

        $second->update(['note' => 10]);
        $result = app(ValidationAnneeService::class)->calculer($student, $year);
        $this->assertSame('admis', $result->decision_auto);
        $this->assertSame('11.33', $result->moyenne_generale);
        $this->assertSame(60, $result->credits_obtenus);

        $admin = User::create(['name' => 'Admin', 'email' => 'admin@test.ci', 'password' => 'password-password', 'role' => 'admin']);
        $this->actingAs($admin)->put('/admin/resultats/'.$result->id.'/decision', [
            'decision_finale' => 'ajourne',
            'motif' => 'Décision du jury après délibération.',
        ])->assertRedirect();

        $this->assertDatabaseHas('resultats_annuels', [
            'id' => $result->id,
            'decision_auto' => 'admis',
            'decision_finale' => 'ajourne',
            'motif' => 'Décision du jury après délibération.',
        ]);
        $this->assertSame(1, JournalModification::count());

        $this->actingAs($student)->get('/resultats/'.$result->id.'/releve.pdf')->assertForbidden();
        $this->actingAs($admin)->post('/admin/resultats/'.$result->id.'/publier')->assertRedirect();
        $this->actingAs($student)->get('/resultats/'.$result->id.'/releve.pdf')->assertDownload();
    }
}
