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
        Schema::create('historique_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained()->restrictOnDelete();
            $table->json('ancienne_valeur');
            $table->json('nouvelle_valeur');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('modifie_le');
            $table->text('motif');
            $table->index(['note_id', 'modifie_le']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historique_notes');
    }
};
