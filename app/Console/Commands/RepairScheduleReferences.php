<?php

namespace App\Console\Commands;

use App\Models\EmploiDuTemps;
use App\Models\Matiere;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[Signature('uist2its:repair-schedule-references
    {--teacher-1= : Enseignant de la matière 1 (ID enseignant ou null)}
    {--teacher-2= : Enseignant de la matière 2 (ID enseignant ou null)}
    {--orphan-1=keep : Conserver, supprimer, ou rattacher avec attach:ID_MATIERE}
    {--orphan-2=keep : Conserver, supprimer, ou rattacher avec attach:ID_MATIERE}')]
#[Description('Prévisualiser puis confirmer les corrections de références aux matières et enseignants')]
class RepairScheduleReferences extends Command
{
    /**
     * @var array<int, array{action: string, subject_id: int|null, fingerprint: string|null}>
     */
    private array $orphanPlan = [];

    /**
     * @var array<int, array{from: int|null, to: int|null}>
     */
    private array $teacherPlan = [];

    public function handle(): int
    {
        $subjects = Matiere::query()
            ->with('enseignant')
            ->whereIn('id', [1, 2])
            ->get()
            ->keyBy('id');

        foreach ([1, 2] as $subjectId) {
            if (! $subjects->has($subjectId)) {
                $this->error("La matière #{$subjectId} est introuvable ; aucune modification effectuée.");

                return self::FAILURE;
            }

            $teacher = $this->resolveTeacher($subjectId, $subjects);
            if ($teacher === false) {
                return self::FAILURE;
            }

            $this->teacherPlan[$subjectId] = [
                'from' => $this->nullableId($subjects[$subjectId]->enseignant_id),
                'to' => $teacher?->id,
            ];
        }

        $sessions = EmploiDuTemps::query()
            ->with(['filiere', 'niveau'])
            ->whereIn('id', [1, 2])
            ->get()
            ->keyBy('id');

        foreach ([1, 2] as $sessionId) {
            $action = strtolower(trim((string) $this->option("orphan-{$sessionId}")));
            $matches = [];
            $this->orphanPlan[$sessionId] = [
                'action' => $action,
                'subject_id' => null,
                'fingerprint' => null,
            ];

            if ($action === 'keep') {
                continue;
            }

            if (! in_array($action, ['delete'], true) && preg_match('/^attach:(\d+)$/', $action, $matches) !== 1) {
                $this->error("Action invalide pour la séance #{$sessionId}. Choisissez keep, delete ou attach:ID_MATIERE.");

                return self::FAILURE;
            }

            $session = $sessions->get($sessionId);
            if (! $session) {
                $this->error("La séance #{$sessionId} n’existe plus ; aucune modification effectuée.");

                return self::FAILURE;
            }

            if ($session->matiere_id !== null) {
                $this->error("La séance #{$sessionId} n’est plus orpheline ; aucune modification effectuée.");

                return self::FAILURE;
            }

            $targetSubjectId = isset($matches[1]) ? (int) $matches[1] : null;
            if ($targetSubjectId !== null) {
                $targetSubject = Matiere::query()->find($targetSubjectId);
                if (! $targetSubject) {
                    $this->error("La matière cible #{$targetSubjectId} est introuvable ; aucune modification effectuée.");

                    return self::FAILURE;
                }

                if (
                    (string) $session->filiere_id !== (string) $targetSubject->filiere_id
                    || (string) $session->niveau_id !== (string) $targetSubject->niveau_id
                ) {
                    $this->error("La matière #{$targetSubjectId} ne correspond pas à la filière et au niveau de la séance #{$sessionId} ; aucune modification effectuée.");

                    return self::FAILURE;
                }
            }

            $this->orphanPlan[$sessionId] = [
                'action' => $action === 'delete' ? 'delete' : 'attach',
                'subject_id' => $targetSubjectId,
                'fingerprint' => $this->scheduleFingerprint($session),
            ];
        }

        $this->displayPlan($subjects, $sessions);

        if (! $this->hasChanges()) {
            $this->info('Aucune modification à appliquer.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Appliquer exactement les modifications affichées ?', false)) {
            $this->info('Aucune modification effectuée.');

            return self::SUCCESS;
        }

        DB::transaction(function (): void {
            foreach ($this->teacherPlan as $subjectId => $plan) {
                if ($plan['from'] === $plan['to']) {
                    continue;
                }

                $subject = Matiere::query()->lockForUpdate()->findOrFail($subjectId);
                if ($this->nullableId($subject->enseignant_id) !== $plan['from']) {
                    throw new RuntimeException("L’affectation de la matière #{$subjectId} a changé depuis l’aperçu.");
                }

                $subject->update(['enseignant_id' => $plan['to']]);
            }

            foreach ($this->orphanPlan as $sessionId => $plan) {
                if ($plan['action'] === 'keep') {
                    continue;
                }

                $session = EmploiDuTemps::query()->lockForUpdate()->findOrFail($sessionId);
                if (
                    $session->matiere_id !== null
                    || $this->scheduleFingerprint($session) !== $plan['fingerprint']
                ) {
                    throw new RuntimeException("La séance #{$sessionId} a changé depuis l’aperçu.");
                }

                if ($plan['action'] === 'delete') {
                    $session->delete();

                    continue;
                }

                $subject = Matiere::query()->lockForUpdate()->findOrFail($plan['subject_id']);
                if (
                    (string) $session->filiere_id !== (string) $subject->filiere_id
                    || (string) $session->niveau_id !== (string) $subject->niveau_id
                ) {
                    throw new RuntimeException("La matière cible de la séance #{$sessionId} ne correspond plus à sa classe.");
                }

                $session->update(['matiere_id' => $subject->id]);
            }
        });

        $this->info('Corrections appliquées avec succès.');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Matiere>  $subjects
     */
    private function resolveTeacher(int $subjectId, Collection $subjects): User|false|null
    {
        $option = $this->option("teacher-{$subjectId}");
        if (! is_string($option) || trim($option) === '') {
            $this->error("Précisez --teacher-{$subjectId}=ID ou --teacher-{$subjectId}=null.");

            return false;
        }

        $option = trim($option);
        if (strtolower($option) === 'null') {
            return null;
        }

        if (filter_var($option, FILTER_VALIDATE_INT) === false) {
            $this->error("L’option --teacher-{$subjectId} doit être un identifiant numérique ou null.");

            return false;
        }

        $teacher = User::query()
            ->whereKey((int) $option)
            ->where('role', 'teacher')
            ->first();

        if (! $teacher) {
            $this->error("L’utilisateur #{$option} n’existe pas ou n’a pas le rôle enseignant.");

            return false;
        }

        return $teacher;
    }

    /**
     * @param  Collection<int, Matiere>  $subjects
     * @param  Collection<int, EmploiDuTemps>  $sessions
     */
    private function displayPlan(Collection $subjects, Collection $sessions): void
    {
        $this->info('Aperçu — aucune donnée n’a encore été modifiée :');

        foreach ($this->teacherPlan as $subjectId => $plan) {
            $subject = $subjects[$subjectId];
            $currentTeacher = $subject->enseignant?->name
                ?? ($plan['from'] === null ? 'aucun' : 'ID '.$plan['from']);
            $targetTeacher = $plan['to'] === null
                ? 'aucun (NULL)'
                : User::query()->whereKey($plan['to'])->value('name').' (ID '.$plan['to'].')';

            $this->line("Matière #{$subjectId} — {$subject->nom} : {$currentTeacher} → {$targetTeacher}");
        }

        foreach ($this->orphanPlan as $sessionId => $plan) {
            $session = $sessions->get($sessionId);

            if ($plan['action'] === 'keep') {
                $this->line("Séance orpheline #{$sessionId} : conservée en l’état.");

                continue;
            }

            if ($plan['action'] === 'delete') {
                $this->warn('SUPPRESSION définitive : séance #'.$sessionId.' — '.$this->describeSchedule($session));

                continue;
            }

            $subject = Matiere::query()->findOrFail($plan['subject_id']);
            $this->line("Séance orpheline #{$sessionId} — {$this->describeSchedule($session)} : rattacher à matière #{$subject->id} — {$subject->nom}");
        }
    }

    private function hasChanges(): bool
    {
        foreach ($this->teacherPlan as $plan) {
            if ($plan['from'] !== $plan['to']) {
                return true;
            }
        }

        foreach ($this->orphanPlan as $plan) {
            if ($plan['action'] !== 'keep') {
                return true;
            }
        }

        return false;
    }

    private function describeSchedule(EmploiDuTemps $session): string
    {
        $class = collect([$session->filiere?->nom, $session->niveau?->nom])
            ->filter()
            ->implode(' · ');

        return 'titre='.$session->titre
            .'; classe='.($class !== '' ? $class : 'non définie')
            .'; date='.$session->date_cours?->toDateString()
            .'; horaires='.substr((string) $session->heure_debut, 0, 5)
            .'–'.substr((string) $session->heure_fin, 0, 5)
            .'; salle='.($session->salle ?: 'sans salle')
            .'; description='.($session->description ?: 'vide')
            .'; créé le='.$session->created_at?->toDateTimeString()
            .'; enseignant_id='.($session->enseignant_id ?? 'NULL')
            .'; statut='.$session->statut;
    }

    private function scheduleFingerprint(EmploiDuTemps $session): string
    {
        return hash('sha256', json_encode([
            $session->titre,
            $session->date_cours?->toDateString(),
            $session->heure_debut,
            $session->heure_fin,
            $session->salle,
            $session->description,
            $session->filiere_id,
            $session->niveau_id,
            $session->created_at?->toISOString(),
        ], JSON_THROW_ON_ERROR));
    }

    private function nullableId(mixed $id): ?int
    {
        return $id === null ? null : (int) $id;
    }
}
