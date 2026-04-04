<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use PDO;

/**
 * Agrégats pour les dashboards admin (cache court en mémoire de processus).
 */
final class AdminDashboardMetricsService
{
    private const TTL_SECONDS = 60;

    /** @var array<string, array{0: float, 1: mixed}> */
    private static array $cache = [];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    /**
     * @return array{
     *   kpis: list<array{id: string, label: string, value: string|null, hint: string|null, error: string|null}>,
     *   blockError: string|null
     * }
     */
    public function getSystemMetrics(): array
    {
        return $this->cached('sys_metrics_v1', function (): array {
            $kpis = [];
            $kpis[] = $this->kpi('tenants', 'Tenants', fn () => $this->scalarInt('SELECT COUNT(*) FROM tenants'));
            $kpis[] = $this->kpi('users_active_7d', 'Utilisateurs actifs (7 j.)', fn () => $this->countUsersActiveSince(7));
            $kpis[] = $this->kpi('users_active_30d', 'Utilisateurs actifs (30 j.)', fn () => $this->countUsersActiveSince(30));
            $kpis[] = $this->kpi('auth_fail_rate', 'Échecs connexion (24 h)', fn () => $this->loginFailureRate24h());
            $kpis[] = $this->kpi('audit_24h', 'Événements audit (24 h)', fn () => $this->scalarInt(
                'SELECT COUNT(*) FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'
            ));

            return ['kpis' => $kpis, 'blockError' => null];
        });
    }

    /**
     * @return array{
     *   kpis: list<array{id: string, label: string, value: string|null, hint: string|null, error: string|null}>,
     *   blockError: string|null
     * }
     */
    public function getOrganizationMetrics(int $tenantId): array
    {
        if ($tenantId <= 0) {
            return ['kpis' => [], 'blockError' => 'Tenant invalide.'];
        }

        return $this->cached('org_metrics_' . $tenantId, function () use ($tenantId): array {
            $kpis = [];
            $kpis[] = $this->kpi('members_active', 'Membres actifs', fn () => $this->scalarInt(
                "SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = 'active'",
                [$tenantId]
            ));
            $kpis[] = $this->kpi('members_inactive', 'Autres statuts', fn () => $this->scalarInt(
                "SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status <> 'active'",
                [$tenantId]
            ));
            $kpis[] = $this->kpi('invites_pending', 'Invitations en attente', fn () => $this->scalarInt(
                "SELECT COUNT(*) FROM community_invitations WHERE tenant_id = ? AND status = 'pending' AND expires_at > NOW()",
                [$tenantId]
            ));
            $kpis[] = $this->kpi('invites_expired', 'Invitations expirées (pending)', fn () => $this->scalarInt(
                "SELECT COUNT(*) FROM community_invitations WHERE tenant_id = ? AND status = 'pending' AND expires_at <= NOW()",
                [$tenantId]
            ));
            $kpis[] = $this->kpi('profiles_incomplete', 'Profils à compléter', fn () => $this->countIncompleteProfiles($tenantId));
            $kpis[] = $this->kpi('active_30d', 'Actifs approx. (30 j.)', fn () => $this->scalarInt(
                'SELECT COUNT(DISTINCT user_id) FROM audit_logs WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND user_id IS NOT NULL',
                [$tenantId]
            ));
            $kpis[] = $this->kpi('training_expiring', 'Formations (échéance 30 j.)', fn () => $this->countTrainingExpiring($tenantId));

            $moderation = $this->countModerationOpen($tenantId);
            if ($moderation['ok']) {
                $kpis[] = [
                    'id' => 'moderation_open',
                    'label' => 'Modération (cas ouverts)',
                    'value' => (string) $moderation['value'],
                    'hint' => null,
                    'error' => null,
                ];
            } else {
                $kpis[] = [
                    'id' => 'moderation_open',
                    'label' => 'Modération (cas ouverts)',
                    'value' => null,
                    'hint' => null,
                    'error' => $moderation['error'],
                ];
            }

            return ['kpis' => $kpis, 'blockError' => null];
        });
    }

    /**
     * @param callable(): array{kpis: list<array<string, mixed>>, blockError: string|null} $compute
     * @return array{kpis: list<array<string, mixed>>, blockError: string|null}
     */
    private function cached(string $key, callable $compute): array
    {
        $now = microtime(true);
        if (isset(self::$cache[$key])) {
            [$exp, $data] = self::$cache[$key];
            if ($now < $exp) {
                return $data;
            }
        }
        try {
            $data = $compute();
        } catch (\Throwable) {
            return ['kpis' => [], 'blockError' => 'Impossible de charger les indicateurs pour le moment.'];
        }
        self::$cache[$key] = [$now + self::TTL_SECONDS, $data];

        return $data;
    }

    /**
     * @param callable(): int|string $fn
     * @return array{id: string, label: string, value: string|null, hint: string|null, error: string|null}
     */
    private function kpi(string $id, string $label, callable $fn): array
    {
        try {
            $v = $fn();
            if (is_int($v)) {
                $value = (string) $v;
            } else {
                $value = (string) $v;
            }

            return [
                'id' => $id,
                'label' => $label,
                'value' => $value,
                'hint' => null,
                'error' => null,
            ];
        } catch (\Throwable) {
            return [
                'id' => $id,
                'label' => $label,
                'value' => null,
                'hint' => null,
                'error' => 'Indisponible',
            ];
        }
    }

    private function scalarInt(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function countUsersActiveSince(int $days): int
    {
        return $this->scalarInt(
            'SELECT COUNT(*) FROM users WHERE last_login_at IS NOT NULL AND last_login_at >= DATE_SUB(NOW(), INTERVAL ? DAY)',
            [$days]
        );
    }

    private function loginFailureRate24h(): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) AS fails, COUNT(*) AS total
             FROM login_attempts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'
        );
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = (int) ($row['total'] ?? 0);
        $fails = (int) ($row['fails'] ?? 0);
        if ($total === 0) {
            return '—';
        }

        return sprintf('%d / %d (%.0f%%)', $fails, $total, 100.0 * $fails / $total);
    }

    private function countIncompleteProfiles(int $tenantId): int
    {
        return $this->scalarInt(
            "SELECT COUNT(*) FROM users u
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE u.tenant_id = ? AND u.status = 'active'
             AND (
               up.user_id IS NULL
               OR NULLIF(TRIM(up.first_name), '') IS NULL
               OR NULLIF(TRIM(up.last_name), '') IS NULL
               OR u.role_id IS NULL
             )",
            [$tenantId]
        );
    }

    private function countTrainingExpiring(int $tenantId): int
    {
        try {
            return $this->scalarInt(
                "SELECT COUNT(*) FROM training_enrollments e
                 WHERE e.tenant_id = ? AND e.expires_at IS NOT NULL
                 AND e.expires_at <= DATE_ADD(NOW(), INTERVAL 30 DAY)
                 AND e.status IN ('assigned', 'in_progress')",
                [$tenantId]
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array{ok: true, value: int}|array{ok: false, error: string} */
    private function countModerationOpen(int $tenantId): array
    {
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'moderation_cases'");
            if (!$stmt || !$stmt->fetchColumn()) {
                return ['ok' => false, 'error' => 'N/A'];
            }

            $value = $this->scalarInt(
                "SELECT COUNT(*) FROM moderation_cases WHERE tenant_id = ? AND status = 'open'",
                [$tenantId]
            );

            return ['ok' => true, 'value' => $value];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Indisponible'];
        }
    }

    /**
     * Aperçus « à traiter » (invitations expirées, formations sous échéance).
     *
     * @return array{
     *   expired_invitations: list<array<string, mixed>>,
     *   training_expiring: list<array<string, mixed>>,
     *   error_invitations: string|null,
     *   error_training: string|null
     * }
     */
    public function getOrganizationWorkQueue(int $tenantId): array
    {
        if ($tenantId <= 0) {
            return [
                'expired_invitations' => [],
                'training_expiring' => [],
                'error_invitations' => null,
                'error_training' => null,
            ];
        }

        return $this->cached('org_work_' . $tenantId, function () use ($tenantId): array {
            $expired = [];
            $errInv = null;
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT id, email, expires_at FROM community_invitations
                     WHERE tenant_id = ? AND status = 'pending' AND expires_at <= NOW()
                     ORDER BY expires_at DESC LIMIT 5"
                );
                $stmt->execute([$tenantId]);
                $expired = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable) {
                $errInv = 'Indisponible';
            }

            $train = [];
            $errTr = null;
            try {
                $stmt = $this->pdo->prepare(
                    'SELECT e.id, e.expires_at, c.title AS course_title, u.email
                     FROM training_enrollments e
                     JOIN training_courses c ON c.id = e.course_id
                     JOIN users u ON u.id = e.user_id
                     WHERE e.tenant_id = ? AND e.expires_at IS NOT NULL
                     AND e.expires_at <= DATE_ADD(NOW(), INTERVAL 30 DAY)
                     AND e.status IN (\'assigned\', \'in_progress\')
                     ORDER BY e.expires_at ASC
                     LIMIT 5'
                );
                $stmt->execute([$tenantId]);
                $train = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable) {
                $errTr = 'Indisponible';
            }

            return [
                'expired_invitations' => $expired,
                'training_expiring' => $train,
                'error_invitations' => $errInv,
                'error_training' => $errTr,
            ];
        });
    }
}
