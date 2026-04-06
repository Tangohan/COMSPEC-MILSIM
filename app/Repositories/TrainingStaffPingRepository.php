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
}
