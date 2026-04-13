<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class AuditLogRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    /**
     * Liste paginée pour super-admin (tous tenants).
     *
     * @param array{
     *   date_from?: string|null,
     *   date_to?: string|null,
     *   action?: string|null,
     *   user_id?: int|null,
     *   tenant_id?: int|null
     * } $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function listSystem(array $filters, int $page, int $perPage): array
    {
        $perPage = max(5, min(100, $perPage));
        $offset = max(0, ($page - 1) * $perPage);
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = 'a.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'a.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['action'])) {
            $where[] = 'a.action LIKE ?';
            $params[] = '%' . $this->likeEscape((string) $filters['action']) . '%';
        }
        if (isset($filters['user_id']) && (int) $filters['user_id'] > 0) {
            $where[] = 'a.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }
        if (isset($filters['tenant_id']) && (int) $filters['tenant_id'] > 0) {
            $where[] = 'a.tenant_id = ?';
            $params[] = (int) $filters['tenant_id'];
        }

        if (!empty($filters['organization_journal'])) {
            $excludedActions = [
                'site_role.assigned',
                'site_role.revoked',
                'permission.scope_migration',
            ];
            $placeholders = implode(',', array_fill(0, count($excludedActions), '?'));
            $where[] = "a.action NOT IN ({$placeholders})";
            foreach ($excludedActions as $ea) {
                $params[] = $ea;
            }
        }

        $whereSql = implode(' AND ', $where);
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM audit_logs a WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT a.*, t.name AS tenant_name, u.email AS actor_email
                FROM audit_logs a
                LEFT JOIN tenants t ON t.id = a.tenant_id
                LEFT JOIN users u ON u.id = a.user_id
                WHERE {$whereSql}
                ORDER BY a.id DESC
                LIMIT " . (int) $perPage . ' OFFSET ' . (int) $offset;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['rows' => $rows ?: [], 'total' => $total];
    }

    /**
     * @param array{
     *   date_from?: string|null,
     *   date_to?: string|null,
     *   action?: string|null,
     *   user_id?: int|null
     * } $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function listForTenant(int $tenantId, array $filters, int $page, int $perPage): array
    {
        $filters['tenant_id'] = $tenantId;

        return $this->listSystem($filters, $page, $perPage);
    }

    /** @return list<array<string, mixed>> */
    public function recentSystem(int $limit): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT a.*, t.name AS tenant_name, u.email AS actor_email
             FROM audit_logs a
             LEFT JOIN tenants t ON t.id = a.tenant_id
             LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Dernières entrées système filtrées par liste d’actions exactes (tableau de bord plateforme).
     *
     * @param list<string> $actions
     * @return list<array<string, mixed>>
     */
    public function recentSystemByActions(array $actions, int $limit): array
    {
        $actions = array_values(array_unique(array_filter(array_map(static function (mixed $a): string {
            return trim((string) $a);
        }, $actions), static fn (string $a): bool => $a !== '')));
        if ($actions === []) {
            return [];
        }
        $limit = max(1, min(50, $limit));
        $placeholders = implode(',', array_fill(0, count($actions), '?'));
        $sql = "SELECT a.*, t.name AS tenant_name, u.email AS actor_email
             FROM audit_logs a
             LEFT JOIN tenants t ON t.id = a.tenant_id
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.action IN ({$placeholders})
             ORDER BY a.id DESC
             LIMIT " . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($actions);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Dernières entrées pour un tenant. Pour le back-office organisation : exclure les actions
     * réservées à la plateforme (rôles site globaux, migrations de périmètre).
     *
     * @return list<array<string, mixed>>
     */
    public function recentForTenant(int $tenantId, int $limit, bool $organizationJournal = true): array
    {
        $limit = max(1, min(50, $limit));
        if (!$organizationJournal) {
            $stmt = $this->pdo->prepare(
                'SELECT a.*, u.email AS actor_email
                 FROM audit_logs a
                 LEFT JOIN users u ON u.id = a.user_id
                 WHERE a.tenant_id = ?
                 ORDER BY a.id DESC
                 LIMIT ' . (int) $limit
            );
            $stmt->execute([$tenantId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $excludedActions = ['site_role.assigned', 'site_role.revoked', 'permission.scope_migration'];
        $placeholders = implode(',', array_fill(0, count($excludedActions), '?'));
        $sql = "SELECT a.*, u.email AS actor_email
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.tenant_id = ?
             AND a.action NOT IN ({$placeholders})
             ORDER BY a.id DESC
             LIMIT " . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$tenantId], $excludedActions));

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Dernières actions « RH / effectifs » : comptes, rôles, groupes, invitations, inscriptions.
     * Mêmes exclusions d’actions plateforme que le journal organisation.
     *
     * @return list<array<string, mixed>>
     */
    public function recentForTenantRhFocus(int $tenantId, int $limit): array
    {
        $limit = max(1, min(40, $limit));
        $focusActions = [
            'user_created',
            'user_updated',
            'user_deactivated',
            'role_assigned',
            'role.permissions_updated',
            'group_member_added',
            'group_member_removed',
            'invitation.sent',
            'invitation.accepted',
            'invitation.revoked',
            'auth.register',
            'tenant.setup_completed',
        ];
        $placeholders = implode(',', array_fill(0, count($focusActions), '?'));
        $excludedActions = ['site_role.assigned', 'site_role.revoked', 'permission.scope_migration'];
        $exPh = implode(',', array_fill(0, count($excludedActions), '?'));

        $sql = "SELECT a.*, u.email AS actor_email
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.tenant_id = ?
             AND a.action IN ({$placeholders})
             AND a.action NOT IN ({$exPh})
             ORDER BY a.id DESC
             LIMIT " . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$tenantId], $focusActions, $excludedActions));

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function likeEscape(string $s): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
    }
}
