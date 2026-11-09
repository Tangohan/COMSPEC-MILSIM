<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditActionLabel;
use App\Support\PlanAccessWindow;
use PDO;

/**
 * Lecture seule pour la fiche communauté (administration du site) : résumé, limites et diagnostic.
 * Chaque requête est préparée et bornée au tenant_id de la route ; aucune valeur secrète n'est lue
 * (on ne compte que la présence d'une configuration, jamais son contenu).
 */
final class TenantAdminInsights
{
    private ?PDO $pdo;

    /** @var array<string, bool> */
    private array $tables = [];

    public function __construct(?PDO $pdo = null, private ?UserRepository $users = null)
    {
        $this->pdo = $pdo;
        $this->users ??= new UserRepository();
    }

    private function db(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    private function hasTable(string $name): bool
    {
        if (!isset($this->tables[$name])) {
            try {
                $st = $this->db()->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
                $st->execute([$name]);
                $this->tables[$name] = (bool) $st->fetchColumn();
            } catch (\Throwable) {
                $this->tables[$name] = false;
            }
        }

        return $this->tables[$name];
    }

    private function scalar(string $sql, array $params): mixed
    {
        try {
            $st = $this->db()->prepare($sql);
            $st->execute($params);

            return $st->fetchColumn();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $sql, array $params): array
    {
        try {
            $st = $this->db()->prepare($sql);
            $st->execute($params);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Le compte appartient bien à cette communauté et est actif (isolation tenant_id). */
    public function isActiveMember(int $tenantId, int $userId): bool
    {
        if ($tenantId < 1 || $userId < 1) {
            return false;
        }
        $found = $this->scalar(
            'SELECT u.id FROM users u WHERE u.id = ? AND u.tenant_id = ? AND ' . UserRepository::sqlPlatformAccountFilter('u', 'active') . ' LIMIT 1',
            [$userId, $tenantId]
        );

        return $found !== false && $found !== null;
    }

    /**
     * Comptes actifs proposables comme propriétaire (liste bornée).
     *
     * @return list<array{id: int, name: string}>
     */
    public function ownerCandidates(int $tenantId): array
    {
        $out = [];
        foreach ($this->users->listPlatformAccounts($tenantId, 'active', null, 'name', 'asc', 200, 0) as $r) {
            $out[] = ['id' => (int) $r['id'], 'name' => (string) ($r['display_name'] ?? '')];
        }

        return $out;
    }

    /**
     * Formule appliquée, origine et jours restants (même logique que le reste du site : PlanAccessWindow).
     *
     * @param array<string, mixed> $tenant
     * @return array{kind: string, plan_slug: string, ends_at: ?string, days_left: ?int}
     */
    public static function planStatus(array $tenant, ?int $now = null): array
    {
        $now ??= time();
        $src = PlanAccessWindow::source($tenant, $now);
        $days = null;
        if ($src['ends_at'] !== null && ($ts = strtotime((string) $src['ends_at'])) !== false) {
            $days = (int) ceil(($ts - $now) / 86400);
        }

        return ['kind' => $src['kind'], 'plan_slug' => $src['plan_slug'], 'ends_at' => $src['ends_at'], 'days_left' => $days];
    }

    /**
     * @param array<string, mixed> $tenant
     * @return array<string, mixed>
     */
    public function summary(array $tenant): array
    {
        $tid = (int) ($tenant['id'] ?? 0);
        $counts = [];
        foreach (UserRepository::PLATFORM_ACCOUNT_FILTERS as $f) {
            $counts[$f] = $this->users->countPlatformAccounts($tid, $f, null);
        }
        $active = UserRepository::sqlPlatformAccountFilter('u', 'active');
        $admins = $this->scalar(
            "SELECT COUNT(DISTINCT u.id) FROM users u
             INNER JOIN roles r ON r.id = u.role_id AND r.tenant_id = u.tenant_id
             INNER JOIN role_permissions rp ON rp.role_id = r.id
             INNER JOIN permissions p ON p.id = rp.permission_id AND p.tenant_id = u.tenant_id
             WHERE u.tenant_id = ? AND {$active} AND p.slug = 'admin.organization'",
            [$tid]
        );
        $last = $this->scalar('SELECT MAX(u.last_login_at) FROM users u WHERE u.tenant_id = ? AND ' . UserRepository::sqlPlatformAccountFilter('u', 'all'), [$tid]);
        $events = $this->hasTable('community_events')
            ? $this->rows('SELECT id, title, starts_at FROM community_events WHERE tenant_id = ? ORDER BY starts_at DESC LIMIT 5', [$tid])
            : [];
        $gameSessions = $this->hasTable('game_sessions')
            ? $this->scalar('SELECT COUNT(*) FROM game_sessions WHERE tenant_id = ? AND last_seen_at >= (NOW() - INTERVAL 7 DAY)', [$tid])
            : null;
        $traffic = $this->hasTable('atak_ingest_traffic')
            ? $this->scalar('SELECT COALESCE(SUM(bytes), 0) + COALESCE(SUM(photo_bytes), 0) FROM atak_ingest_traffic WHERE tenant_id = ? AND bucket_ts >= (NOW() - INTERVAL 30 DAY)', [$tid])
            : null;

        return [
            'counts' => $counts,
            'admins' => (int) $admins,
            'last_activity' => ($last !== null && $last !== false && $last !== '') ? (string) $last : null,
            'plan' => self::planStatus($tenant),
            'events' => $events,
            'game_sessions_7d' => $gameSessions === null ? null : (int) $gameSessions,
            'ingest_bytes_30d' => $traffic === null ? null : (int) $traffic,
        ];
    }

    /**
     * Limites et fonctionnalités de la formule appliquée (lecture seule : elles se règlent sur la formule).
     *
     * @param array<string, mixed>|null $plan ligne subscription_plans de la formule effective
     * @param array<string, int> $counts décomptes de comptes
     * @return array{limits: list<array{key: string, limit: int, used: ?int, reached: bool}>, flags: array<string, bool>}
     */
    public static function planLimits(?array $plan, array $counts): array
    {
        $features = $plan !== null ? json_decode((string) ($plan['features_json'] ?? '{}'), true) : null;
        $features = is_array($features) ? $features : [];
        $limits = [];
        $flags = [];
        foreach ($features as $key => $value) {
            $key = (string) $key;
            if (preg_match('/^[a-z0-9_.\-]{1,64}$/i', $key) !== 1) {
                continue;
            }
            if (is_bool($value)) {
                $flags[$key] = $value;
            } elseif (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $limit = max(0, (int) $value);
                $used = $key === 'max_members' ? (int) ($counts['active'] ?? 0) : null;
                $limits[] = ['key' => $key, 'limit' => $limit, 'used' => $used, 'reached' => $used !== null && $limit > 0 && $used >= $limit];
            }
        }
        ksort($flags);

        return ['limits' => $limits, 'flags' => $flags];
    }

    /**
     * État (jamais le contenu) des intégrations de la communauté.
     *
     * @param array<string, mixed> $tenant
     * @return list<array{key: string, label: string, state: 'ok'|'missing'|'na', detail: string}>
     */
    public function integrations(array $tenant): array
    {
        $tid = (int) ($tenant['id'] ?? 0);
        $out = [];
        $discord = $this->hasTable('discord_companion_guilds')
            ? (int) $this->scalar('SELECT COUNT(*) FROM discord_companion_guilds WHERE tenant_id = ?', [$tid])
            : null;
        $out[] = ['key' => 'discord', 'label' => 'Discord (Companion)', 'state' => $discord === null ? 'na' : ($discord > 0 ? 'ok' : 'missing'), 'detail' => $discord === null ? 'table absente' : ($discord > 0 ? 'serveur lié' : 'aucun serveur lié')];
        $keys = $this->hasTable('tenant_api_keys')
            ? (int) $this->scalar('SELECT COUNT(*) FROM tenant_api_keys WHERE tenant_id = ? AND revoked_at IS NULL', [$tid])
            : null;
        $out[] = ['key' => 'api', 'label' => 'Clés d’API', 'state' => $keys === null ? 'na' : ($keys > 0 ? 'ok' : 'missing'), 'detail' => $keys === null ? 'table absente' : $keys . ' clé(s) active(s)'];
        $steam = $this->hasTable('game_sessions')
            ? (int) $this->scalar('SELECT COUNT(DISTINCT steam_id) FROM game_sessions WHERE tenant_id = ? AND steam_id IS NOT NULL', [$tid])
            : null;
        $out[] = ['key' => 'steam', 'label' => 'Steam / jeu', 'state' => $steam === null ? 'na' : ($steam > 0 ? 'ok' : 'missing'), 'detail' => $steam === null ? 'table absente' : $steam . ' identifiant(s) joueur connu(s)'];
        $atak = $this->hasTable('atak_terminals')
            ? (int) $this->scalar('SELECT COUNT(*) FROM atak_terminals WHERE tenant_id = ?', [$tid])
            : null;
        $out[] = ['key' => 'atak', 'label' => 'Terminaux ATAK', 'state' => $atak === null ? 'na' : ($atak > 0 ? 'ok' : 'missing'), 'detail' => $atak === null ? 'table absente' : $atak . ' terminal(aux)'];

        return $out;
    }

    /**
     * @param array<string, mixed> $tenant
     * @return array<string, mixed>
     */
    public function diagnostic(array $tenant): array
    {
        $tid = (int) ($tenant['id'] ?? 0);
        $migrations = null;
        try {
            $migrations = (new PlatformHealthDigest($this->db()))->migrationCounts();
        } catch (\Throwable) {
        }
        $audit = [];
        foreach ($this->rows('SELECT action, created_at FROM audit_logs WHERE tenant_id = ? ORDER BY id DESC LIMIT 10', [$tid]) as $r) {
            $audit[] = ['label' => AuditActionLabel::toFrench((string) ($r['action'] ?? '')), 'at' => (string) ($r['created_at'] ?? '')];
        }
        $mails = $this->hasTable('email_deliveries')
            ? $this->rows("SELECT event_code, status, error_message, created_at FROM email_deliveries WHERE tenant_id = ? ORDER BY id DESC LIMIT 10", [$tid])
            : [];
        $mailRows = [];
        foreach ($mails as $m) {
            $mailRows[] = [
                'event' => TenantEditRules::redact((string) ($m['event_code'] ?? ''), 60),
                'status' => TenantEditRules::redact((string) ($m['status'] ?? ''), 20),
                'error' => TenantEditRules::redact((string) ($m['error_message'] ?? ''), 100),
                'at' => (string) ($m['created_at'] ?? ''),
            ];
        }
        // Les tâches planifiées sont communes à la plateforme (pas de tenant_id) : clé, état et date seulement.
        $cron = $this->hasTable('cron_job_runs')
            ? $this->rows("SELECT job_key, status, started_at FROM cron_job_runs WHERE status = 'error' ORDER BY id DESC LIMIT 5", [])
            : [];
        $link = [];
        if ($this->hasTable('game_sessions')) {
            $seen = $this->scalar('SELECT MAX(last_seen_at) FROM game_sessions WHERE tenant_id = ?', [$tid]);
            $link[] = ['label' => 'Dernier signe du jeu / Overwatch', 'value' => $seen ? (string) $seen : 'jamais'];
        }
        if ($this->hasTable('atak_terminals')) {
            $link[] = ['label' => 'Terminaux ATAK enregistrés', 'value' => (string) (int) $this->scalar('SELECT COUNT(*) FROM atak_terminals WHERE tenant_id = ?', [$tid])];
        }

        return [
            'migrations' => $migrations,
            'integrations' => $this->integrations($tenant),
            'audit' => $audit,
            'mails' => $mailRows,
            'cron_errors' => array_map(static fn (array $r): array => [
                'job' => TenantEditRules::redact((string) ($r['job_key'] ?? ''), 60),
                'at' => (string) ($r['started_at'] ?? ''),
            ], $cron),
            'link' => $link,
        ];
    }
}
