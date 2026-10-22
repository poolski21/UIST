<?php

namespace App\Providers;

use App\Models\Annonce;
use App\Models\Inscription;
use App\Models\Note;
use App\Models\ResultatAnnuel;
use App\Models\User;
use App\Policies\AdminPasswordPolicy;
use App\Policies\AnnoncePolicy;
use App\Policies\ClassRosterPolicy;
use App\Policies\NotePolicy;
use App\Policies\ResultatAnnuelPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Annonce::class, AnnoncePolicy::class);
        Gate::policy(Note::class, NotePolicy::class);
        Gate::policy(ResultatAnnuel::class, ResultatAnnuelPolicy::class);
        Gate::policy(User::class, AdminPasswordPolicy::class);
        Gate::policy(Inscription::class, ClassRosterPolicy::class);
    }
}
