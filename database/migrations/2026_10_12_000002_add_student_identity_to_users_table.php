<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('student')->index();
            $table->string('matricule')->nullable()->unique();
            $table->unsignedBigInteger('filiere_id')->nullable()->index();
            $table->unsignedBigInteger('niveau_id')->nullable()->index();
            $table->date('date_naissance')->nullable();
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['matricule']);
            $table->dropIndex(['role']);
            $table->dropIndex(['filiere_id']);
            $table->dropIndex(['niveau_id']);
            $table->dropColumn(['role', 'matricule', 'filiere_id', 'niveau_id', 'date_naissance']);
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
