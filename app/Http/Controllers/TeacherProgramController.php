<?php

namespace App\Http\Controllers;

use App\Http\Resources\EmploiDuTempsResource;
use App\Models\EmploiDuTemps;
use App\Models\Matiere;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeacherProgramController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filters = $request->validate([
            'week_start' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $weekStart = Carbon::parse($filters['week_start'] ?? now())
            ->startOfWeek(Carbon::MONDAY)
            ->startOfDay();
        $weekEnd = $weekStart->copy()->addDays(5);
        $teacherId = $request->user()->getAuthIdentifier();

        $schedule = EmploiDuTemps::query()
            ->with(['filiere', 'niveau', 'matiere.enseignant', 'enseignant'])
            ->published()
            ->whereDate('date_cours', '>=', $weekStart->toDateString())
            ->whereDate('date_cours', '<=', $weekEnd->toDateString())
            ->where(function (Builder $query) use ($teacherId): void {
                $query->where('enseignant_id', $teacherId)
                    ->orWhere(function (Builder $query) use ($teacherId): void {
                        $query->whereNull('enseignant_id')
                            ->whereHas('matiere', fn (Builder $subjectQuery) => $subjectQuery
                                ->where('enseignant_id', $teacherId));
                    });
            })
            ->orderBy('date_cours')
            ->orderBy('heure_debut')
            ->get();
        $colorIndexes = Matiere::colorIndexesById();
        $schedule->each(fn (EmploiDuTemps $course) => $course->setAttribute(
            'subject_color_index',
            $colorIndexes[$course->matiere_id] ?? null,
        ));

        return Inertia::render('Teacher/Programme', [
            'emploiDuTemps' => EmploiDuTempsResource::collection($schedule)->resolve(),
            'weekStart' => $weekStart->toDateString(),
        ]);
    }
}
