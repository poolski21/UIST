<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emplois_du_temps', function (Blueprint $table) {
            $table->foreignId('matiere_id')->nullable()->constrained('matieres')->nullOnDelete();
            $table->foreignId('enseignant_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('emplois_du_temps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('matiere_id');
            $table->dropConstrainedForeignId('enseignant_id');
        });
    }
};
