<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Filiere;
use App\Models\HistoriqueNote;
use App\Models\Inscription;
use App\Models\JournalModification;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\ResultatAnnuel;
use App\Models\User;
use App\Services\ValidationAnneeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class PublishedNoteCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_teacher_cannot_change_a_published_note_through_the_regular_entry_route(): void
    {
        ['teacher' => $teacher, 'note' => $note] = $this->createAcademicFixture();
        $publicationDate = $note->publie_le;

        $this->actingAs($teacher)
            ->post(route('enseignant.notes.store'), $this->noteInput($note, [16, 16], 16))
            ->assertForbidden();

        $note->refresh();
        $this->assertSame('publie', $note->statut);
        $this->assertSame('9.00', $note->note);
        $this->assertTrue($publicationDate->equalTo($note->publie_le));
        $this->assertDatabaseCount('historique_notes', 0);
    }

    public function test_teacher_can_update_a_draft_note_for_their_assigned_subject(): void
    {
        ['teacher' => $teacher, 'note' => $note] = $this->createAcademicFixture('brouillon');

        $this->actingAs($teacher)
            ->post(route('enseignant.notes.store'), $this->noteInput($note, [16, 16], 16))
            ->assertRedirect();

        $note->refresh();
        $this->assertSame('brouillon', $note->statut);
        $this->assertSame('16.00', $note->note);
        $this->assertNull($note->publie_le);
        $this->assertDatabaseCount('notes', 1);
    }

    public function test_admin_correction_is_historized_and_marks_an_existing_result_for_recalculation(): void
    {
        ['admin' => $admin, 'student' => $student, 'year' => $year, 'note' => $note] = $this->createAcademicFixture();
        $publicationDate = $note->publie_le;
        $resultPublicationDate = now()->subDay();
        $resultat = ResultatAnnuel::create([
            'etudiant_id' => $student->id,
            'annee_academique_id' => $year->id,
            'moyenne_generale' => 9,
            'credits_obtenus' => 0,
            'decision_auto' => 'ajourne',
            'decision_finale' => 'ajourne',
            'publie_le' => $resultPublicationDate,
        ]);
        $resultPublicationDate = $resultat->fresh()->publie_le;
        $payload = $this->noteInput($note, [15, 15], 15) + ['motif' => 'Correction après vérification du procès-verbal.'];

        $this->actingAs($admin)
            ->put(route('admin.notes.correct', $note), $payload)
            ->assertRedirect();

        $note->refresh();
        $this->assertSame('publie', $note->statut);
        $this->assertSame('15.00', $note->note);
        $this->assertTrue($publicationDate->equalTo($note->publie_le));

        $history = HistoriqueNote::firstOrFail();
        $this->assertSame((int) $note->id, (int) $history->note_id);
        $this->assertSame((int) $admin->id, (int) $history->user_id);
        $this->assertSame('Correction après vérification du procès-verbal.', $history->motif);
        $this->assertNotNull($history->modifie_le);
        $this->assertSame('9.00', $history->ancienne_valeur['note']);
        $this->assertSame('15.00', $history->nouvelle_valeur['note']);

        $resultat->refresh();
        $this->assertTrue($resultat->a_recalculer);
        $this->assertSame('ajourne', $resultat->decision_auto);
        $this->assertSame('ajourne', $resultat->decision_finale);
        $this->assertTrue($resultPublicationDate->equalTo($resultat->publie_le));

        app(ValidationAnneeService::class)->calculer($student, $year);

        $resultat->refresh();
        $this->assertFalse($resultat->a_recalculer);
        $this->assertSame('admis', $resultat->decision_auto);
        $this->assertSame('ajourne', $resultat->decision_finale);
        $this->assertSame('15.00', $resultat->moyenne_generale);
        $this->assertSame(60, $resultat->credits_obtenus);
        $this->assertTrue($resultPublicationDate->equalTo($resultat->publie_le));
    }

    public function test_admin_correction_requires_a_motif_and_changes_nothing_when_missing(): void
    {
        ['admin' => $admin, 'note' => $note] = $this->createAcademicFixture();
        $publicationDate = $note->publie_le;

        $this->actingAs($admin)
            ->from(route('admin.notes'))
            ->put(route('admin.notes.correct', $note), $this->noteInput($note, [15, 15], 15))
            ->assertSessionHasErrors('motif');

        $note->refresh();
        $this->assertSame('9.00', $note->note);
        $this->assertSame('publie', $note->statut);
        $this->assertTrue($publicationDate->equalTo($note->publie_le));
        $this->assertDatabaseCount('historique_notes', 0);
    }

    public function test_admin_correction_rejects_a_motif_shorter_than_ten_characters(): void
    {
        ['admin' => $admin, 'note' => $note] = $this->createAcademicFixture();
        $publicationDate = $note->publie_le;

        $this->actingAs($admin)
            ->from(route('admin.notes'))
            ->put(route('admin.notes.correct', $note), $this->noteInput($note, [15, 15], 15) + ['motif' => 'abc'])
            ->assertSessionHasErrors('motif');

        $note->refresh();
        $this->assertSame('9.00', $note->note);
        $this->assertSame('publie', $note->statut);
        $this->assertTrue($publicationDate->equalTo($note->publie_le));
        $this->assertDatabaseCount('historique_notes', 0);
    }

    public function test_note_history_cannot_be_updated_or_deleted(): void
    {
        ['admin' => $admin, 'note' => $note] = $this->createAcademicFixture();

        $this->actingAs($admin)
            ->put(route('admin.notes.correct', $note), $this->noteInput($note, [15, 15], 15) + [
                'motif' => 'Correction valide du procès-verbal.',
            ])
            ->assertRedirect();

        $history = HistoriqueNote::firstOrFail();
        $originalReason = $history->motif;
        $originalValues = $history->nouvelle_valeur;

        try {
            $history->update(['motif' => 'Tentative de falsification']);
            $this->fail('Une ligne d’historique modifiée doit lever une LogicException.');
        } catch (LogicException $exception) {
            $this->assertSame('L’historique des notes est immuable.', $exception->getMessage());
        }

        try {
            $history->delete();
            $this->fail('Une ligne d’historique supprimée doit lever une LogicException.');
        } catch (LogicException $exception) {
            $this->assertSame('L’historique des notes est immuable.', $exception->getMessage());
        }

        $history->refresh();
        $this->assertSame($originalReason, $history->motif);
        $this->assertSame($originalValues, $history->nouvelle_valeur);
        $this->assertDatabaseCount('historique_notes', 1);
    }

    public function test_teacher_cannot_use_the_administrative_published_note_correction_route(): void
    {
        ['teacher' => $teacher, 'note' => $note] = $this->createAcademicFixture();

        $this->actingAs($teacher)
            ->put(route('admin.notes.correct', $note), $this->noteInput($note, [15, 15], 15) + ['motif' => 'Motif invalide.'])
            ->assertForbidden();

        $this->assertDatabaseCount('historique_notes', 0);
        $this->assertSame('9.00', $note->fresh()->note);
    }

    public function test_admin_must_recalculate_before_recording_a_decision_for_a_flagged_result(): void
    {
        ['admin' => $admin, 'student' => $student, 'year' => $year] = $this->createAcademicFixture();
        $resultat = ResultatAnnuel::create([
            'etudiant_id' => $student->id,
            'annee_academique_id' => $year->id,
            'moyenne_generale' => 9,
            'credits_obtenus' => 0,
            'decision_auto' => 'ajourne',
            'decision_finale' => 'ajourne',
            'a_recalculer' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.resultats.decide', $resultat), [
                'decision_finale' => 'admis',
                'motif' => 'Décision du jury.',
            ])
            ->assertStatus(409);

        $this->assertSame('ajourne', $resultat->fresh()->decision_finale);
        $this->assertDatabaseCount('journal_modifications', 0);
    }

    public function test_recalculation_logs_a_changed_automatic_decision_with_the_correction_reason(): void
    {
        ['admin' => $admin, 'student' => $student, 'year' => $year, 'note' => $note] = $this->createAcademicFixture();
        $resultat = ResultatAnnuel::create([
            'etudiant_id' => $student->id,
            'annee_academique_id' => $year->id,
            'moyenne_generale' => 9,
            'credits_obtenus' => 0,
            'decision_auto' => 'ajourne',
            'decision_finale' => 'ajourne',
            'publie_le' => now(),
        ]);

        $this->actingAs($admin)
            ->put(route('admin.notes.correct', $note), $this->noteInput($note, [15, 15], 15) + [
                'motif' => 'Correction validée du procès-verbal.',
            ])
            ->assertRedirect();
        app(ValidationAnneeService::class)->calculer($student, $year);

        $this->assertDatabaseCount('journal_modifications', 1);
        $entry = JournalModification::firstOrFail();
        $this->assertSame((int) $admin->id, (int) $entry->modifie_par);
        $this->assertSame(ResultatAnnuel::class, $entry->element_type);
        $this->assertSame((int) $resultat->id, (int) $entry->element_id);
        $this->assertSame(['decision_auto' => 'ajourne'], $entry->anciennes_valeurs);
        $this->assertSame(['decision_auto' => 'admis'], $entry->nouvelles_valeurs);
        $this->assertSame('Recalcul automatique suite à correction d’une note publiée', $entry->motif);
        $this->assertNotNull($entry->created_at);

        $resultat->refresh();
        $this->assertSame('admis', $resultat->decision_auto);
        $this->assertSame('ajourne', $resultat->decision_finale);
    }

    public function test_recalculation_without_decision_changes_does_not_create_a_journal_entry(): void
    {
        ['admin' => $admin, 'student' => $student, 'year' => $year] = $this->createAcademicFixture();
        ResultatAnnuel::create([
            'etudiant_id' => $student->id,
            'annee_academique_id' => $year->id,
            'moyenne_generale' => 9,
            'credits_obtenus' => 0,
            'decision_auto' => 'ajourne',
            'decision_finale' => 'ajourne',
        ]);

        $this->actingAs($admin);
        app(ValidationAnneeService::class)->calculer($student, $year);

        $this->assertDatabaseCount('journal_modifications', 0);
    }

    public function test_recalculation_rolls_back_when_journal_creation_fails(): void
    {
        ['admin' => $admin, 'student' => $student, 'year' => $year, 'note' => $note] = $this->createAcademicFixture();
        $resultat = ResultatAnnuel::create([
            'etudiant_id' => $student->id,
            'annee_academique_id' => $year->id,
            'moyenne_generale' => 9,
            'credits_obtenus' => 0,
            'decision_auto' => 'ajourne',
            'decision_finale' => 'ajourne',
            'a_recalculer' => true,
        ]);
        $note->update([
            'note' => 15,
            'notes_td' => [15, 15],
            'note_examen' => 15,
        ]);
        JournalModification::creating(static function (): never {
            throw new RuntimeException('Échec simulé de création du journal.');
        });
        $this->actingAs($admin);

        try {
            app(ValidationAnneeService::class)->calculer($student, $year);
            $this->fail('Le recalcul doit échouer si le journal ne peut pas être écrit.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Échec simulé de création du journal.', $exception->getMessage());
        }

        $resultat->refresh();
        $this->assertSame('ajourne', $resultat->decision_auto);
        $this->assertSame('ajourne', $resultat->decision_finale);
        $this->assertSame('9.00', $resultat->moyenne_generale);
        $this->assertSame(0, $resultat->credits_obtenus);
        $this->assertTrue($resultat->a_recalculer);
        $this->assertDatabaseCount('journal_modifications', 0);
    }

    private function createAcademicFixture(string $status = 'publie'): array
    {
        $filiere = Filiere::create(['nom' => 'Informatique', 'code' => 'INFO']);
        $niveau = Niveau::create([
            'filiere_id' => $filiere->id,
            'nom' => 'Licence 1',
            'code' => 'L1',
            'credits_requis' => 60,
        ]);
        $year = AnneeAcademique::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
        ]);
        $teacher = User::create([
            'name' => 'Enseignant',
            'email' => 'teacher@test.ci',
            'password' => 'MotDePasseSolide!',
            'role' => 'teacher',
        ]);
        $admin = User::create([
            'name' => 'Administrateur',
            'email' => 'admin@test.ci',
            'password' => 'MotDePasseSolide!',
            'role' => 'admin',
        ]);
        $student = User::create([
            'name' => 'Étudiant',
            'email' => 'student@test.ci',
            'password' => 'MotDePasseSolide!',
            'role' => 'student',
            'matricule' => 'ETU-1',
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
        ]);
        $subject = Matiere::create([
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
            'enseignant_id' => $teacher->id,
            'nom' => 'Algorithmique',
            'code' => 'ALG-1',
            'credits' => 60,
            'coefficient' => 1,
        ]);
        Inscription::create([
            'etudiant_id' => $student->id,
            'niveau_id' => $niveau->id,
            'annee_academique_id' => $year->id,
        ]);
        $note = Note::create([
            'etudiant_id' => $student->id,
            'matiere_id' => $subject->id,
            'annee_academique_id' => $year->id,
            'note' => 9,
            'notes_td' => [9, 9],
            'note_examen' => 9,
            'statut' => $status,
            'publie_le' => $status === 'publie' ? now()->subDays(2) : null,
        ]);

        return compact('admin', 'student', 'teacher', 'year', 'subject', 'note');
    }

    private function noteInput(Note $note, array $td, float $exam): array
    {
        return [
            'matiere_id' => $note->matiere_id,
            'etudiant_id' => $note->etudiant_id,
            'annee_academique_id' => $note->annee_academique_id,
            'notes_td' => $td,
            'note_examen' => $exam,
        ];
    }
}
