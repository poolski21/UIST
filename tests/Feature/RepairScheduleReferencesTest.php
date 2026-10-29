<?php

namespace Tests\Feature;

use App\Models\EmploiDuTemps;
use App\Models\Filiere;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairScheduleReferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_reference_repair_applies_teacher_choices_and_preserves_orphan_sessions(): void
    {
        $data = $this->createRepairData();

        $this->artisan('uist2its:repair-schedule-references', [
            '--teacher-1' => 'null',
            '--teacher-2' => (string) $data['teacherTwo']->id,
            '--orphan-1' => 'keep',
            '--orphan-2' => 'keep',
        ])
            ->expectsOutputToContain('Matière #1')
            ->expectsOutputToContain('Séance orpheline #1 : conservée en l’état.')
            ->expectsConfirmation('Appliquer exactement les modifications affichées ?', 'yes')
            ->assertExitCode(0);

        $this->assertDatabaseHas('matieres', [
            'id' => $data['subjectOne']->id,
            'enseignant_id' => null,
        ]);
        $this->assertDatabaseHas('matieres', [
            'id' => $data['subjectTwo']->id,
            'enseignant_id' => $data['teacherTwo']->id,
        ]);
        $this->assertDatabaseHas('emplois_du_temps', [
            'id' => $data['sessionOne']->id,
            'matiere_id' => null,
        ]);
        $this->assertDatabaseHas('emplois_du_temps', [
            'id' => $data['sessionTwo']->id,
            'matiere_id' => null,
        ]);
    }

    public function test_orphan_session_is_not_deleted_when_the_preview_is_not_confirmed(): void
    {
        $data = $this->createRepairData();

        $this->artisan('uist2its:repair-schedule-references', [
            '--teacher-1' => (string) $data['teacherOne']->id,
            '--teacher-2' => (string) $data['teacherTwo']->id,
            '--orphan-1' => 'delete',
            '--orphan-2' => 'keep',
        ])
            ->expectsOutputToContain('SUPPRESSION définitive : séance #1')
            ->expectsConfirmation('Appliquer exactement les modifications affichées ?', 'no')
            ->assertExitCode(0);

        $this->assertModelExists($data['sessionOne']);
        $this->assertDatabaseCount('emplois_du_temps', 2);
    }

    public function test_confirmed_repair_can_attach_an_orphan_to_a_subject_in_the_same_class(): void
    {
        $data = $this->createRepairData();

        $this->artisan('uist2its:repair-schedule-references', [
            '--teacher-1' => (string) $data['teacherOne']->id,
            '--teacher-2' => (string) $data['teacherOne']->id,
            '--orphan-1' => 'attach:1',
            '--orphan-2' => 'keep',
        ])
            ->expectsOutputToContain('rattacher à matière #1')
            ->expectsConfirmation('Appliquer exactement les modifications affichées ?', 'yes')
            ->assertExitCode(0);

        $this->assertDatabaseHas('emplois_du_temps', [
            'id' => $data['sessionOne']->id,
            'matiere_id' => $data['subjectOne']->id,
        ]);
        $this->assertDatabaseHas('emplois_du_temps', [
            'id' => $data['sessionTwo']->id,
            'matiere_id' => null,
        ]);
    }

    public function test_confirmed_repair_deletes_only_the_selected_orphan(): void
    {
        $data = $this->createRepairData();

        $this->artisan('uist2its:repair-schedule-references', [
            '--teacher-1' => (string) $data['teacherOne']->id,
            '--teacher-2' => (string) $data['teacherOne']->id,
            '--orphan-1' => 'keep',
            '--orphan-2' => 'delete',
        ])
            ->expectsOutputToContain('SUPPRESSION définitive : séance #2')
            ->expectsConfirmation('Appliquer exactement les modifications affichées ?', 'yes')
            ->assertExitCode(0);

        $this->assertDatabaseHas('emplois_du_temps', [
            'id' => $data['sessionOne']->id,
            'matiere_id' => null,
        ]);
        $this->assertDatabaseMissing('emplois_du_temps', ['id' => $data['sessionTwo']->id]);
    }

    /**
     * @return array{
     *     teacherOne: User,
     *     teacherTwo: User,
     *     subjectOne: Matiere,
     *     subjectTwo: Matiere,
     *     sessionOne: EmploiDuTemps,
     *     sessionTwo: EmploiDuTemps
     * }
     */
    private function createRepairData(): array
    {
        $teacherOne = User::create([
            'name' => 'Enseignant un',
            'email' => 'teacher-one-repair@test.ci',
            'password' => 'MotDePasseSolide!',
            'role' => 'teacher',
        ]);
        $teacherTwo = User::create([
            'name' => 'Enseignant deux',
            'email' => 'teacher-two-repair@test.ci',
            'password' => 'MotDePasseSolide!',
            'role' => 'teacher',
        ]);
        $filiere = Filiere::create(['nom' => 'Informatique', 'code' => 'INFO']);
        $niveau = Niveau::create([
            'filiere_id' => $filiere->id,
            'nom' => 'Master 1',
            'code' => 'M1',
            'credits_requis' => 60,
        ]);
        $subjectOne = $this->createSubject($filiere, $niveau, $teacherOne, 'MATH-01');
        $subjectTwo = $this->createSubject($filiere, $niveau, $teacherOne, 'INFO-02');
        $sessionOne = $this->createOrphanSession($filiere, $niveau, 'Orpheline un', '2026-10-12');
        $sessionTwo = $this->createOrphanSession($filiere, $niveau, 'Orpheline deux', '2026-10-13');

        return compact('teacherOne', 'teacherTwo', 'subjectOne', 'subjectTwo', 'sessionOne', 'sessionTwo');
    }

    private function createSubject(Filiere $filiere, Niveau $niveau, User $teacher, string $code): Matiere
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

    private function createOrphanSession(Filiere $filiere, Niveau $niveau, string $title, string $date): EmploiDuTemps
    {
        return EmploiDuTemps::create([
            'titre' => $title,
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
            'matiere_id' => null,
            'enseignant_id' => null,
            'date_cours' => $date,
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
            'salle' => 'Salle A1',
            'description' => 'Description de test',
            'statut' => 'publie',
        ]);
    }
}
