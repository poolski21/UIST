<?php

namespace Tests\Feature;

use App\Models\Annonce;
use App\Models\Filiere;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnnouncementAttachmentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_announcement_attachments_are_private_and_only_downloadable_by_the_target_audience(): void
    {
        $admin = User::create([
            'name' => 'Administrateur',
            'email' => 'announcement-admin@test.ci',
            'password' => 'MotDePasseAdminSolide!',
            'role' => 'admin',
        ]);
        $filiere = Filiere::create(['nom' => 'Informatique', 'code' => 'INFO-ANNOUNCEMENT']);
        $otherFiliere = Filiere::create(['nom' => 'Gestion', 'code' => 'GEST-ANNOUNCEMENT']);
        $student = User::create([
            'name' => 'Étudiant ciblé',
            'role' => 'student',
            'matricule' => 'UIST-ANNOUNCEMENT-1',
            'filiere_id' => $filiere->id,
            'password' => 'MotDePasseEtudiant!',
        ]);
        $otherStudent = User::create([
            'name' => 'Étudiant non ciblé',
            'role' => 'student',
            'matricule' => 'UIST-ANNOUNCEMENT-2',
            'filiere_id' => $otherFiliere->id,
            'password' => 'MotDePasseEtudiant!',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.annonces.store'), [
                'titre' => 'Document de filière',
                'contenu' => 'Document réservé à la filière.',
                'piece_jointe' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
                'cible_type' => 'filiere',
                'cible_id' => $filiere->id,
                'publier' => true,
            ])
            ->assertRedirect();

        $annonce = Annonce::query()->firstOrFail();
        Storage::disk('local')->assertExists($annonce->piece_jointe);
        Storage::disk('public')->assertMissing($annonce->piece_jointe);

        $this->actingAs($student)
            ->get(route('etudiant.infos'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('annonces.data.0.piece_jointe', route('annonces.attachment', $annonce)));

        auth()->logout();
        $this->get(route('annonces.attachment', $annonce))
            ->assertRedirect(route('login'));

        $this->actingAs($student);
        $response = $this->get(route('annonces.attachment', $annonce));
        $response->assertOk();
        $this->assertStringContainsString('attachment;', $response->headers->get('content-disposition'));

        $this->actingAs($otherStudent)
            ->get(route('annonces.attachment', $annonce))
            ->assertForbidden();
    }

    public function test_existing_public_announcement_files_are_moved_to_private_storage(): void
    {
        Storage::disk('public')->put('annonces/legacy.pdf', 'legacy content');
        $legacyAppRoot = dirname(Storage::disk('local')->path(''));
        File::ensureDirectoryExists($legacyAppRoot.'/annonces');
        File::ensureDirectoryExists($legacyAppRoot.'/cours');
        file_put_contents($legacyAppRoot.'/annonces/mounted.pdf', 'mounted content');
        file_put_contents($legacyAppRoot.'/cours/course.png', 'course content');

        $exitCode = Artisan::call('storage:migrate-public-uploads');

        $this->assertSame(0, $exitCode);
        Storage::disk('public')->assertMissing('annonces/legacy.pdf');
        Storage::disk('public')->assertExists('cours/course.png');
        Storage::disk('local')->assertExists('annonces/legacy.pdf');
        Storage::disk('local')->assertExists('annonces/mounted.pdf');
        $this->assertSame('legacy content', Storage::disk('local')->get('annonces/legacy.pdf'));
        $this->assertSame('mounted content', Storage::disk('local')->get('annonces/mounted.pdf'));
        $this->assertSame('course content', Storage::disk('public')->get('cours/course.png'));
        $this->assertDirectoryDoesNotExist($legacyAppRoot.'/annonces');
        $this->assertDirectoryDoesNotExist($legacyAppRoot.'/cours');
    }
}
