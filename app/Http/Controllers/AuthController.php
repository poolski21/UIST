<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentLoginRequest;
use App\Http\Requests\UserLoginRequest;
use App\Models\ConnexionLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'studentSecondFactor' => true,
        ]);
    }

    public function student(StudentLoginRequest $request): RedirectResponse
    {
        $matricule = trim($request->string('matricule')->toString());
        $key = mb_strtolower($matricule).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, config('uist2its.student_login_attempts'))) {
            $this->journaliser($request, null, $matricule, false, 'matricule_limitee');
            throw ValidationException::withMessages([
                'matricule' => 'Trop de tentatives. Réessayez dans '.RateLimiter::availableIn($key).' secondes.',
            ]);
        }

        $etudiant = User::query()->where('matricule', $matricule)->where('role', 'student')->first();
        $secondFactorValide = $etudiant?->date_naissance?->format('Y-m-d') === $request->input('date_naissance');

        if (! $etudiant || ! $secondFactorValide) {
            RateLimiter::hit($key, config('uist2its.student_login_decay_minutes') * 60);
            $this->journaliser($request, $etudiant, $matricule, false, 'matricule');

            throw ValidationException::withMessages([
                'matricule' => 'Matricule ou date de naissance invalide.',
                'date_naissance' => 'Matricule ou date de naissance invalide.',
            ]);
        }

        RateLimiter::clear($key);
        Auth::login($etudiant);
        $request->session()->regenerate();
        $this->journaliser($request, $etudiant, $matricule, true, 'matricule');

        return redirect()->intended($etudiant->password_change_required
            ? route('admin.passwords.index')
            : route('espace'));
    }

    public function user(UserLoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');
        $candidate = User::query()->where('email', $credentials['email'])->first();
        $success = $candidate
            && in_array($candidate->role, ['teacher', 'admin'], true)
            && $candidate->password
            && Hash::check($credentials['password'], $candidate->password);

        $this->journaliser($request, $candidate, null, (bool) $success, 'email');
        if (! $success) {
            throw ValidationException::withMessages(['email' => 'Identifiants incorrects.']);
        }

        Auth::login($candidate);
        $request->session()->regenerate();

        return redirect()->intended($candidate->password_change_required
            ? route('admin.passwords.index')
            : route('espace'));
    }

    public function destroy(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function journaliser($request, ?User $user, ?string $matricule, bool $reussie, string $type): void
    {
        ConnexionLog::create([
            'user_id' => $user?->id,
            'matricule' => $matricule,
            'type_connexion' => $type,
            'reussie' => $reussie,
            'adresse_ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
        ]);
    }
}
