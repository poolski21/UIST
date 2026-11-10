<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeOwnPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PasswordChangeController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Auth/ChangePassword');
    }

    public function update(ChangeOwnPasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        DB::transaction(function () use ($request, $user): void {
            $user->forceFill([
                'password' => $request->validated('password'),
                'password_change_required' => false,
            ])->save();
        });

        return to_route('espace')->with('success', 'Votre mot de passe a été modifié.');
    }
}
