<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeAdminPasswordRequest;
use App\Models\AdminPasswordChange;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdminPasswordController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', User::class);

        return Inertia::render('Admin/Passwords', [
            'admins' => User::query()
                ->where('role', 'admin')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'mustChangePassword' => (bool) request()->user()->password_change_required,
            'generatedPassword' => request()->session()->pull('generated_admin_password'),
        ]);
    }

    public function reset(User $user): RedirectResponse
    {
        Gate::authorize('resetPassword', $user);

        $password = bin2hex(random_bytes(24));

        DB::transaction(function () use ($user, $password): void {
            $user->forceFill([
                'password' => $password,
                'password_change_required' => true,
            ])->save();

            AdminPasswordChange::create([
                'actor_id' => request()->user()->id,
                'target_admin_id' => $user->id,
                'target_email' => $user->email,
                'action' => 'reset',
            ]);
        });

        request()->session()->flash('generated_admin_password', $password);

        return to_route('admin.passwords.index')->with('success', 'Mot de passe temporaire généré. Copiez-le maintenant : il ne sera affiché qu’une seule fois.');
    }

    public function update(ChangeAdminPasswordRequest $request): RedirectResponse
    {
        $admin = $request->user();
        Gate::authorize('changePassword', $admin);
        $data = $request->validated();

        DB::transaction(function () use ($admin, $data): void {
            $admin->forceFill([
                'password' => $data['password'],
                'password_change_required' => false,
            ])->save();

            AdminPasswordChange::create([
                'actor_id' => $admin->id,
                'target_admin_id' => $admin->id,
                'target_email' => $admin->email,
                'action' => 'change',
            ]);
        });

        return to_route('admin.dashboard')->with('success', 'Votre mot de passe a été modifié.');
    }
}
