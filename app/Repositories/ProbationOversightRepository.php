<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SqlText;
use PDO;

/**
 * Suivi des membres en période d’essai (rôle `probation`) au-delà d’un seuil de jours.
 */
final class ProbationOversightRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private static ?bool $hasTenantUserRolesTable = null;

    private function hasTenantUserRolesTable(): bool
    {
        if (self::$hasTenantUserRolesTable === null) {
            try {
                $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_user_roles' LIMIT 1");
                self::$hasTenantUserRolesTable = $stmt && (bool) $stmt->fetchColumn();
            } catch (\Throwable) {
                self::$hasTenantUserRolesTable = false;
            }
        }

        return self::$hasTenantUserRolesTable;
    }

    /**
     * Dossiers en période d’essai (rôle `probation`) depuis au moins `$days` jours.
     *
     * @return list<array{user_id: int, first_name: string, last_name: string, display_name: string, started_at: string, age_days: int}>
     */
    public function listOverdue(int $tenantId, int $days, int $limit = 5): array
    {
        if (!$this->hasTenantUserRolesTable() || $tenantId < 1 || $days < 1) {
            return [];
        }
        $lim = max(1, min(25, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT u.id AS user_id, u.display_name,
                    up.first_name, up.last_name,
                    COALESCE(tur.valid_from, tur.created_at) AS started_at,
                    TIMESTAMPDIFF(DAY, COALESCE(tur.valid_from, tur.created_at), NOW()) AS age_days
             FROM tenant_user_roles tur
             INNER JOIN roles r ON r.id = tur.role_id AND r.tenant_id = tur.tenant_id
             INNER JOIN users u ON u.id = tur.user_id AND u.tenant_id = tur.tenant_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE tur.tenant_id = ?
               AND tur.org_unit_id IS NULL
               AND ' . SqlText::equalsLiteral($this->pdo, 'r.slug', 'probation') . '
               AND ' . SqlText::equalsLiteral($this->pdo, 'u.status', 'active') . '
               AND COALESCE(tur.valid_from, tur.created_at) IS NOT NULL
               AND COALESCE(tur.valid_from, tur.created_at) <= DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY started_at ASC
             LIMIT {$lim}"
        );
        $stmt->execute([$tenantId, $days]);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[] = [
                'user_id' => (int) ($row['user_id'] ?? 0),
                'first_name' => (string) ($row['first_name'] ?? ''),
                'last_name' => (string) ($row['last_name'] ?? ''),
                'display_name' => (string) ($row['display_name'] ?? ''),
                'started_at' => (string) ($row['started_at'] ?? ''),
                'age_days' => max($days, (int) ($row['age_days'] ?? $days)),
            ];
        }

        return $out;
    }

    public function countOverdue(int $tenantId, int $days): int
    {
        if (!$this->hasTenantUserRolesTable() || $tenantId < 1 || $days < 1) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM tenant_user_roles tur
             INNER JOIN roles r ON r.id = tur.role_id AND r.tenant_id = tur.tenant_id
             INNER JOIN users u ON u.id = tur.user_id AND u.tenant_id = tur.tenant_id
             WHERE tur.tenant_id = ?
               AND tur.org_unit_id IS NULL
               AND ' . SqlText::equalsLiteral($this->pdo, 'r.slug', 'probation') . '
               AND ' . SqlText::equalsLiteral($this->pdo, 'u.status', 'active') . '
               AND COALESCE(tur.valid_from, tur.created_at) IS NOT NULL
               AND COALESCE(tur.valid_from, tur.created_at) <= DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $stmt->execute([$tenantId, $days]);

        return (int) $stmt->fetchColumn();
    }
}
