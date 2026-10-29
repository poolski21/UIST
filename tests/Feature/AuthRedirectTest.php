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

    public function test_student_with_required_password_change_still_reaches_student_space_and_cannot_access_password_change(): void
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

        $this->get(route('espace'))
            ->assertRedirect(route('etudiant.dashboard'));

        $this->get(route('password.change'))->assertForbidden();
        $this->put(route('password.change.update'), [
            'current_password' => 'CurrentTeacherPassword2026!',
            'password' => 'NewStudentPassword2026!',
            'password_confirmation' => 'NewStudentPassword2026!',
        ])->assertForbidden();
        $this->assertTrue($student->fresh()->password_change_required);
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
