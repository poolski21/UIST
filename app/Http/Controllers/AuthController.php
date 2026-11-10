<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentLoginRequest;
use App\Http\Requests\UserLoginRequest;
use App\Models\ConnexionLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $pendingAdminId = request()->session()->get('admin_mfa_user_id');
        $pendingAdmin = $pendingAdminId
            ? User::query()->whereKey($pendingAdminId)->where('role', 'admin')->exists()
            : false;

        if (! $pendingAdmin) {
            request()->session()->forget('admin_mfa_user_id');
        }

        return Inertia::render('Auth/Login', [
            'adminMfaRequired' => $pendingAdmin,
        ]);
    }

    public function student(StudentLoginRequest $request): RedirectResponse
    {
        $request->session()->forget('admin_mfa_user_id');
        $matricule = trim($request->string('matricule')->toString());
        $key = mb_strtolower($matricule).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, config('uist2its.student_login_attempts'))) {
            $this->journaliser($request, null, $matricule, false, 'matricule_limitee');
            throw ValidationException::withMessages([
                'matricule' => 'Trop de tentatives. Réessayez dans '.RateLimiter::availableIn($key).' secondes.',
            ]);
        }

        $etudiant = User::query()->where('matricule', $matricule)->where('role', 'student')->first();
        $dateNaissanceValide = $etudiant?->date_naissance?->toDateString()
            === $request->validated('date_naissance');

        if (! $etudiant || ! $dateNaissanceValide) {
            RateLimiter::hit($key, config('uist2its.student_login_decay_minutes') * 60);
            $this->journaliser($request, $etudiant, $matricule, false, 'matricule');

            throw ValidationException::withMessages([
                'matricule' => 'Matricule ou date de naissance invalide.',
            ]);
        }

        RateLimiter::clear($key);
        Auth::login($etudiant);
        $request->session()->regenerate();
        $this->journaliser($request, $etudiant, $matricule, true, 'matricule');

        return to_route('espace');
    }

    public function user(UserLoginRequest $request): RedirectResponse
    {
        $request->session()->forget('admin_mfa_user_id');
        $credentials = $request->only('email', 'password');
        $candidate = User::query()->where('email', $credentials['email'])->first();
        $success = $candidate
            && in_array($candidate->role, ['teacher', 'admin'], true)
            && $candidate->password
            && Hash::check($credentials['password'], $candidate->password);

        if (! $success) {
            $this->journaliser($request, $candidate, null, false, 'email');

            throw ValidationException::withMessages(['email' => 'Identifiants incorrects.']);
        }

        if ($candidate->role === 'admin') {
            $passphrase = config('uist2its.admin_mfa_passphrase');
            abort_unless(is_string($passphrase) && $passphrase !== '', 503, 'Le second facteur administrateur n’est pas configuré.');

            $request->session()->regenerate();
            $request->session()->put('admin_mfa_user_id', $candidate->id);

            return to_route('login');
        }

        $this->journaliser($request, $candidate, null, true, 'email');
        Auth::login($candidate);
        $request->session()->regenerate();

        if ($candidate->password_change_required) {
            return to_route('password.change');
        }

        return to_route('espace');
    }

    public function verifyAdminMfa(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'admin_passphrase' => ['required', 'string', 'max:128'],
        ]);
        $pendingAdminId = $request->session()->get('admin_mfa_user_id');
        $admin = $pendingAdminId
            ? User::query()->whereKey($pendingAdminId)->where('role', 'admin')->first()
            : null;

        if (! $admin) {
            $request->session()->forget('admin_mfa_user_id');

            throw ValidationException::withMessages([
                'admin_passphrase' => 'Veuillez recommencer la connexion.',
            ]);
        }

        $passphrase = config('uist2its.admin_mfa_passphrase');
        abort_unless(is_string($passphrase) && $passphrase !== '', 503, 'Le second facteur administrateur n’est pas configuré.');

        if (! hash_equals($passphrase, $credentials['admin_passphrase'])) {
            $this->journaliser($request, $admin, null, false, 'admin_mfa');

            throw ValidationException::withMessages([
                'admin_passphrase' => 'Code de vérification incorrect.',
            ]);
        }

        $request->session()->forget('admin_mfa_user_id');
        Auth::login($admin);
        $request->session()->regenerate();
        $this->journaliser($request, $admin, null, true, 'admin_mfa');

        if ($admin->password_change_required) {
            return to_route('admin.passwords.index');
        }

        return to_route('espace');
    }

    public function destroy(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function journaliser(Request $request, ?User $user, ?string $matricule, bool $reussie, string $type): void
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
