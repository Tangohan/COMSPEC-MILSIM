<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\GradeRepository;
use App\Repositories\MemberGradeChangeRepository;
use App\Repositories\MemberGradeScheduleRepository;
use App\Repositories\UserRepository;
use App\Support\GradeSelectOptions;
use Closure;
use RuntimeException;

/**
 * Changements de grade planifiés à une date et une heure.
 *
 * Planifier, modifier, annuler : mêmes droits que le changement de grade immédiat (contrôlés par le contrôleur).
 * Appliquer : tâche automatique member_grade_schedule, idempotente (revendication conditionnelle de la ligne), journalisée
 * comme un changement de grade (member_grade_changes, origine « planifié », auteur = personne qui a planifié).
 */
final class MemberGradeScheduleService
{
    /** @var Closure(): int */
    private Closure $clock;

    public function __construct(
        private MemberGradeScheduleRepository $schedules,
        private UserRepository $users,
        private GradeRepository $grades,
        private ?PersonnelStructureChangeNotificationService $notifier = null,
        private ?PersonnelServiceHistoryWriter $serviceHistory = null,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function available(): bool
    {
        return $this->schedules->tableExists();
    }

    /**
     * Date et heure saisies (Y-m-d, H:i) en datetime SQL ; heure absente : 08:00. Doit être dans le futur.
     *
     * @throws RuntimeException
     */
    public function parseFuture(string $date, string $time): string
    {
        $date = trim($date);
        $time = trim($time) === '' ? '08:00' : trim($time);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
            throw new RuntimeException('Date ou heure invalide.');
        }
        $ts = strtotime($date . ' ' . $time . ':00');
        if ($ts === false || date('Y-m-d', $ts) !== $date) {
            throw new RuntimeException('Date ou heure invalide.');
        }
        if ($ts <= ($this->clock)()) {
            throw new RuntimeException('La date doit être dans le futur.');
        }

        return date('Y-m-d H:i:s', $ts);
    }

    /**
     * @throws RuntimeException message affichable
     */
    public function schedule(int $tenantId, int $userId, int $gradeId, string $date, string $time, string $reason, int $actorId): int
    {
        $this->assertReady();
        $when = $this->parseFuture($date, $time);
        $reason = $this->cleanReason($reason);
        $grade = $this->tenantGrade($tenantId, $gradeId);
        $user = $this->users->findById($userId, $tenantId);
        if ($user === null) {
            throw new RuntimeException('Membre introuvable.');
        }
        if ($this->schedules->pendingForUser($tenantId, $userId) !== null) {
            throw new RuntimeException('Un changement de grade est déjà planifié pour ce membre : modifiez-le ou annulez-le.');
        }
        $from = (int) ($user['grade_id'] ?? 0);
        if ($from === $gradeId) {
            throw new RuntimeException('Ce membre a déjà ce grade.');
        }
        try {
            return $this->schedules->insert($tenantId, $userId, [
                'from_grade_id' => $from > 0 ? $from : null,
                'to_grade_id' => $gradeId,
                'to_label' => GradeSelectOptions::label($grade),
                'scheduled_for' => $when,
                'reason' => $reason,
                'actor' => $actorId,
                'now' => $this->now(),
            ]);
        } catch (\PDOException) {
            // Unicité (une seule ligne à venir par membre) : deux demandes simultanées.
            throw new RuntimeException('Un changement de grade est déjà planifié pour ce membre : modifiez-le ou annulez-le.');
        }
    }

    /**
     * @throws RuntimeException
     */
    public function update(int $tenantId, int $scheduleId, int $gradeId, string $date, string $time, string $reason, int $actorId): void
    {
        $this->assertReady();
        $row = $this->requireEditable($tenantId, $scheduleId);
        $when = $this->parseFuture($date, $time);
        $reason = $this->cleanReason($reason);
        $grade = $this->tenantGrade($tenantId, $gradeId);
        if (!$this->schedules->updatePending($scheduleId, $tenantId, $gradeId, GradeSelectOptions::label($grade), $when, $reason, $actorId, $this->now())) {
            throw new RuntimeException('Ce changement de grade n’est plus modifiable.');
        }
        unset($row);
    }

    /**
     * @throws RuntimeException
     */
    public function cancel(int $tenantId, int $scheduleId, int $actorId, string $reason = ''): void
    {
        $this->assertReady();
        $this->requireEditable($tenantId, $scheduleId);
        $reason = trim($reason) !== '' ? $reason : 'Annulé par un responsable.';
        if (!$this->schedules->cancelPending($scheduleId, $tenantId, $reason, $actorId, $this->now())) {
            throw new RuntimeException('Ce changement de grade n’est plus annulable.');
        }
    }

    /**
     * Applique les changements échus. Sans erreur : une ligne impossible passe en « annulé » avec sa raison.
     *
     * @return array{applied:int, cancelled:int, skipped:int}
     */
    public function applyDue(): array
    {
        $out = ['applied' => 0, 'cancelled' => 0, 'skipped' => 0];
        if (!$this->available()) {
            return $out;
        }
        foreach ($this->schedules->listDue($this->now()) as $row) {
            $id = (int) $row['id'];
            if (!$this->schedules->claim($id, $this->now())) {
                $out['skipped']++;
                continue;
            }
            try {
                $result = $this->applyOne($row);
            } catch (\Throwable $e) {
                error_log('[member_grade_schedule] ligne ' . $id . ' : ' . $e->getMessage());
                $this->schedules->finish($id, MemberGradeScheduleRepository::CANCELLED, 'Application impossible : erreur technique.', $this->now());
                $out['cancelled']++;
                continue;
            }
            $out[$result]++;
        }

        return $out;
    }

    /**
     * Prochains changements pour le tableau de bord.
     *
     * @return list<array<string, mixed>>
     */
    public function upcoming(int $tenantId, int $limit = 5): array
    {
        return $this->decorate($tenantId, $this->available() ? $this->schedules->listUpcoming($tenantId, $limit) : []);
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $tenantId, int $limit = 5): array
    {
        return $this->decorate($tenantId, $this->available() ? $this->schedules->listRecent($tenantId, $limit) : []);
    }

    /** @return list<array<string, mixed>> */
    public function forMember(int $tenantId, int $userId): array
    {
        return $this->available() ? $this->schedules->listForUser($tenantId, $userId) : [];
    }

    /**
     * Date relative : 'today', 'tomorrow' ou 'days' (avec le nombre de jours).
     *
     * @return array{kind:string, days:int}
     */
    public function relative(string $scheduledFor): array
    {
        $ts = strtotime($scheduledFor);
        if ($ts === false) {
            return ['kind' => 'days', 'days' => 0];
        }
        $days = (int) round((strtotime(date('Y-m-d', $ts)) - strtotime(date('Y-m-d', ($this->clock)()))) / 86400);
        if ($days <= 0) {
            return ['kind' => 'today', 'days' => 0];
        }

        return $days === 1 ? ['kind' => 'tomorrow', 'days' => 1] : ['kind' => 'days', 'days' => $days];
    }

    /**
     * @param array<string, mixed> $row
     * @return 'applied'|'cancelled'
     */
    private function applyOne(array $row): string
    {
        $id = (int) $row['id'];
        $tenantId = (int) $row['tenant_id'];
        $userId = (int) $row['user_id'];
        $gradeId = (int) $row['to_grade_id'];
        $actor = (int) ($row['created_by'] ?? 0);

        $user = $this->users->findById($userId, $tenantId);
        if ($user === null) {
            $this->schedules->finish($id, MemberGradeScheduleRepository::CANCELLED, 'Le membre a quitté la communauté.', $this->now());

            return 'cancelled';
        }
        $grade = $this->findTenantGrade($tenantId, $gradeId);
        if ($grade === null) {
            $this->schedules->finish($id, MemberGradeScheduleRepository::CANCELLED, 'Le grade visé n’existe plus.', $this->now());

            return 'cancelled';
        }
        $before = (int) ($user['grade_id'] ?? 0);
        if ($before === $gradeId) {
            $this->schedules->finish($id, MemberGradeScheduleRepository::APPLIED, 'Le membre avait déjà ce grade.', $this->now());

            return 'applied';
        }

        $snapshot = $this->notifier?->snapshot($tenantId, $userId);
        MemberGradeChangeRepository::withSource(
            MemberGradeChangeRepository::SOURCE_SCHEDULED,
            fn () => $this->users->update($userId, $tenantId, ['grade_id' => $gradeId]),
            $actor
        );
        $this->schedules->finish($id, MemberGradeScheduleRepository::APPLIED, null, $this->now());

        // Les retours (e-mail, journal du dossier) ne doivent jamais défaire le changement.
        try {
            if ($snapshot !== null) {
                $this->notifier?->notifyFromSnapshots($tenantId, $userId, $actor > 0 ? $actor : null, $snapshot, $this->notifier->snapshot($tenantId, $userId));
            }
            $label = GradeSelectOptions::label($grade);
            $this->serviceHistory?->record(
                $userId,
                'promotion',
                'Grade planifié : ' . $label,
                'Motif : ' . trim((string) ($row['reason'] ?? '')),
                substr((string) $row['scheduled_for'], 0, 10),
                $actor > 0 ? $actor : null,
                'Changement planifié'
            );
        } catch (\Throwable) {
        }

        return 'applied';
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function decorate(int $tenantId, array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $userId = (int) $row['user_id'];
            $user = $this->users->findById($userId, $tenantId);
            if ($user === null) {
                continue;
            }
            $currentId = (int) ($user['grade_id'] ?? 0);
            $current = $currentId > 0 ? $this->findTenantGrade($tenantId, $currentId) : null;
            $target = $this->findTenantGrade($tenantId, (int) $row['to_grade_id']);
            $name = trim((string) ($row['display_name'] ?? '')) ?: trim((string) ($row['callsign'] ?? ''));
            $out[] = [
                'id' => (int) $row['id'],
                'user_id' => $userId,
                'name' => $name !== '' ? $name : trim((string) ($user['display_name'] ?? '')),
                'from_label' => $current !== null ? GradeSelectOptions::label($current) : '',
                'to_label' => $target !== null ? GradeSelectOptions::label($target) : (string) ($row['to_label'] ?? ''),
                'scheduled_for' => (string) $row['scheduled_for'],
                'finished_at' => (string) ($row['finished_at'] ?? ''),
                'relative' => $this->relative((string) $row['scheduled_for']),
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function tenantGrade(int $tenantId, int $gradeId): array
    {
        $grade = $this->findTenantGrade($tenantId, $gradeId);
        if ($grade === null) {
            throw new RuntimeException('Ce grade n’appartient pas à la communauté.');
        }

        return $grade;
    }

    /** @return array<string, mixed>|null */
    private function findTenantGrade(int $tenantId, int $gradeId): ?array
    {
        if ($gradeId < 1) {
            return null;
        }
        foreach ($this->grades->listForTenant($tenantId) as $g) {
            if ((int) ($g['id'] ?? 0) === $gradeId) {
                return $g;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function requireEditable(int $tenantId, int $scheduleId): array
    {
        $row = $this->schedules->find($scheduleId, $tenantId);
        if ($row === null || (string) $row['status'] !== MemberGradeScheduleRepository::PENDING) {
            throw new RuntimeException('Ce changement de grade n’est plus à venir.');
        }
        if (strtotime((string) $row['scheduled_for']) <= ($this->clock)()) {
            throw new RuntimeException('La date est passée : le changement va être appliqué.');
        }

        return $row;
    }

    private function cleanReason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Indiquez le motif du changement.');
        }

        return mb_substr($reason, 0, 400);
    }

    private function assertReady(): void
    {
        if (!$this->available()) {
            throw new RuntimeException('La planification n’est pas encore disponible : lancez les mises à jour du schéma.');
        }
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s', ($this->clock)());
    }
}
