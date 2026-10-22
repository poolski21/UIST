<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_reset_command_generates_a_one_time_secret_and_requires_password_change(): void
    {
        $admin = User::create([
            'name' => 'Admin Reset',
            'email' => 'reset-admin@test.ci',
            'password' => 'AncienMotDePasseSolide!',
            'role' => 'admin',
        ]);

        Artisan::call('admin:reset-password', ['email' => $admin->email]);
        $output = Artisan::output();
        preg_match('/(?m)^([a-f0-9]{48})$/', trim($output), $matches);
        $temporaryPassword = $matches[1] ?? null;

        $this->assertIsString($temporaryPassword);
        $admin->refresh();
        $this->assertTrue($admin->password_change_required);
        $this->assertTrue(Hash::check($temporaryPassword, $admin->password));
        $this->assertDatabaseHas('admin_password_changes', [
            'actor_id' => null,
            'target_admin_id' => $admin->id,
            'target_email' => $admin->email,
            'action' => 'reset',
        ]);
        $this->actingAs($admin)->get('/admin')->assertRedirect(route('admin.passwords.index'));

        $this->put(route('admin.passwords.update'), [
            'current_password' => $temporaryPassword,
            'password' => 'NouvelleSecuriteAdmin2026!',
            'password_confirmation' => 'NouvelleSecuriteAdmin2026!',
        ])->assertRedirect(route('admin.dashboard'));

        $admin->refresh();
        $this->assertFalse($admin->password_change_required);
        $this->assertTrue(Hash::check('NouvelleSecuriteAdmin2026!', $admin->password));
        $this->assertDatabaseHas('admin_password_changes', [
            'actor_id' => $admin->id,
            'target_admin_id' => $admin->id,
            'target_email' => $admin->email,
            'action' => 'change',
        ]);
    }

    public function test_admin_ui_displays_a_generated_secret_once_and_only_for_admin_accounts(): void
    {
        $actor = User::create(['name' => 'Admin acteur', 'email' => 'actor@test.ci', 'password' => 'MotDePasseTresSolide!', 'role' => 'admin']);
        $target = User::create(['name' => 'Admin cible', 'email' => 'target@test.ci', 'password' => 'AutreMotDePasseSolide!', 'role' => 'admin']);
        $teacher = User::create(['name' => 'Enseignant', 'email' => 'teacher-reset@test.ci', 'password' => 'EnseignantMotDePasse!', 'role' => 'teacher']);

        $this->actingAs($actor)->post(route('admin.passwords.reset', $target))
            ->assertRedirect(route('admin.passwords.index'));
        $secret = session('generated_admin_password');

        $this->get(route('admin.passwords.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Passwords')
                ->where('generatedPassword', $secret)
                ->where('mustChangePassword', false));
        $this->assertNull(session('generated_admin_password'));
        $this->assertTrue($target->fresh()->password_change_required);
        $this->assertFalse(Hash::check((string) $secret, $actor->fresh()->password));
        $this->actingAs($actor)->post(route('admin.passwords.reset', $teacher))->assertForbidden();
    }

    public function test_only_existing_admin_accounts_can_be_reset_by_the_console_command(): void
    {
        $teacher = User::create([
            'name' => 'Enseignant',
            'email' => 'teacher-command@test.ci',
            'password' => 'EnseignantMotDePasse!',
            'role' => 'teacher',
        ]);
        $passwordBefore = $teacher->password;

        $exitCode = Artisan::call('admin:reset-password', ['email' => $teacher->email]);

        $this->assertSame(1, $exitCode);
        $this->assertSame($passwordBefore, $teacher->fresh()->password);
        $this->assertDatabaseCount('admin_password_changes', 0);
    }
}
