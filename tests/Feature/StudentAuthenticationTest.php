<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_student_can_sign_in_with_matricule_and_date_of_birth(): void
    {
        $student = User::create([
            'name' => 'Étudiant de test',
            'role' => 'student',
            'matricule' => 'UIST-DOB-LOGIN',
            'date_naissance' => '2002-05-10',
            'password' => 'UnusedStudentPassword!',
        ]);

        $this->from(route('login'))->post(route('login.student'), [
            'matricule' => $student->matricule,
            'date_naissance' => '2002-05-10',
        ])->assertRedirect(route('espace'));

        $this->assertAuthenticatedAs($student);
        $this->assertDatabaseHas('connexions_log', [
            'user_id' => $student->id,
            'type_connexion' => 'matricule',
            'reussie' => true,
        ]);
    }

    public function test_student_password_does_not_replace_the_required_date_of_birth(): void
    {
        $student = User::create([
            'name' => 'Étudiante de test',
            'role' => 'student',
            'matricule' => 'UIST-DOB-REQUIRED',
            'date_naissance' => '2002-05-10',
            'password' => 'UnusedStudentPassword!',
        ]);

        $this->from(route('login'))->post(route('login.student'), [
            'matricule' => $student->matricule,
            'password' => 'UnusedStudentPassword!',
        ])->assertSessionHasErrors('date_naissance');

        $this->assertGuest();
    }

    public function test_student_login_rejects_a_wrong_date_of_birth(): void
    {
        $student = User::create([
            'name' => 'Étudiant de test',
            'role' => 'student',
            'matricule' => 'UIST-DOB-WRONG',
            'date_naissance' => '2002-05-10',
            'password' => null,
        ]);

        $this->from(route('login'))->post(route('login.student'), [
            'matricule' => $student->matricule,
            'date_naissance' => '2002-05-11',
        ])->assertSessionHasErrors('matricule');

        $this->assertGuest();
    }
}
