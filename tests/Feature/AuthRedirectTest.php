<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_teacher_without_required_password_change_is_redirected_to_teacher_space(): void
    {
        $teacher = $this->createUser('teacher', false);

        $this->post(route('login.user'), [
            'email' => $teacher->email,
            'password' => 'CurrentTeacherPassword2026!',
        ])->assertRedirect(route('espace'));

        $this->get(route('espace'))
            ->assertRedirect(route('enseignant.notes'));
    }

    public function test_teacher_with_required_password_change_is_redirected_to_own_password_page_and_can_update_it(): void
    {
        $teacher = $this->createUser('teacher', true);

        $this->post(route('login.user'), [
            'email' => $teacher->email,
            'password' => 'CurrentTeacherPassword2026!',
        ])->assertRedirect(route('password.change'));

        $this->get(route('password.change'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/ChangePassword'));

        $this->put(route('password.change.update'), [
            'current_password' => 'CurrentTeacherPassword2026!',
            'password' => 'NewTeacherPassword2026!',
            'password_confirmation' => 'NewTeacherPassword2026!',
        ])->assertRedirect(route('espace'));

        $this->assertFalse($teacher->fresh()->password_change_required);
        $this->assertTrue(Hash::check('NewTeacherPassword2026!', $teacher->fresh()->password));
        $this->get(route('espace'))->assertRedirect(route('enseignant.notes'));
    }

    public function test_admin_must_submit_the_animal_passphrase_before_authentication(): void
    {
        config(['uist2its.admin_mfa_passphrase' => 'lionne']);
        $admin = $this->createUser('admin', false);

        $this->post(route('login.user'), [
            'email' => $admin->email,
            'password' => 'CurrentTeacherPassword2026!',
        ])->assertRedirect(route('login'));

        $this->assertGuest();
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('adminMfaRequired', true));

        $this->post(route('login.admin-mfa'), [
            'admin_passphrase' => 'tigre',
        ])->assertSessionHasErrors('admin_passphrase');

        $this->assertGuest();
        $this->post(route('login.admin-mfa'), [
            'admin_passphrase' => 'lionne',
        ])->assertRedirect(route('espace'));

        $this->assertAuthenticatedAs($admin);
        $this->assertDatabaseHas('connexions_log', [
            'user_id' => $admin->id,
            'type_connexion' => 'admin_mfa',
            'reussie' => true,
        ]);
    }

    public function test_admin_login_fails_with_service_unavailable_when_passphrase_is_not_configured(): void
    {
        config(['uist2its.admin_mfa_passphrase' => null]);
        $admin = $this->createUser('admin', false);

        $this->post(route('login.user'), [
            'email' => $admin->email,
            'password' => 'CurrentTeacherPassword2026!',
        ])->assertServiceUnavailable();

        $this->assertGuest();
    }

    public function test_admin_passphrase_endpoint_is_rate_limited(): void
    {
        config(['uist2its.admin_mfa_passphrase' => 'lionne']);
        $admin = $this->createUser('admin', false);

        $this->post(route('login.user'), [
            'email' => $admin->email,
            'password' => 'CurrentTeacherPassword2026!',
        ])->assertRedirect(route('login'));

        for ($attempt = 0; $attempt < 9; $attempt++) {
            $this->post(route('login.admin-mfa'), [
                'admin_passphrase' => 'incorrect',
            ])->assertSessionHasErrors('admin_passphrase');
        }

        $this->post(route('login.admin-mfa'), [
            'admin_passphrase' => 'incorrect',
        ])->assertTooManyRequests();

        $this->assertGuest();
    }

    public function test_student_signs_in_with_matricule_and_date_of_birth_even_when_password_change_is_flagged(): void
    {
        $student = User::create([
            'name' => 'Étudiant de test',
            'matricule' => 'STUDENT-PASSWORD-CHANGE',
            'date_naissance' => '2002-05-10',
            'password' => null,
            'role' => 'student',
        ]);
        $student->forceFill(['password_change_required' => true])->save();

        $this->post(route('login.student'), [
            'matricule' => $student->matricule,
            'date_naissance' => '2002-05-10',
        ])->assertRedirect(route('espace'));

        $this->assertAuthenticatedAs($student);
        $this->get(route('etudiant.dashboard'))->assertOk();
        $this->get(route('password.change'))->assertForbidden();
        $this->assertTrue($student->fresh()->password_change_required);
        $this->get(route('espace'))->assertRedirect(route('etudiant.dashboard'));
    }

    public function test_espace_redirects_admin_teacher_and_student_to_their_role_pages(): void
    {
        $admin = $this->createUser('admin', false);
        $teacher = $this->createUser('teacher', false);
        $student = $this->createUser('student', false);

        $this->actingAs($admin)->get(route('espace'))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($teacher)->get(route('espace'))->assertRedirect(route('enseignant.notes'));
        $this->actingAs($student)->get(route('espace'))->assertRedirect(route('etudiant.dashboard'));
    }

    private function createUser(string $role, bool $passwordChangeRequired): User
    {
        static $userNumber = 0;
        $userNumber++;

        $user = User::create([
            'name' => ucfirst($role).' Test '.$userNumber,
            'email' => $role.'-'.$userNumber.'@test.ci',
            'password' => 'CurrentTeacherPassword2026!',
            'role' => $role,
        ]);

        $user->forceFill(['password_change_required' => $passwordChangeRequired])->save();

        return $user;
    }
}
