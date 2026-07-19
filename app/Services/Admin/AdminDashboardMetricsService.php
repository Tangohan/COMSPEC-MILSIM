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

        return $this->cached('org_metrics_v3_' . $tenantId, function () use ($tenantId): array {
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
            $kpis[] = $this->kpi('members_no_unit', 'Sans unité (affectation)', fn () => $this->countActiveUsersWithoutUnit($tenantId));
            $kpis[] = $this->kpi('members_no_role', 'Sans rôle communautaire', fn () => $this->countActiveUsersWithoutRole($tenantId));
            $kpis[] = $this->kpi('active_30d', 'Actifs approx. (30 j.)', fn () => $this->scalarInt(
                'SELECT COUNT(DISTINCT user_id) FROM audit_logs WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND user_id IS NOT NULL',
                [$tenantId]
            ));
            $kpis[] = $this->kpi('training_expiring', 'Formations (échéance 30 j.)', fn () => $this->countTrainingExpiring($tenantId));

            $forumPending = $this->countForumReportsPendingForTenant($tenantId);
            if ($forumPending['ok']) {
                $kpis[] = [
                    'id' => 'moderation_open',
                    'label' => 'Signalements forum (à traiter)',
                    'value' => (string) $forumPending['value'],
                    'hint' => null,
                    'error' => null,
                ];
            } else {
                $kpis[] = [
                    'id' => 'moderation_open',
                    'label' => 'Signalements forum (à traiter)',
                    'value' => null,
                    'hint' => null,
                    'error' => $forumPending['error'],
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
        $sql = 'SELECT COUNT(*) FROM users u
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE u.tenant_id = ? AND u.status = \'active\'
             AND (' . $this->sqlIncompleteProfilePredicate('u', 'up', $this->tenantRequiresCivilIdentity($tenantId)) . ')'
             . $this->sqlExcludeNoiseAccounts('u');

        return $this->scalarInt($sql, [$tenantId]);
    }

    private function countActiveUsersWithoutUnit(int $tenantId): int
    {
        if (!$this->schemaHasUserUnitsTable()) {
            return 0;
        }
        $sql = "SELECT COUNT(*) FROM users u
             WHERE u.tenant_id = ? AND u.status = 'active'
             AND NOT EXISTS (
               SELECT 1 FROM user_units uu
               INNER JOIN units un ON un.id = uu.unit_id AND un.tenant_id = u.tenant_id
               WHERE uu.user_id = u.id AND (uu.ended_at IS NULL OR uu.ended_at > NOW())
             )" . $this->sqlExcludeNoiseAccounts('u');

        return $this->scalarInt($sql, [$tenantId]);
    }

    private function countActiveUsersWithoutRole(int $tenantId): int
    {
        $sql = "SELECT COUNT(*) FROM users u
             WHERE u.tenant_id = ? AND u.status = 'active' AND u.role_id IS NULL"
             . $this->sqlExcludeNoiseAccounts('u');

        return $this->scalarInt($sql, [$tenantId]);
    }

    /** Inscription « simple » : identité civile attendue ; milsim : indicatif / nom affiché suffisent. */
    private function tenantRequiresCivilIdentity(int $tenantId): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT settings FROM tenants WHERE id = ? LIMIT 1');
            $stmt->execute([$tenantId]);
            $raw = $stmt->fetchColumn();
            if (!is_string($raw) || $raw === '') {
                return false;
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return false;
            }
            $community = is_array($decoded['community'] ?? null) ? $decoded['community'] : [];
            $mode = (string) ($community['registration_mode'] ?? $decoded['registration_mode'] ?? 'milsim');

            return $mode === 'simple';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Prédicat « profil incomplet » pour la file RH admin.
     * Milsim : pas d’identité affichable (nom + indicatif vides).
     * Simple : prénom/nom civil manquants.
     */
    private function sqlIncompleteProfilePredicate(string $userAlias, string $profileAlias, bool $requireCivil): string
    {
        if ($requireCivil) {
            return "({$profileAlias}.user_id IS NULL
                OR NULLIF(TRIM({$profileAlias}.first_name), '') IS NULL
                OR NULLIF(TRIM({$profileAlias}.last_name), '') IS NULL)";
        }

        return "(NULLIF(TRIM(COALESCE({$userAlias}.display_name, '')), '') IS NULL
            AND ({$userAlias}.callsign IS NULL OR NULLIF(TRIM({$userAlias}.callsign), '') IS NULL))";
    }

    /** Comptes techniques + comptes seed @demo.local (ne doivent pas polluer la file RH). */
    private function sqlExcludeNoiseAccounts(string $alias = 'u'): string
    {
        return $this->sqlExcludeServiceAccounts($alias)
            . " AND LOWER({$alias}.email) NOT LIKE '%@demo.local'";
    }

    /** Fragment SQL : exclure les comptes techniques si la colonne existe. */
    private function sqlExcludeServiceAccounts(string $alias = 'u'): string
    {
        try {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_service_account' LIMIT 1");
            if ($stmt && $stmt->fetchColumn()) {
                return " AND ({$alias}.is_service_account IS NULL OR {$alias}.is_service_account = 0)";
            }
        } catch (\Throwable) {
        }

        return '';
    }

    private function schemaHasUserUnitsTable(): bool
    {
        try {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_units' LIMIT 1");

            return (bool) ($stmt && $stmt->fetchColumn());
        } catch (\Throwable) {
            return false;
        }
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
    private function countForumReportsPendingForTenant(int $tenantId): array
    {
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'forum_reports'");
            if (!$stmt || !$stmt->fetchColumn()) {
                return ['ok' => false, 'error' => 'N/A'];
            }

            $value = $this->scalarInt(
                "SELECT COUNT(*) FROM forum_reports WHERE tenant_id = ? AND status = 'pending'",
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
     *   incomplete_profiles: list<array{id: int, email: string, display_name: string}>,
     *   users_without_unit: list<array{id: int, email: string, display_name: string}>,
     *   users_without_role: list<array{id: int, email: string, display_name: string}>,
     *   error_invitations: string|null,
     *   error_training: string|null,
     *   error_incomplete: string|null,
     *   error_no_unit: string|null,
     *   error_no_role: string|null
     * }
     */
    public function getOrganizationWorkQueue(int $tenantId): array
    {
        if ($tenantId <= 0) {
            return [
                'expired_invitations' => [],
                'training_expiring' => [],
                'incomplete_profiles' => [],
                'users_without_unit' => [],
                'users_without_role' => [],
                'error_invitations' => null,
                'error_training' => null,
                'error_incomplete' => null,
                'error_no_unit' => null,
                'error_no_role' => null,
            ];
        }

        return $this->cached('org_work_v3_' . $tenantId, function () use ($tenantId): array {
            $requireCivil = $this->tenantRequiresCivilIdentity($tenantId);
            $noise = $this->sqlExcludeNoiseAccounts('u');

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
                     JOIN users u ON u.id = e.user_id AND u.tenant_id = e.tenant_id
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

            $incomplete = [];
            $errInc = null;
            try {
                $sql = 'SELECT u.id, u.email, COALESCE(NULLIF(TRIM(u.display_name), \'\'), u.email) AS display_name
                    FROM users u
                    LEFT JOIN user_profiles up ON up.user_id = u.id
                    WHERE u.tenant_id = ? AND u.status = \'active\'
                    AND (' . $this->sqlIncompleteProfilePredicate('u', 'up', $requireCivil) . ')'
                    . $noise . '
                    ORDER BY u.email ASC LIMIT 8';
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$tenantId]);
                $incomplete = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable) {
                $errInc = 'Indisponible';
            }

            $noUnit = [];
            $errNu = null;
            try {
                if ($this->schemaHasUserUnitsTable()) {
                    $sql = 'SELECT u.id, u.email, COALESCE(NULLIF(TRIM(u.display_name), \'\'), u.email) AS display_name
                        FROM users u
                        WHERE u.tenant_id = ? AND u.status = \'active\'
                        AND NOT EXISTS (
                          SELECT 1 FROM user_units uu
                          INNER JOIN units un ON un.id = uu.unit_id AND un.tenant_id = u.tenant_id
                          WHERE uu.user_id = u.id AND (uu.ended_at IS NULL OR uu.ended_at > NOW())
                        )' . $noise . '
                        ORDER BY u.email ASC LIMIT 8';
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([$tenantId]);
                    $noUnit = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }
            } catch (\Throwable) {
                $errNu = 'Indisponible';
            }

            $noRole = [];
            $errNr = null;
            try {
                $sql = 'SELECT u.id, u.email, COALESCE(NULLIF(TRIM(u.display_name), \'\'), u.email) AS display_name
                    FROM users u
                    WHERE u.tenant_id = ? AND u.status = \'active\' AND u.role_id IS NULL'
                    . $noise . '
                    ORDER BY u.email ASC LIMIT 8';
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$tenantId]);
                $noRole = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable) {
                $errNr = 'Indisponible';
            }

            return [
                'expired_invitations' => $expired,
                'training_expiring' => $train,
                'incomplete_profiles' => $incomplete,
                'users_without_unit' => $noUnit,
                'users_without_role' => $noRole,
                'error_invitations' => $errInv,
                'error_training' => $errTr,
                'error_incomplete' => $errInc,
                'error_no_unit' => $errNu,
                'error_no_role' => $errNr,
            ];
        });
    }
}
