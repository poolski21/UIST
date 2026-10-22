<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filieres', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('niveaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filiere_id')->constrained()->cascadeOnDelete();
            $table->string('nom');
            $table->string('code');
            $table->unsignedSmallInteger('credits_requis')->default(60);
            $table->timestamps();
            $table->unique(['filiere_id', 'code']);
        });

        Schema::create('annees_academiques', function (Blueprint $table) {
            $table->id();
            $table->string('libelle')->unique();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->boolean('active')->default(false);
            $table->timestamps();
        });

        Schema::create('matieres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filiere_id')->constrained()->cascadeOnDelete();
            $table->foreignId('niveau_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enseignant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nom');
            $table->string('code')->unique();
            $table->unsignedSmallInteger('credits')->default(3);
            $table->decimal('coefficient', 6, 2)->default(1);
            $table->timestamps();
        });

        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etudiant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('niveau_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_academique_id')->constrained('annees_academiques')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['etudiant_id', 'annee_academique_id']);
        });

        Schema::create('emplois_du_temps', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->foreignId('filiere_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('niveau_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('date_cours');
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->string('salle')->nullable();
            $table->string('statut')->default('brouillon')->index();
            $table->foreignId('publie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('publie_le')->nullable();
            $table->timestamps();
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etudiant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_academique_id')->constrained('annees_academiques')->cascadeOnDelete();
            $table->decimal('note', 5, 2);
            $table->string('statut')->default('brouillon')->index();
            $table->foreignId('saisie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('publie_le')->nullable();
            $table->timestamps();
            $table->unique(['etudiant_id', 'matiere_id', 'annee_academique_id']);
        });

        Schema::create('resultats_annuels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etudiant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('annee_academique_id')->constrained('annees_academiques')->cascadeOnDelete();
            $table->decimal('moyenne_generale', 5, 2);
            $table->unsignedSmallInteger('credits_obtenus');
            $table->string('decision_auto');
            $table->string('decision_finale')->nullable();
            $table->foreignId('modifiee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motif')->nullable();
            $table->timestamp('publie_le')->nullable();
            $table->timestamps();
            $table->unique(['etudiant_id', 'annee_academique_id']);
        });

        Schema::create('annonces', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->longText('contenu');
            $table->string('piece_jointe')->nullable();
            $table->string('cible_type')->default('tous');
            $table->unsignedBigInteger('cible_id')->nullable();
            $table->foreignId('cree_par')->constrained('users')->cascadeOnDelete();
            $table->timestamp('publiee_le')->nullable()->index();
            $table->timestamps();
            $table->index(['cible_type', 'cible_id']);
        });

        Schema::create('categories_cours', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('cours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categorie_cours_id')->constrained('categories_cours')->cascadeOnDelete();
            $table->string('titre');
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('connexions_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('matricule')->nullable()->index();
            $table->string('type_connexion');
            $table->boolean('reussie');
            $table->string('adresse_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_modifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('element_type');
            $table->unsignedBigInteger('element_id');
            $table->json('anciennes_valeurs')->nullable();
            $table->json('nouvelles_valeurs')->nullable();
            $table->text('motif')->nullable();
            $table->timestamps();
            $table->index(['element_type', 'element_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('journal_modifications');
        Schema::dropIfExists('connexions_log');
        Schema::dropIfExists('cours');
        Schema::dropIfExists('categories_cours');
        Schema::dropIfExists('annonces');
        Schema::dropIfExists('resultats_annuels');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('emplois_du_temps');
        Schema::dropIfExists('inscriptions');
        Schema::dropIfExists('matieres');
        Schema::dropIfExists('annees_academiques');
        Schema::dropIfExists('niveaux');
        Schema::dropIfExists('filieres');
    }
};
