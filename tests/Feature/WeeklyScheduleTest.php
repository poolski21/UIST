<?php

namespace Tests\Feature;

use App\Models\EmploiDuTemps;
use App\Models\Filiere;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_student_can_navigate_their_published_monday_to_saturday_schedule(): void
    {
        $student = $this->createStudent();
        $teacher = $this->createTeacher('Professeur de la matière');
        $subject = $this->createSubject($student->filiere_id, $student->niveau_id, $teacher, 'INFO-1');
        $course = $this->createCourse($subject, [
            'enseignant_id' => null,
            'date_cours' => '2026-10-31',
            'salle' => null,
        ]);
        $unassignedSubject = Matiere::create([
            'filiere_id' => $student->filiere_id,
            'niveau_id' => $student->niveau_id,
            'enseignant_id' => null,
            'nom' => 'Matière sans enseignant',
            'code' => 'INFO-2',
            'credits' => 3,
            'coefficient' => 1,
        ]);
        $unassignedCourse = $this->createCourse($unassignedSubject, [
            'enseignant_id' => null,
            'date_cours' => '2026-10-31',
            'heure_debut' => '10:00',
            'heure_fin' => '11:00',
        ]);
        $this->createCourse($subject, [
            'date_cours' => '2026-11-01',
            'heure_debut' => '10:00',
        ]);
        $this->createCourse($subject, [
            'date_cours' => '2026-10-30',
            'statut' => 'brouillon',
            'heure_debut' => '11:00',
        ]);

        $this->assertDatabaseHas('emplois_du_temps', [
            'id' => $course->id,
            'filiere_id' => $student->filiere_id,
            'niveau_id' => $student->niveau_id,
            'statut' => 'publie',
        ]);

        $this->actingAs($student)
            ->get('/etudiant?week_start=2026-10-26')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Student/Dashboard')
                ->where('weekStart', '2026-10-26')
                ->has('emploiDuTemps', 2)
                ->where('emploiDuTemps.0.id', $course->id)
                ->where('emploiDuTemps.0.subject_color_index', 0)
                ->where('emploiDuTemps.0.matiere', 'Matière INFO-1')
                ->where('emploiDuTemps.0.enseignant', 'Professeur de la matière')
                ->where('emploiDuTemps.0.date', '2026-10-31')
                ->where('emploiDuTemps.1.id', $unassignedCourse->id)
                ->where('emploiDuTemps.1.subject_color_index', 1)
                ->where('emploiDuTemps.1.enseignant', 'Enseignant à confirmer'));
    }

    public function test_guest_is_redirected_from_the_teacher_program(): void
    {
        $this->get(route('enseignant.programme'))
            ->assertRedirect(route('login'));
    }

    public function test_student_is_forbidden_from_the_teacher_program(): void
    {
        $student = $this->createStudent();

        $this->actingAs($student)
            ->get(route('enseignant.programme'))
            ->assertForbidden();
    }

    public function test_teacher_program_includes_published_direct_and_fallback_assignments_but_not_drafts(): void
    {
        $teacher = $this->createTeacher('Enseignant connecté');
        $otherTeacher = $this->createTeacher('Autre enseignant');
        $filiere = Filiere::create(['nom' => 'Informatique', 'code' => 'INFO']);
        $niveau = Niveau::create([
            'filiere_id' => $filiere->id,
            'nom' => 'Licence 1',
            'code' => 'L1',
            'credits_requis' => 60,
        ]);
        $teacherSubject = $this->createSubject($filiere->id, $niveau->id, $teacher, 'TEACHER-1');
        $otherSubject = $this->createSubject($filiere->id, $niveau->id, $otherTeacher, 'OTHER-1');
        $directAssignment = $this->createCourse($otherSubject, [
            'enseignant_id' => $teacher->id,
            'heure_debut' => '08:00',
            'salle' => 'A1',
        ]);
        $subjectFallback = $this->createCourse($teacherSubject, [
            'enseignant_id' => null,
            'heure_debut' => '09:00',
            'heure_fin' => '10:00',
            'salle' => 'B2',
        ]);
        $this->createCourse($teacherSubject, [
            'enseignant_id' => $otherTeacher->id,
            'heure_debut' => '10:00',
        ]);
        $this->createCourse($otherSubject, [
            'enseignant_id' => null,
            'heure_debut' => '11:00',
        ]);
        $this->createCourse($teacherSubject, [
            'statut' => 'brouillon',
            'heure_debut' => '12:00',
        ]);

        $this->actingAs($teacher)
            ->get(route('enseignant.programme', ['week_start' => '2026-10-26']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Teacher/Programme')
                ->where('weekStart', '2026-10-26')
                ->has('emploiDuTemps', 2)
                ->where('emploiDuTemps.0.id', $directAssignment->id)
                ->where('emploiDuTemps.0.subject_color_index', 1)
                ->where('emploiDuTemps.0.matiere', 'Matière OTHER-1')
                ->where('emploiDuTemps.0.filiere', 'Informatique')
                ->where('emploiDuTemps.0.niveau', 'Licence 1')
                ->where('emploiDuTemps.0.enseignant', 'Enseignant connecté')
                ->where('emploiDuTemps.1.id', $subjectFallback->id)
                ->where('emploiDuTemps.1.subject_color_index', 0)
                ->where('emploiDuTemps.1.enseignant', 'Enseignant connecté'));
    }

    public function test_admin_created_published_session_appears_only_for_its_assigned_teacher_with_class_context(): void
    {
        $admin = User::create([
            'name' => 'Administrateur',
            'email' => 'admin-schedule@test.ci',
            'password' => 'MotDePasseSolide!',
            'role' => 'admin',
        ]);
        $teacher = $this->createTeacher('Enseignant affecté');
        $otherTeacher = $this->createTeacher('Autre enseignant');
        $filiere = Filiere::create(['nom' => 'Informatique', 'code' => 'INFO']);
        $niveau = Niveau::create([
            'filiere_id' => $filiere->id,
            'nom' => 'Licence 2',
            'code' => 'L2',
            'credits_requis' => 60,
        ]);
        $subject = $this->createSubject($filiere->id, $niveau->id, $teacher, 'INFO-2');

        $this->actingAs($admin)
            ->post(route('admin.emplois.store'), [
                'filiere_id' => $filiere->id,
                'niveau_id' => $niveau->id,
                'week_start' => '2026-10-19',
                'jour' => 1,
                'matiere_id' => $subject->id,
                'enseignant_id' => $teacher->id,
                'heure_debut' => '08:00',
                'heure_fin' => '09:30',
                'salle' => 'A2',
                'publier' => true,
            ])
            ->assertRedirect();

        $course = EmploiDuTemps::query()->sole();

        $this->actingAs($teacher)
            ->get(route('enseignant.programme', ['week_start' => '2026-10-19']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Teacher/Programme')
                ->has('emploiDuTemps', 1)
                ->where('emploiDuTemps.0.id', $course->id)
                ->where('emploiDuTemps.0.filiere', 'Informatique')
                ->where('emploiDuTemps.0.niveau', 'Licence 2')
                ->where('emploiDuTemps.0.matiere', 'Matière INFO-2')
                ->where('emploiDuTemps.0.heure_debut', '08:00')
                ->where('emploiDuTemps.0.salle', 'A2'));

        $this->actingAs($otherTeacher)
            ->get(route('enseignant.programme', ['week_start' => '2026-10-19']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Teacher/Programme')
                ->has('emploiDuTemps', 0));
    }

    public function test_teacher_program_keeps_common_sessions_with_the_same_time_visible(): void
    {
        $teacher = $this->createTeacher('Enseignant de cours commun');
        $firstFiliere = Filiere::create(['nom' => 'Informatique', 'code' => 'INFO']);
        $firstNiveau = Niveau::create([
            'filiere_id' => $firstFiliere->id,
            'nom' => 'Licence 1',
            'code' => 'L1',
            'credits_requis' => 60,
        ]);
        $secondFiliere = Filiere::create(['nom' => 'Gestion', 'code' => 'GEST']);
        $secondNiveau = Niveau::create([
            'filiere_id' => $secondFiliere->id,
            'nom' => 'Licence 2',
            'code' => 'L2',
            'credits_requis' => 60,
        ]);
        $firstSubject = $this->createSubject($firstFiliere->id, $firstNiveau->id, $teacher, 'COMMON-1');
        $secondSubject = $this->createSubject($secondFiliere->id, $secondNiveau->id, $teacher, 'COMMON-2');
        $firstSubject->update(['nom' => 'Communication']);
        $secondSubject->update(['nom' => 'Communication']);
        $firstCourse = $this->createCourse($firstSubject, ['date_cours' => '2026-10-19', 'heure_debut' => '10:00', 'heure_fin' => '11:00']);
        $secondCourse = $this->createCourse($secondSubject, ['date_cours' => '2026-10-19', 'heure_debut' => '10:00', 'heure_fin' => '11:00']);

        $this->actingAs($teacher)
            ->get(route('enseignant.programme', ['week_start' => '2026-10-19']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Teacher/Programme')
                ->has('emploiDuTemps', 2)
                ->where('emploiDuTemps.0.filiere', 'Informatique')
                ->where('emploiDuTemps.0.niveau', 'Licence 1')
                ->where('emploiDuTemps.1.filiere', 'Gestion')
                ->where('emploiDuTemps.1.niveau', 'Licence 2')
                ->where('emploiDuTemps.0.heure_debut', '10:00')
                ->where('emploiDuTemps.1.heure_debut', '10:00'));
    }

    private function createStudent(): User
    {
        $filiere = Filiere::create(['nom' => 'Informatique', 'code' => 'INFO']);
        $niveau = Niveau::create([
            'filiere_id' => $filiere->id,
            'nom' => 'Licence 1',
            'code' => 'L1',
            'credits_requis' => 60,
        ]);

        return User::create([
            'name' => 'Étudiant de test',
            'email' => 'student@test.ci',
            'password' => 'MotDePasseSolide!',
            'role' => 'student',
            'matricule' => 'ETU-TEST',
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
        ]);
    }

    private function createTeacher(string $name): User
    {
        static $teacherNumber = 0;
        $teacherNumber++;

        return User::create([
            'name' => $name,
            'email' => 'teacher'.$teacherNumber.'@test.ci',
            'password' => 'MotDePasseSolide!',
            'role' => 'teacher',
        ]);
    }

    private function createSubject(int $filiereId, int $niveauId, User $teacher, string $code): Matiere
    {
        return Matiere::create([
            'filiere_id' => $filiereId,
            'niveau_id' => $niveauId,
            'enseignant_id' => $teacher->id,
            'nom' => 'Matière '.$code,
            'code' => $code,
            'credits' => 3,
            'coefficient' => 1,
        ]);
    }

    private function createCourse(Matiere $subject, array $overrides = []): EmploiDuTemps
    {
        return EmploiDuTemps::create([
            'titre' => $subject->nom,
            'filiere_id' => $subject->filiere_id,
            'niveau_id' => $subject->niveau_id,
            'matiere_id' => $subject->id,
            'enseignant_id' => $subject->enseignant_id,
            'date_cours' => '2026-10-28',
            'heure_debut' => '08:00',
            'heure_fin' => '09:00',
            'salle' => 'A1',
            'statut' => 'publie',
            ...$overrides,
        ]);
    }
}
