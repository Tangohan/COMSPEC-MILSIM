<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TrainingStaffPingRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function log(int $tenantId, int $enrollmentId, int $moduleId, string $kind = 'module_blocked'): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO training_staff_ping_log (tenant_id, enrollment_id, module_id, ping_kind) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$tenantId, $enrollmentId, $moduleId, $kind]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return;
            }
            throw $e;
        }
    }

    /** Secondes depuis le dernier ping du même type, ou null si aucun. */
    public function secondsSinceLastPing(int $enrollmentId, int $moduleId, string $kind = 'module_blocked'): ?int
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT created_at FROM training_staff_ping_log WHERE enrollment_id = ? AND module_id = ? AND ping_kind = ? ORDER BY created_at DESC LIMIT 1'
            );
            $stmt->execute([$enrollmentId, $moduleId, $kind]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || empty($row['created_at'])) {
                return null;
            }
            $t = strtotime((string) $row['created_at']);

            return $t ? max(0, time() - $t) : null;
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Dernier ping (en secondes écoulées) pour un même module_id, groupé par enrollment_id — une seule requête
     * pour un lot d'identifiants au lieu d'un appel à secondsSinceLastPing() par ligne.
     *
     * @param list<int> $enrollmentIds
     * @return array<int, int> enrollment_id => secondes écoulées depuis le dernier ping
     */
    public function secondsSinceLastPingBatch(array $enrollmentIds, int $moduleId, string $kind = 'module_blocked'): array
    {
        $enrollmentIds = array_values(array_unique(array_filter(array_map('intval', $enrollmentIds), static fn (int $id): bool => $id > 0)));
        if ($enrollmentIds === [] || $moduleId < 1) {
            return [];
        }
        try {
            $ph = implode(',', array_fill(0, count($enrollmentIds), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT enrollment_id, MAX(created_at) AS last_ping
                 FROM training_staff_ping_log
                 WHERE enrollment_id IN ({$ph}) AND module_id = ? AND ping_kind = ?
                 GROUP BY enrollment_id"
            );
            $stmt->execute([...$enrollmentIds, $moduleId, $kind]);
            $out = [];
            $now = time();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $eid = (int) ($row['enrollment_id'] ?? 0);
                $t = strtotime((string) ($row['last_ping'] ?? ''));
                if ($eid > 0 && $t) {
                    $out[$eid] = max(0, $now - $t);
                }
            }

            return $out;
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return [];
            }
            throw $e;
        }
    }
}
