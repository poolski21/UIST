<?php

namespace Tests\Feature;

use App\Models\EmploiDuTemps;
use App\Models\Filiere;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmploiDuTempsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_session_rejects_times_outside_the_allowed_range(): void
    {
        ['admin' => $admin, 'filiere' => $filiere, 'niveau' => $niveau, 'teacher' => $teacher, 'subject' => $subject] = $this->makeClass();

        $this->actingAs($admin)
            ->post(route('admin.emplois.store'), $this->validPayload($filiere, $niveau, $teacher, $subject, [
                'heure_debut' => '07:59',
            ]))
            ->assertSessionHasErrors('heure_debut');

        $this->actingAs($admin)
            ->post(route('admin.emplois.store'), $this->validPayload($filiere, $niveau, $teacher, $subject, [
                'heure_fin' => '16:31',
            ]))
            ->assertSessionHasErrors('heure_fin');

        $this->assertDatabaseCount('emplois_du_temps', 0);
    }

    public function test_session_rejects_end_time_not_after_start_time(): void
    {
        ['admin' => $admin, 'filiere' => $filiere, 'niveau' => $niveau, 'teacher' => $teacher, 'subject' => $subject] = $this->makeClass();

        $this->actingAs($admin)
            ->post(route('admin.emplois.store'), $this->validPayload($filiere, $niveau, $teacher, $subject, [
                'heure_debut' => '10:00',
                'heure_fin' => '10:00',
            ]))
            ->assertSessionHasErrors('heure_fin');

        $this->assertDatabaseCount('emplois_du_temps', 0);
    }

    public function test_session_rejects_sunday(): void
    {
        ['admin' => $admin, 'filiere' => $filiere, 'niveau' => $niveau, 'teacher' => $teacher, 'subject' => $subject] = $this->makeClass();

        $this->actingAs($admin)
            ->post(route('admin.emplois.store'), $this->validPayload($filiere, $niveau, $teacher, $subject, [
                'jour' => 7,
            ]))
            ->assertSessionHasErrors('jour');

        $this->assertDatabaseCount('emplois_du_temps', 0);
    }

    public function test_overlapping_sessions_for_the_same_class_are_rejected(): void
    {
        ['admin' => $admin, 'filiere' => $filiere, 'niveau' => $niveau, 'teacher' => $teacher, 'subject' => $subject] = $this->makeClass();
        $otherTeacher = $this->makeTeacher('class-conflict');
        $otherSubject = $this->makeSubject($filiere, $niveau, $otherTeacher, 'CLASS-CONFLICT');
        $this->makeSession($filiere, $niveau, $subject, $teacher, 'Room 1', '10:00', '11:00');

        $this->actingAs($admin)
            ->post(route('admin.emplois.store'), $this->validPayload($filiere, $niveau, $otherTeacher, $otherSubject, [
                'heure_debut' => '10:30',
                'heure_fin' => '11:30',
                'salle' => 'Room 2',
            ]))
            ->assertSessionHasErrors('jour');

        $this->assertDatabaseCount('emplois_du_temps', 1);
    }

    public function test_overlapping_sessions_for_the_same_teacher_are_rejected_across_classes(): void
    {
        ['admin' => $admin, 'filiere' => $filiere, 'niveau' => $niveau, 'teacher' => $teacher, 'subject' => $subject] = $this->makeClass();
        $otherClass = $this->makeClass('GEST', 'L1', 'teacher-other-class');
        $this->makeSession($filiere, $niveau, $subject, $teacher, 'Room 1', '10:00', '11:00');

        $this->actingAs($admin)
            ->post(route('admin.emplois.store'), $this->validPayload(
                $otherClass['filiere'],
                $otherClass['niveau'],
                $teacher,
                $otherClass['subject'],
                ['heure_debut' => '10:30', 'heure_fin' => '11:30', 'salle' => 'Room 2'],
            ))
            ->assertSessionHasErrors('enseignant_id');

        $this->assertDatabaseCount('emplois_du_temps', 1);
    }

    public function test_overlapping_sessions_for_the_same_room_are_rejected_across_classes(): void
    {
        ['admin' => $admin, 'filiere' => $filiere, 'niveau' => $niveau, 'teacher' => $teacher, 'subject' => $subject] = $this->makeClass();
        $otherClass = $this->makeClass('GEST', 'L1', 'room-other-class');
        $otherTeacher = $this->makeTeacher('room-conflict');
        $otherSubject = $this->makeSubject($otherClass['filiere'], $otherClass['niveau'], $otherTeacher, 'ROOM-CONFLICT');
        $this->makeSession($filiere, $niveau, $subject, $teacher, 'Room 1', '10:00', '11:00');

        $this->actingAs($admin)
            ->post(route('admin.emplois.store'), $this->validPayload(
                $otherClass['filiere'],
                $otherClass['niveau'],
                $otherTeacher,
                $otherSubject,
                ['heure_debut' => '10:30', 'heure_fin' => '11:30', 'salle' => 'Room 1'],
            ))
            ->assertSessionHasErrors('salle');

        $this->assertDatabaseCount('emplois_du_temps', 1);
    }

    public function test_unassigned_teacher_and_room_do_not_trigger_cross_class_conflicts(): void
    {
        ['admin' => $admin, 'filiere' => $filiere, 'niveau' => $niveau, 'teacher' => $teacher, 'subject' => $subject] = $this->makeClass();
        $otherClass = $this->makeClass('GEST', 'L1', 'unassigned-other-class');
        $this->makeSession($filiere, $niveau, $subject, $teacher, 'Room 1', '10:00', '11:00');

        $this->actingAs($admin)
            ->post(route('admin.emplois.store'), $this->validPayload(
                $otherClass['filiere'],
                $otherClass['niveau'],
                $otherClass['teacher'],
                $otherClass['subject'],
                ['enseignant_id' => null, 'heure_debut' => '10:30', 'heure_fin' => '11:30', 'salle' => ''],
            ))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('emplois_du_temps', 2);
    }

    public function test_adjacent_sessions_are_allowed_and_an_update_does_not_conflict_with_itself(): void
    {
        ['admin' => $admin, 'filiere' => $filiere, 'niveau' => $niveau, 'teacher' => $teacher, 'subject' => $subject] = $this->makeClass();
        $session = $this->makeSession($filiere, $niveau, $subject, $teacher, 'Room 1', '10:00', '11:00');

        $payload = $this->validPayload($filiere, $niveau, $teacher, $subject, [
            'heure_debut' => '11:00',
            'heure_fin' => '12:00',
        ]);
        $this->actingAs($admin)
            ->post(route('admin.emplois.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->put(route('admin.emplois.update', $session), $this->validPayload($filiere, $niveau, $teacher, $subject, [
                'heure_debut' => '10:00',
                'heure_fin' => '11:00',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('emplois_du_temps', 2);
    }

    public function test_week_publication_only_publishes_drafts_for_the_selected_class_and_week(): void
    {
        ['admin' => $admin, 'filiere' => $filiere, 'niveau' => $niveau, 'teacher' => $teacher, 'subject' => $subject] = $this->makeClass();
        $student = User::create([
            'name' => 'Étudiante de la classe',
            'role' => 'student',
            'matricule' => 'ETU-PUBLISH-WEEK',
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
        ]);
        $first = $this->makeSession($filiere, $niveau, $subject, $teacher, 'Room 1', '08:00', '09:00');
        $second = $this->makeSession($filiere, $niveau, $subject, $teacher, 'Room 2', '09:00', '10:00', '2026-10-20');
        $otherClass = $this->makeClass('GEST', 'L1', 'publish-other-class');
        $otherClassDraft = $this->makeSession(
            $otherClass['filiere'],
            $otherClass['niveau'],
            $otherClass['subject'],
            $otherClass['teacher'],
            'Room 3',
            '08:00',
            '09:00',
        );
        $otherWeekDraft = $this->makeSession($filiere, $niveau, $subject, $teacher, 'Room 4', '08:00', '09:00', '2026-10-26');

        $this->actingAs($admin)
            ->post(route('admin.emplois.publish-week'), [
                'filiere_id' => $filiere->id,
                'niveau_id' => $niveau->id,
                'week_start' => '2026-10-19',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('emplois_du_temps', ['id' => $first->id, 'statut' => 'publie']);
        $this->assertDatabaseHas('emplois_du_temps', ['id' => $second->id, 'statut' => 'publie']);
        $this->assertDatabaseHas('emplois_du_temps', ['id' => $otherClassDraft->id, 'statut' => 'brouillon']);
        $this->assertDatabaseHas('emplois_du_temps', ['id' => $otherWeekDraft->id, 'statut' => 'brouillon']);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame(2, $student->fresh()->notifications()->count());
    }

    /**
     * @return array{admin: User, filiere: Filiere, niveau: Niveau, teacher: User, subject: Matiere}
     */
    private function makeClass(string $code = 'INFO', string $levelCode = 'L1', string $suffix = 'base'): array
    {
        $admin = User::create([
            'name' => 'Administrateur',
            'email' => 'admin-'.$suffix.'@test.ci',
            'password' => 'MotDePasseTresSolide!',
            'role' => 'admin',
        ]);
        $filiere = Filiere::create(['nom' => 'Informatique '.$suffix, 'code' => $code.'-'.$suffix]);
        $niveau = Niveau::create([
            'filiere_id' => $filiere->id,
            'nom' => 'Licence '.$levelCode,
            'code' => $levelCode,
            'credits_requis' => 60,
        ]);
        $teacher = $this->makeTeacher($suffix);
        $subject = $this->makeSubject($filiere, $niveau, $teacher, 'SUBJECT-'.$suffix);

        return compact('admin', 'filiere', 'niveau', 'teacher', 'subject');
    }

    private function makeTeacher(string $suffix): User
    {
        return User::create([
            'name' => 'Enseignant '.$suffix,
            'email' => 'teacher-'.$suffix.'@test.ci',
            'password' => 'MotDePasseTresSolide!',
            'role' => 'teacher',
        ]);
    }

    private function makeSubject(Filiere $filiere, Niveau $niveau, User $teacher, string $code): Matiere
    {
        return Matiere::create([
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
            'enseignant_id' => $teacher->id,
            'nom' => 'Algorithmique '.$code,
            'code' => $code,
            'credits' => 3,
            'coefficient' => 1,
        ]);
    }

    private function makeSession(
        Filiere $filiere,
        Niveau $niveau,
        Matiere $subject,
        User $teacher,
        string $room,
        string $start,
        string $end,
        string $date = '2026-10-19',
    ): EmploiDuTemps {
        return EmploiDuTemps::create([
            'titre' => $subject->nom,
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
            'matiere_id' => $subject->id,
            'enseignant_id' => $teacher->id,
            'date_cours' => $date,
            'heure_debut' => $start,
            'heure_fin' => $end,
            'salle' => $room,
            'statut' => 'brouillon',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(Filiere $filiere, Niveau $niveau, User $teacher, Matiere $subject, array $overrides = []): array
    {
        return array_merge([
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
            'week_start' => '2026-10-19',
            'jour' => 1,
            'matiere_id' => $subject->id,
            'enseignant_id' => $teacher->id,
            'heure_debut' => '08:00',
            'heure_fin' => '09:30',
            'salle' => 'Room 1',
            'description' => '',
            'publier' => false,
        ], $overrides);
    }
}
