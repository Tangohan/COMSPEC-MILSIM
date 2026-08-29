<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Vivier / succession : readiness pour postes de responsabilité.
 */
final class PersonnelSuccessionRepository
{
    /** @var list<string> */
    public const READINESS = ['ready_now', 'ready_3m', 'develop'];

    /** @var array<string, string> */
    public const READINESS_LABELS = [
        'ready_now' => 'Prêt maintenant',
        'ready_3m' => 'Prêt sous 3 mois',
        'develop' => 'À développer',
    ];

    /** @var list<string> */
    public const DEFAULT_ROLE_LABELS = [
        'Chef d’équipe',
        'Chef de groupe',
        'Instructeur',
        'Adjoint de section',
        'Chef de section',
    ];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function tableExists(): bool
    {
        $st = $this->pdo->query(
            "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_succession_entries' LIMIT 1"
        );

        return (bool) ($st && $st->fetchColumn());
    }

    public function upsert(
        int $tenantId,
        int $userId,
        string $targetRoleLabel,
        string $readiness,
        ?string $notes,
        ?int $assessedBy,
        ?int $targetJobRoleId = null
    ): int {
        if (!$this->tableExists()) {
            return 0;
        }
        $targetRoleLabel = trim($targetRoleLabel);
        if ($targetRoleLabel === '') {
            return 0;
        }
        $readiness = in_array($readiness, self::READINESS, true) ? $readiness : 'develop';

        $st = $this->pdo->prepare(
            'SELECT id FROM personnel_succession_entries
             WHERE tenant_id = ? AND user_id = ? AND target_role_label = ? AND is_active = 1
             LIMIT 1'
        );
        $st->execute([$tenantId, $userId, mb_substr($targetRoleLabel, 0, 120)]);
        $existingId = (int) ($st->fetchColumn() ?: 0);

        if ($existingId > 0) {
            $up = $this->pdo->prepare(
                'UPDATE personnel_succession_entries
                 SET readiness = ?, notes = ?, assessed_by = ?, assessed_at = NOW(),
                     target_job_role_id = ?, updated_at = NOW()
                 WHERE id = ? AND tenant_id = ?'
            );
            $up->execute([
                $readiness,
                $notes !== null && trim($notes) !== '' ? trim($notes) : null,
                $assessedBy,
                $targetJobRoleId && $targetJobRoleId > 0 ? $targetJobRoleId : null,
                $existingId,
                $tenantId,
            ]);

            return $existingId;
        }

        $ins = $this->pdo->prepare(
            'INSERT INTO personnel_succession_entries
             (tenant_id, user_id, target_role_label, target_job_role_id, readiness, notes, assessed_by, assessed_at, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 1, NOW())'
        );
        $ins->execute([
            $tenantId,
            $userId,
            mb_substr($targetRoleLabel, 0, 120),
            $targetJobRoleId && $targetJobRoleId > 0 ? $targetJobRoleId : null,
            $readiness,
            $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            $assessedBy,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function deactivate(int $id, int $tenantId): bool
    {
        if (!$this->tableExists() || $id < 1) {
            return false;
        }
        $st = $this->pdo->prepare(
            'UPDATE personnel_succession_entries SET is_active = 0, updated_at = NOW() WHERE id = ? AND tenant_id = ?'
        );
        $st->execute([$id, $tenantId]);

        return $st->rowCount() > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveForTenant(int $tenantId, ?string $readiness = null, int $limit = 100): array
    {
        if (!$this->tableExists() || $tenantId < 1) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $where = 's.tenant_id = ? AND s.is_active = 1';
        $params = [$tenantId];
        if ($readiness !== null && in_array($readiness, self::READINESS, true)) {
            $where .= ' AND s.readiness = ?';
            $params[] = $readiness;
        }
        $st = $this->pdo->prepare(
            "SELECT s.*, u.display_name AS user_display_name, u.email AS user_email, u.callsign AS user_callsign
             FROM personnel_succession_entries s
             LEFT JOIN users u ON u.id = s.user_id
             WHERE {$where}
             ORDER BY FIELD(s.readiness, 'ready_now', 'ready_3m', 'develop'), s.target_role_label ASC, u.display_name ASC
             LIMIT {$limit}"
        );
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array{ready_now:int,ready_3m:int,develop:int,total:int}
     */
    public function countsByReadiness(int $tenantId): array
    {
        $out = ['ready_now' => 0, 'ready_3m' => 0, 'develop' => 0, 'total' => 0];
        if (!$this->tableExists() || $tenantId < 1) {
            return $out;
        }
        $st = $this->pdo->prepare(
            'SELECT readiness, COUNT(*) AS c FROM personnel_succession_entries
             WHERE tenant_id = ? AND is_active = 1 GROUP BY readiness'
        );
        $st->execute([$tenantId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $key = (string) ($row['readiness'] ?? '');
            $c = (int) ($row['c'] ?? 0);
            if (isset($out[$key])) {
                $out[$key] = $c;
                $out['total'] += $c;
            }
        }

        return $out;
    }
}
