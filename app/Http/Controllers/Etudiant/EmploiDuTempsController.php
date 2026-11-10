<?php

namespace App\Http\Controllers\Etudiant;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmploiDuTempsResource;
use App\Models\EmploiDuTemps;
use App\Models\Matiere;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmploiDuTempsController extends Controller
{
    public function index(Request $request): Response
    {
        $student = $request->user();
        $filters = $request->validate([
            'week_start' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $weekStart = Carbon::parse($filters['week_start'] ?? now())
            ->startOfWeek(Carbon::MONDAY)
            ->startOfDay();
        $weekEnd = $weekStart->copy()->addDays(5);

        $schedule = EmploiDuTemps::query()
            ->with(['filiere', 'niveau', 'matiere.enseignant', 'enseignant'])
            ->published()
            ->whereDate('date_cours', '>=', $weekStart->toDateString())
            ->whereDate('date_cours', '<=', $weekEnd->toDateString())
            ->whereNotNull('filiere_id')
            ->whereNotNull('niveau_id')
            ->where('filiere_id', $student->filiere_id)
            ->where('niveau_id', $student->niveau_id)
            ->orderBy('date_cours')
            ->orderBy('heure_debut')
            ->get();
        $colorIndexes = Matiere::colorIndexesById();
        $schedule->each(fn (EmploiDuTemps $course) => $course->setAttribute(
            'subject_color_index',
            $colorIndexes[$course->matiere_id] ?? null,
        ));

        return Inertia::render('Student/Emplois', [
            'emploiDuTemps' => EmploiDuTempsResource::collection($schedule)->resolve(),
            'week' => [$weekStart->format('d/m/Y'), $weekEnd->format('d/m/Y')],
            'weekStart' => $weekStart->toDateString(),
        ]);
    }
}
