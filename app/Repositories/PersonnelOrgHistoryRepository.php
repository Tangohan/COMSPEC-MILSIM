<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class PersonnelOrgHistoryRepository
{
    private static ?bool $tableReady = null;

    public function __construct(
        private ?PDO $pdo = null,
    ) {
        $this->pdo ??= Database::getPdo();
    }

    public function schemaReady(): bool
    {
        if (self::$tableReady === null) {
            try {
                $st = $this->pdo->query(
                    "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_org_history' LIMIT 1"
                );
                self::$tableReady = $st && (bool) $st->fetchColumn();
            } catch (\Throwable) {
                self::$tableReady = false;
            }
        }

        return self::$tableReady;
    }

    public function append(int $tenantId, int $userId, ?int $actorUserId, string $summary): void
    {
        if (!$this->schemaReady() || $tenantId < 1 || $userId < 1) {
            return;
        }
        $s = trim($summary);
        if ($s === '') {
            return;
        }
        if (function_exists('mb_strlen') && mb_strlen($s) > 600) {
            $s = mb_substr($s, 0, 597) . '…';
        } elseif (strlen($s) > 600) {
            $s = substr($s, 0, 597) . '…';
        }
        $st = $this->pdo->prepare(
            'INSERT INTO personnel_org_history (tenant_id, user_id, actor_user_id, summary) VALUES (?,?,?,?)'
        );
        $st->execute([$tenantId, $userId, $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null, $s]);
    }

    /**
     * @return list<array{id: int|string, user_id: int|string, actor_user_id: ?int, summary: string, created_at: string}>
     */
    public function listForUser(int $tenantId, int $userId, int $limit = 30): array
    {
        if (!$this->schemaReady() || $tenantId < 1 || $userId < 1) {
            return [];
        }
        $lim = max(1, min(100, $limit));
        $st = $this->pdo->prepare(
            "SELECT id, user_id, actor_user_id, summary, created_at
             FROM personnel_org_history
             WHERE tenant_id = ? AND user_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT {$lim}"
        );
        $st->execute([$tenantId, $userId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Date (Y-m-d) du dernier changement de grade enregistré dans l’historique du dossier (résumé « Grade : »).
     */
    public function latestGradeChangeDateYmd(int $tenantId, int $userId): ?string
    {
        if (!$this->schemaReady() || $tenantId < 1 || $userId < 1) {
            return null;
        }
        $st = $this->pdo->prepare(
            "SELECT DATE(created_at) AS d
             FROM personnel_org_history
             WHERE tenant_id = ? AND user_id = ?
               AND summary LIKE 'Grade :%'
             ORDER BY created_at DESC, id DESC
             LIMIT 1"
        );
        $st->execute([$tenantId, $userId]);
        $v = $st->fetchColumn();
        if ($v === false || $v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s !== '' ? $s : null;
    }
}
