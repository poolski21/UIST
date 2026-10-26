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
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_production_seeder_creates_the_initial_admin_but_skips_demo_accounts(): void
    {
        $this->app->instance('env', 'production');
        config(['uist2its.admin_initial_password' => null]);

        Artisan::call('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@uist.ci',
            'role' => 'admin',
            'password_change_required' => true,
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'enseignant1@uist.ci']);
        $this->assertDatabaseMissing('users', ['matricule' => 'UIST-2026-0001']);
        $this->assertStringContainsString('données et comptes de démonstration ne seront pas créés', Artisan::output());
    }

    public function test_non_production_seeder_still_creates_demo_accounts(): void
    {
        $this->assertFalse(app()->isProduction());

        Artisan::call('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
        ]);

        $teacher = User::query()->where('email', 'enseignant1@uist.ci')->firstOrFail();
        $this->assertTrue(Hash::check('EnseignantUIST!1', $teacher->password));
        $this->assertDatabaseHas('users', ['matricule' => 'UIST-2026-0001']);
    }

    public function test_note_remains_published_when_notification_send_throws(): void
    {
        $student = $this->createStudent();
        $filiere = Filiere::findOrFail($student->filiere_id);
        $niveau = Niveau::findOrFail($student->niveau_id);
        $annee = AnneeAcademique::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'active' => true,
        ]);
        $matiere = Matiere::create([
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
            'nom' => 'Algorithmique',
            'code' => 'ALG-1',
            'credits' => 3,
            'coefficient' => 1,
        ]);
        Inscription::create([
            'etudiant_id' => $student->id,
            'niveau_id' => $niveau->id,
            'annee_academique_id' => $annee->id,
        ]);
        $note = Note::create([
            'etudiant_id' => $student->id,
            'matiere_id' => $matiere->id,
            'annee_academique_id' => $annee->id,
            'note' => 14,
            'notes_td' => [14, 14],
            'note_examen' => 14,
            'statut' => 'brouillon',
        ]);
        $admin = User::create([
            'name' => 'Administrateur',
            'email' => 'admin@test.ci',
            'password' => 'MotDePasseSolide!',
            'role' => 'admin',
        ]);
        $exception = new RuntimeException('SMTP indisponible');

        Notification::shouldReceive('send')->once()->andThrow($exception);
        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'Échec de notification après publication des notes.')
                && $context['matiere_id'] === $matiere->id
                && $context['annee_academique_id'] === $annee->id
                && $context['destinataires'] === 1
                && $context['exception'] === $exception);

        $this->actingAs($admin)
            ->post(route('admin.notes.publish', [$matiere, $annee]))
            ->assertRedirect()
            ->assertSessionHas('success', '1 note(s) publiée(s).');

        $this->assertSame('publie', $note->fresh()->statut);
        $this->assertNotNull($note->fresh()->publie_le);
    }

    public function test_annual_result_remains_published_when_notification_send_throws(): void
    {
        $student = $this->createStudent();
        $annee = AnneeAcademique::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'active' => true,
        ]);
        $resultat = ResultatAnnuel::create([
            'etudiant_id' => $student->id,
            'annee_academique_id' => $annee->id,
            'moyenne_generale' => 14,
            'credits_obtenus' => 60,
            'decision_auto' => 'admis',
            'decision_finale' => 'admis',
            'a_recalculer' => false,
        ]);
        $admin = User::create([
            'name' => 'Administrateur',
            'email' => 'admin@test.ci',
            'password' => 'MotDePasseSolide!',
            'role' => 'admin',
        ]);
        $exception = new RuntimeException('SMTP indisponible');
        $loggedException = null;

        Notification::shouldReceive('send')->once()->andThrow($exception);
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context) use (&$loggedException): bool {
                $loggedException = $context['exception'] ?? null;

                return str_contains($message, 'Échec de notification après publication du résultat annuel.');
            });

        $this->actingAs($admin)
            ->post(route('admin.resultats.publish', $resultat))
            ->assertRedirect()
            ->assertSessionHas('success', 'Décision publiée à l’étudiant.');

        $this->assertSame($exception->getMessage(), $loggedException?->getMessage());
        $this->assertNotNull($resultat->fresh()->publie_le);
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
            'name' => 'Étudiant',
            'email' => 'student@test.ci',
            'password' => 'MotDePasseSolide!',
            'role' => 'student',
            'matricule' => 'ETU-1',
            'filiere_id' => $filiere->id,
            'niveau_id' => $niveau->id,
        ]);
    }
}
