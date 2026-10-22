<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->json('notes_td')->nullable()->after('note');
            $table->decimal('note_examen', 5, 2)->nullable()->after('notes_td');
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn(['notes_td', 'note_examen']);
        });
    }
};
