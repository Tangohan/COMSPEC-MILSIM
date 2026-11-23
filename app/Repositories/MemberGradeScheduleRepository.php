<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Changements de grade planifiés (table member_grade_schedules, étape 4990 de la file active).
 * Une seule ligne « pending » par membre (open_slot) ; la tâche automatique revendique chaque ligne par un UPDATE conditionnel.
 */
class MemberGradeScheduleRepository
{
    public const PENDING = 'pending';
    public const RUNNING = 'running';
    public const APPLIED = 'applied';
    public const CANCELLED = 'cancelled';

    private ?bool $tableReady = null;

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function tableExists(): bool
    {
        if ($this->tableReady !== null) {
            return $this->tableReady;
        }
        try {
            $this->pdo()->query('SELECT 1 FROM member_grade_schedules LIMIT 1');
            $this->tableReady = true;
        } catch (\Throwable) {
            $this->tableReady = false;
        }

        return $this->tableReady;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $tenantId): ?array
    {
        $st = $this->pdo()->prepare('SELECT * FROM member_grade_schedules WHERE id = ? AND tenant_id = ? LIMIT 1');
        $st->execute([$id, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function pendingForUser(int $tenantId, int $userId): ?array
    {
        $st = $this->pdo()->prepare(
            "SELECT * FROM member_grade_schedules WHERE tenant_id = ? AND user_id = ? AND status = 'pending' LIMIT 1"
        );
        $st->execute([$tenantId, $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @param array{from_grade_id:?int, to_grade_id:int, to_label:?string, scheduled_for:string, reason:string, actor:int, now:string} $d
     */
    public function insert(int $tenantId, int $userId, array $d): int
    {
        $st = $this->pdo()->prepare(
            "INSERT INTO member_grade_schedules
                (tenant_id, user_id, from_grade_id, to_grade_id, to_label, scheduled_for, reason, status, open_slot,
                 created_by, updated_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 1, ?, ?, ?, ?)"
        );
        $st->execute([
            $tenantId, $userId, $d['from_grade_id'], $d['to_grade_id'], $d['to_label'], $d['scheduled_for'], $d['reason'],
            $d['actor'] > 0 ? $d['actor'] : null, $d['actor'] > 0 ? $d['actor'] : null, $d['now'], $d['now'],
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /** Modifie une ligne encore « pending » ; false si elle ne l'est plus. */
    public function updatePending(int $id, int $tenantId, int $toGradeId, ?string $toLabel, string $scheduledFor, string $reason, int $actor, string $now): bool
    {
        $st = $this->pdo()->prepare(
            "UPDATE member_grade_schedules
             SET to_grade_id = ?, to_label = ?, scheduled_for = ?, reason = ?, updated_by = ?, updated_at = ?
             WHERE id = ? AND tenant_id = ? AND status = 'pending'"
        );
        $st->execute([$toGradeId, $toLabel, $scheduledFor, $reason, $actor > 0 ? $actor : null, $now, $id, $tenantId]);

        return $st->rowCount() === 1;
    }

    /** Annule une ligne encore « pending » ; false si elle ne l'est plus. */
    public function cancelPending(int $id, int $tenantId, string $reason, ?int $actor, string $now): bool
    {
        $st = $this->pdo()->prepare(
            "UPDATE member_grade_schedules
             SET status = 'cancelled', open_slot = NULL, status_reason = ?, finished_at = ?, finished_by = ?, updated_at = ?
             WHERE id = ? AND tenant_id = ? AND status = 'pending'"
        );
        $st->execute([$this->clip($reason), $now, $actor !== null && $actor > 0 ? $actor : null, $now, $id, $tenantId]);

        return $st->rowCount() === 1;
    }

    /** Revendique une ligne échue : un seul passage de la tâche gagne, les autres reçoivent false. */
    public function claim(int $id, string $now): bool
    {
        $st = $this->pdo()->prepare(
            "UPDATE member_grade_schedules SET status = 'running', open_slot = NULL, updated_at = ?
             WHERE id = ? AND status = 'pending' AND scheduled_for <= ?"
        );
        $st->execute([$now, $id, $now]);

        return $st->rowCount() === 1;
    }

    /** Termine une ligne revendiquée : 'applied' ou 'cancelled'. */
    public function finish(int $id, string $status, ?string $reason, string $now): void
    {
        $st = $this->pdo()->prepare(
            "UPDATE member_grade_schedules SET status = ?, status_reason = ?, finished_at = ?, updated_at = ?
             WHERE id = ? AND status = 'running'"
        );
        $st->execute([$status, $reason !== null ? $this->clip($reason) : null, $now, $now, $id]);
    }

    /** @return list<array<string, mixed>> lignes à appliquer, plus anciennes d'abord */
    public function listDue(string $now, int $limit = 200): array
    {
        $st = $this->pdo()->prepare(
            "SELECT * FROM member_grade_schedules WHERE status = 'pending' AND scheduled_for <= ?
             ORDER BY scheduled_for ASC, id ASC LIMIT " . max(1, $limit)
        );
        $st->execute([$now]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Prochains changements (tableau de bord), avec noms et grade actuel.
     *
     * @return list<array<string, mixed>>
     */
    public function listUpcoming(int $tenantId, int $limit = 5): array
    {
        $st = $this->pdo()->prepare(
            "SELECT s.*, u.display_name, u.callsign
             FROM member_grade_schedules s
             LEFT JOIN users u ON u.id = s.user_id
             WHERE s.tenant_id = ? AND s.status = 'pending'
             ORDER BY s.scheduled_for ASC, s.id ASC LIMIT " . max(1, $limit)
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Changements appliqués récemment.
     *
     * @return list<array<string, mixed>>
     */
    public function listRecent(int $tenantId, int $limit = 5): array
    {
        $st = $this->pdo()->prepare(
            "SELECT s.*, u.display_name, u.callsign
             FROM member_grade_schedules s
             LEFT JOIN users u ON u.id = s.user_id
             WHERE s.tenant_id = ? AND s.status = 'applied'
             ORDER BY s.finished_at DESC, s.id DESC LIMIT " . max(1, $limit)
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Dernières lignes d'un membre (à venir, appliquées, annulées).
     *
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $tenantId, int $userId, int $limit = 6): array
    {
        $st = $this->pdo()->prepare(
            'SELECT * FROM member_grade_schedules WHERE tenant_id = ? AND user_id = ?
             ORDER BY scheduled_for DESC, id DESC LIMIT ' . max(1, $limit)
        );
        $st->execute([$tenantId, $userId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function clip(string $v): string
    {
        return mb_substr(trim($v), 0, 400);
    }
}
