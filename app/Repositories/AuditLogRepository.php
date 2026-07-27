<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\Audit\AuditSnapshotPresenter;
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
     *   action_exact?: string|null,
     *   action_domain?: string|null,
     *   search?: string|null,
     *   user_id?: int|null,
     *   tenant_id?: int|null,
     *   entity_type?: string|null,
     *   entity_id?: int|null,
     *   actor_email?: string|null
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
        if (!empty($filters['action_exact'])) {
            $where[] = 'a.action = ?';
            $params[] = trim((string) $filters['action_exact']);
        } elseif (!empty($filters['action'])) {
            $where[] = 'a.action LIKE ?';
            $params[] = '%' . $this->likeEscape((string) $filters['action']) . '%';
        }
        if (!empty($filters['action_domain'])) {
            $where[] = 'a.action LIKE ?';
            $params[] = $this->likeEscape(trim((string) $filters['action_domain'])) . '.%';
        }
        if (isset($filters['user_id']) && (int) $filters['user_id'] > 0) {
            $where[] = 'a.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }
        if (isset($filters['tenant_id']) && (int) $filters['tenant_id'] > 0) {
            $where[] = 'a.tenant_id = ?';
            $params[] = (int) $filters['tenant_id'];
        }
        if (!empty($filters['entity_type'])) {
            $where[] = 'a.entity_type LIKE ?';
            $params[] = '%' . $this->likeEscape(trim((string) $filters['entity_type'])) . '%';
        }
        if (isset($filters['entity_id']) && (int) $filters['entity_id'] > 0) {
            $where[] = 'a.entity_id = ?';
            $params[] = (int) $filters['entity_id'];
        }
        if (!empty($filters['actor_email'])) {
            $where[] = 'u.email LIKE ?';
            $params[] = '%' . $this->likeEscape(trim((string) $filters['actor_email'])) . '%';
        }
        if (!empty($filters['search'])) {
            $q = '%' . $this->likeEscape(trim((string) $filters['search'])) . '%';
            $where[] = '(a.action LIKE ? OR a.entity_type LIKE ? OR CAST(a.entity_id AS CHAR) LIKE ?
                OR u.email LIKE ? OR u.display_name LIKE ? OR u.callsign LIKE ?
                OR eu.email LIKE ? OR eu.display_name LIKE ? OR eu.callsign LIKE ?
                OR d.title LIKE ? OR r.name LIKE ? OR et.name LIKE ?
                OR t.name LIKE ? OR a.ip LIKE ? OR a.user_agent LIKE ? OR a.old_value LIKE ? OR a.new_value LIKE ?)';
            array_push($params, $q, $q, $q, $q, $q, $q, $q, $q, $q, $q, $q, $q, $q, $q, $q, $q, $q);
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
        $fromSql = $this->listFromSql();
        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             {$fromSql}
             WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = 'SELECT ' . $this->listSelectSql() . "
                {$fromSql}
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
            'SELECT ' . $this->listSelectSql() . '
             ' . $this->listFromSql() . '
             ORDER BY a.id DESC
             LIMIT ' . (int) $limit
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
        $sql = 'SELECT ' . $this->listSelectSql() . '
             ' . $this->listFromSql() . "
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
                'SELECT ' . $this->listSelectSql() . '
                 ' . $this->listFromSql() . '
                 WHERE a.tenant_id = ?
                 ORDER BY a.id DESC
                 LIMIT ' . (int) $limit
            );
            $stmt->execute([$tenantId]);

            return $this->enrichRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        }

        $excludedActions = ['site_role.assigned', 'site_role.revoked', 'permission.scope_migration'];
        $placeholders = implode(',', array_fill(0, count($excludedActions), '?'));
        $sql = 'SELECT ' . $this->listSelectSql() . '
             ' . $this->listFromSql() . "
             WHERE a.tenant_id = ?
             AND a.action NOT IN ({$placeholders})
             ORDER BY a.id DESC
             LIMIT " . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$tenantId], $excludedActions));

        return $this->enrichRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
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
            'user_left_community',
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

        $sql = 'SELECT ' . $this->listSelectSql() . '
             ' . $this->listFromSql() . "
             WHERE a.tenant_id = ?
             AND a.action IN ({$placeholders})
             AND a.action NOT IN ({$exPh})
             ORDER BY a.id DESC
             LIMIT " . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$tenantId], $focusActions, $excludedActions));

        return $this->enrichRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function enrichRows(array $rows): array
    {
        return array_map(
            static fn (array $row): array => AuditSnapshotPresenter::enrichListRow($row),
            $rows
        );
    }

    private function likeEscape(string $s): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
    }

    /**
     * Détail d’une entrée. Si $tenantScope est défini (>0), refuse les lignes d’une autre communauté.
     *
     * @return array<string, mixed>|null
     */
    public function findByIdForScope(int $id, ?int $tenantScope): ?array
    {
        if ($id < 1) {
            return null;
        }
        $sql = 'SELECT ' . $this->listSelectSql() . '
                ' . $this->listFromSql() . '
                WHERE a.id = ?
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || $row === []) {
            return null;
        }
        if ($tenantScope !== null && $tenantScope > 0) {
            $tid = isset($row['tenant_id']) ? (int) $row['tenant_id'] : 0;
            if ($tid !== $tenantScope) {
                return null;
            }
        }

        return $row;
    }

    /**
     * Première trace connue d’attribution de rôle communauté pour ce membre (date jour).
     */
    public function earliestRoleAssignedDateYmdForTargetUser(int $tenantId, int $targetUserId): ?string
    {
        if ($tenantId < 1 || $targetUserId < 1) {
            return null;
        }
        try {
            $st = $this->pdo->prepare(
                "SELECT DATE(MIN(created_at)) AS d
                 FROM audit_logs
                 WHERE tenant_id = ?
                   AND action = 'role_assigned'
                   AND entity_type = 'user'
                   AND entity_id = ?"
            );
            $st->execute([$tenantId, $targetUserId]);
            $v = $st->fetchColumn();
        } catch (\Throwable) {
            return null;
        }
        if ($v === false || $v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s !== '' ? $s : null;
    }

    private function listSelectSql(): string
    {
        return 'a.*, t.name AS tenant_name,
                u.email AS actor_email,
                u.display_name AS actor_display_name,
                u.callsign AS actor_callsign,
                eu.display_name AS entity_user_display_name,
                eu.callsign AS entity_user_callsign,
                eu.email AS entity_user_email,
                d.title AS entity_document_title,
                r.name AS entity_role_name,
                et.name AS entity_tenant_name';
    }

    private function listFromSql(): string
    {
        return 'FROM audit_logs a
                LEFT JOIN tenants t ON t.id = a.tenant_id
                LEFT JOIN users u ON u.id = a.user_id
                LEFT JOIN users eu ON eu.id = a.entity_id AND a.entity_type IN (\'user\', \'auth\')
                LEFT JOIN documents d ON d.id = a.entity_id AND a.entity_type = \'document\'
                LEFT JOIN roles r ON r.id = a.entity_id AND a.entity_type = \'role\'
                LEFT JOIN tenants et ON et.id = a.entity_id AND a.entity_type = \'tenant\'';
    }
}
