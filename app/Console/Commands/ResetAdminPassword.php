<?php

namespace App\Console\Commands;

use App\Models\AdminPasswordChange;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetAdminPassword extends Command
{
    protected $signature = 'admin:reset-password {email}';

    protected $description = 'Generate a one-time password for an administrator account';

    public function handle(): int
    {
        $email = $this->argument('email');
        $admin = User::query()
            ->where('email', $email)
            ->where('role', 'admin')
            ->first();

        if (! $admin) {
            $this->error('Aucun compte administrateur correspondant à cette adresse.');

            return self::FAILURE;
        }

        $password = bin2hex(random_bytes(24));

        DB::transaction(function () use ($admin, $email, $password): void {
            $admin->forceFill([
                'password' => $password,
                'password_change_required' => true,
            ])->save();

            AdminPasswordChange::create([
                'actor_id' => null,
                'target_admin_id' => $admin->id,
                'target_email' => $email,
                'action' => 'reset',
            ]);
        });

        $this->line('Mot de passe temporaire (à communiquer de façon sûre, affiché une seule fois) :');
        $this->line($password);

        return self::SUCCESS;
    }
}
