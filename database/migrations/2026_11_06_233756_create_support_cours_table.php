<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('support_cours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enseignant_id')->constrained('users')->cascadeOnDelete();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('chemin_fichier');
            $table->string('nom_fichier');
            $table->string('type_mime', 150);
            $table->timestamps();
        });

        Schema::create('support_cours_niveaux', function (Blueprint $table) {
            $table->foreignId('support_cours_id')->constrained('support_cours')->cascadeOnDelete();
            $table->foreignId('niveau_id')->constrained('niveaux')->cascadeOnDelete();
            $table->primary(['support_cours_id', 'niveau_id']);
            $table->index(['niveau_id', 'support_cours_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_cours_niveaux');
        Schema::dropIfExists('support_cours');
    }
};
