<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SteamId;
use PDO;

/**
 * Parc ATAK des joueurs, toutes communautés (administration du site, /admin/parc-atak).
 *
 * Un « ATAK » est une fiche atak_terminals (téléphone du joueur enregistré par le mod), hors sessions web.
 * Ses chiffres viennent de ce que le jeu remonte déjà : temps de jeu et rôles (atak_screen_time),
 * distances et heures de vol (atak_distance_stats, atak_pilot_time), classe du slot Arma (operator_game_profiles),
 * comptes rendus envoyés (atak_tactical_reports).
 *
 * Les actions de l'administration (blocage, retrait, messages imposés) ont leurs tables
 * (bootstrap/atak_admin_control_migration.php) et chacune laisse une ligne dans atak_admin_actions.
 */
final class AtakAdminControlRepository
{
    public const LEVELS = [
        'info' => 'Information',
        'warning' => 'Avertissement',
        'critical' => 'Urgent',
    ];

    public const ACTIONS = [
        'block' => 'Blocage',
        'unblock' => 'Blocage levé',
        'retire' => 'ATAK retiré',
        'message' => 'Message imposé',
        'message_cancel' => 'Message annulé',
        'sessions' => 'Déconnexion forcée',
    ];

    /** Filtres d'état de la liste. */
    public const STATES = [
        '' => 'Tous',
        'actifs' => 'Vus depuis 7 jours',
        'bloques' => 'Bloqués',
        'messages' => 'Message en attente',
        'dormants' => 'Sans signe depuis 30 jours',
    ];

    public const SORTS = [
        'recent' => 'Dernier signe de vie',
        'jeu' => 'Temps de jeu',
        'distance' => 'Distance totale',
        'nom' => 'Indicatif',
    ];

    private ?PDO $pdo;

    private ?bool $ready = null;

    /** @var array<string, ?array<string, mixed>> blocages déjà cherchés pendant la requête */
    private array $blockCache = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /** Crée les tables au premier usage (le passage des migrations n'est pas automatique au déploiement). */
    public function ready(): bool
    {
        if ($this->ready !== null) {
            return $this->ready;
        }
        try {
            require_once dirname(__DIR__, 2) . '/bootstrap/atak_admin_control_migration.php';
            ob_start();
            try {
                run_atak_admin_control_migration($this->pdo());
            } finally {
                ob_end_clean();
            }
            $this->ready = true;
        } catch (\Throwable) {
            $this->ready = false;
        }

        return $this->ready;
    }

    // ── Parc ────────────────────────────────────────────────────────────────

    /**
     * ATAK de toutes les communautés, plus récents d'abord (au plus $limit fiches).
     *
     * @return list<array<string, mixed>>
     */
    public function listTerminals(string $search = '', int $tenantId = 0, int $limit = 1000): array
    {
        $where = ["t.terminal_type <> 'web'", "t.terminal_uid NOT LIKE 'WEB-%'", "t.terminal_uid NOT LIKE 'SESSION-%'"];
        $args = [];
        if ($tenantId > 0) {
            $where[] = 't.tenant_id = ?';
            $args[] = $tenantId;
        }
        $search = trim($search);
        if ($search !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($search, 0, 80)) . '%';
            $where[] = '(t.operator_callsign LIKE ? OR t.terminal_uid LIKE ? OR t.terminal_label LIKE ? OR t.operator_military_id LIKE ?
                        OR u.display_name LIKE ? OR u.callsign LIKE ? OR u.email LIKE ? OR u.steam_id LIKE ? OR tn.name LIKE ?)';
            array_push($args, $like, $like, $like, $like, $like, $like, $like, $like, $like);
        }
        $limit = max(1, min(2000, $limit));
        $base = 'FROM atak_terminals t
                 LEFT JOIN users u ON u.id = t.user_id
                 LEFT JOIN tenants tn ON tn.id = t.tenant_id
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY (t.last_seen_at IS NULL), t.last_seen_at DESC, t.id DESC
                 LIMIT ' . $limit;
        $common = 't.id, t.tenant_id, t.user_id, t.terminal_uid, t.terminal_label, t.terminal_type, t.platform_label,
                   t.operator_callsign, t.operator_military_id, t.status, t.compromise_state, t.first_seen_at, t.last_seen_at,
                   t.created_at, u.display_name, u.callsign AS user_callsign, u.email, u.steam_id, u.status AS user_status,
                   tn.name AS tenant_name, tn.slug AS tenant_slug';
        try {
            $st = $this->pdo()->prepare(
                "SELECT {$common}, t.last_client_ip, t.mod_version, t.extension_version, t.server_host {$base}"
            );
            $st->execute($args);
        } catch (\PDOException) {
            // Fiches d'avant la télémétrie (IP, versions) : on liste quand même.
            try {
                $st = $this->pdo()->prepare("SELECT {$common} {$base}");
                $st->execute($args);
            } catch (\PDOException) {
                return [];
            }
        }

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function findTerminal(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        foreach (['t.*', 't.id, t.tenant_id, t.user_id, t.terminal_uid, t.terminal_label, t.terminal_type, t.platform_label, t.operator_callsign, t.operator_military_id, t.status, t.compromise_state, t.first_seen_at, t.last_seen_at, t.created_at'] as $cols) {
            try {
                $st = $this->pdo()->prepare(
                    "SELECT {$cols}, u.display_name, u.callsign AS user_callsign, u.email, u.steam_id, u.status AS user_status,
                            tn.name AS tenant_name, tn.slug AS tenant_slug
                     FROM atak_terminals t
                     LEFT JOIN users u ON u.id = t.user_id
                     LEFT JOIN tenants tn ON tn.id = t.tenant_id
                     WHERE t.id = ? LIMIT 1"
                );
                $st->execute([$id]);
                $row = $st->fetch(PDO::FETCH_ASSOC);

                return $row ?: null;
            } catch (\PDOException) {
                continue;
            }
        }

        return null;
    }

    /**
     * Communautés qui ont au moins un ATAK, pour le filtre.
     *
     * @return list<array{id: int, name: string, n: int}>
     */
    public function tenantsWithTerminals(): array
    {
        try {
            $st = $this->pdo()->query(
                "SELECT t.tenant_id AS id, MAX(tn.name) AS name, COUNT(*) AS n
                 FROM atak_terminals t LEFT JOIN tenants tn ON tn.id = t.tenant_id
                 WHERE t.terminal_type <> 'web'
                 GROUP BY t.tenant_id ORDER BY name"
            );
        } catch (\PDOException) {
            return [];
        }
        $out = [];
        foreach ($st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [] as $r) {
            $out[] = ['id' => (int) $r['id'], 'name' => (string) ($r['name'] ?? ('Communauté #' . $r['id'])), 'n' => (int) $r['n']];
        }

        return $out;
    }

    /**
     * Chiffres de jeu par joueur (clé « tenant:user »), pour les joueurs des fiches données.
     *
     * @param list<int> $userIds
     * @return array<string, array{play: int, screen: int, total_m: int, foot_m: int, land_m: int, air_m: int, sea_m: int, halo_m: int, steps: int, sessions: int, pilot_s: int, reports: int, role: string, role_s: int, slot: string, game_class: string, faction: string, group: string, side: string, game_seen: string, mission: string, server: string}>
     */
    public function metricsForUsers(array $userIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $out = [];
        $row = static function (int $tenantId, int $userId) use (&$out): string {
            $k = $tenantId . ':' . $userId;
            $out[$k] ??= [
                'play' => 0, 'screen' => 0, 'total_m' => 0, 'foot_m' => 0, 'land_m' => 0, 'air_m' => 0, 'sea_m' => 0,
                'halo_m' => 0, 'steps' => 0, 'sessions' => 0, 'pilot_s' => 0, 'reports' => 0,
                'role' => '', 'role_s' => 0, 'slot' => '', 'game_class' => '', 'faction' => '', 'group' => '', 'side' => '',
                'game_seen' => '', 'mission' => '', 'server' => '',
            ];

            return $k;
        };

        // Temps de jeu, temps d'écran, rôles tenus.
        $roles = [];
        foreach ($this->rows(
            "SELECT tenant_id, user_id, kind, item_key, MAX(item_label) AS item_label, SUM(seconds) AS seconds
             FROM atak_screen_time
             WHERE user_id IN ({$in}) AND kind IN ('play', 'screen', 'role')
             GROUP BY tenant_id, user_id, kind, item_key",
            $ids
        ) as $r) {
            $k = $row((int) $r['tenant_id'], (int) $r['user_id']);
            $sec = (int) $r['seconds'];
            $key = (string) $r['item_key'];
            if ($r['kind'] === 'play' && strtoupper($key) === 'TOTAL') {
                $out[$k]['play'] += $sec;
            } elseif ($r['kind'] === 'screen' && strtoupper($key) === 'TOTAL') {
                $out[$k]['screen'] += $sec;
            } elseif ($r['kind'] === 'role') {
                $roles[$k][] = ['key' => $key, 'label' => (string) ($r['item_label'] ?? $key), 'seconds' => $sec];
            }
        }
        foreach ($roles as $k => $list) {
            [$role, $slot] = self::topRoles($list);
            $out[$k]['role'] = $role['label'] ?? '';
            $out[$k]['role_s'] = (int) ($role['seconds'] ?? 0);
            $out[$k]['slot'] = $slot['label'] ?? '';
        }

        // Distances.
        foreach ($this->rows(
            "SELECT tenant_id, user_id, SUM(steps) AS steps, SUM(foot_m) AS foot_m, SUM(land_m) AS land_m, SUM(sea_m) AS sea_m,
                    SUM(air_m) AS air_m, SUM(halo_m) AS halo_m, COUNT(*) AS sessions
             FROM atak_distance_stats WHERE user_id IN ({$in}) GROUP BY tenant_id, user_id",
            $ids
        ) as $r) {
            $k = $row((int) $r['tenant_id'], (int) $r['user_id']);
            foreach (['steps', 'foot_m', 'land_m', 'sea_m', 'air_m', 'halo_m', 'sessions'] as $f) {
                $out[$k][$f] = (int) $r[$f];
            }
            $out[$k]['total_m'] = (int) $r['foot_m'] + (int) $r['land_m'] + (int) $r['sea_m'] + (int) $r['air_m'] + (int) $r['halo_m'];
        }

        // Heures de vol.
        foreach ($this->rows(
            "SELECT tenant_id, user_id, SUM(seconds) AS seconds FROM atak_pilot_time WHERE user_id IN ({$in}) GROUP BY tenant_id, user_id",
            $ids
        ) as $r) {
            $out[$row((int) $r['tenant_id'], (int) $r['user_id'])]['pilot_s'] = (int) $r['seconds'];
        }

        // Comptes rendus envoyés.
        foreach ($this->rows(
            "SELECT tenant_id, submitter_user_id AS user_id, COUNT(*) AS n FROM atak_tactical_reports
             WHERE submitter_user_id IN ({$in}) GROUP BY tenant_id, submitter_user_id",
            $ids
        ) as $r) {
            $out[$row((int) $r['tenant_id'], (int) $r['user_id'])]['reports'] = (int) $r['n'];
        }

        // Classe du slot Arma, faction, groupe : dernier passage en jeu.
        foreach ($this->rows(
            "SELECT tenant_id, user_id, role, faction, group_name, side, last_seen_at, mission_name, server_name
             FROM operator_game_profiles WHERE user_id IN ({$in}) ORDER BY last_seen_at ASC",
            $ids
        ) as $r) {
            $k = $row((int) $r['tenant_id'], (int) $r['user_id']);
            $out[$k]['game_class'] = trim((string) ($r['role'] ?? ''));
            $out[$k]['faction'] = trim((string) ($r['faction'] ?? ''));
            $out[$k]['group'] = trim((string) ($r['group_name'] ?? ''));
            $out[$k]['side'] = trim((string) ($r['side'] ?? ''));
            $out[$k]['game_seen'] = (string) ($r['last_seen_at'] ?? '');
            $out[$k]['mission'] = trim((string) ($r['mission_name'] ?? ''));
            $out[$k]['server'] = trim((string) ($r['server_name'] ?? ''));
        }

        return $out;
    }

    /**
     * Rôle le plus tenu (hors nom de slot) et slot le plus joué.
     *
     * @param list<array{key: string, label: string, seconds: int}> $roles
     * @return array{0: ?array{key: string, label: string, seconds: int}, 1: ?array{key: string, label: string, seconds: int}}
     */
    public static function topRoles(array $roles): array
    {
        $role = null;
        $slot = null;
        foreach ($roles as $r) {
            $isSlot = str_starts_with(strtoupper($r['key']), 'SLOT:');
            if ($isSlot) {
                $r['label'] = trim(preg_replace('/^SLOT:\s*/i', '', $r['label']) ?? $r['label']);
                if ($slot === null || $r['seconds'] > $slot['seconds']) {
                    $slot = $r;
                }
            } elseif ($role === null || $r['seconds'] > $role['seconds']) {
                $role = $r;
            }
        }

        return [$role, $slot];
    }

    /**
     * Détail des rôles tenus par un joueur dans une communauté (plus long d'abord).
     *
     * @return list<array{key: string, label: string, seconds: int}>
     */
    public function rolesFor(int $tenantId, int $userId): array
    {
        $out = [];
        foreach ($this->rows(
            "SELECT item_key, MAX(item_label) AS item_label, SUM(seconds) AS seconds FROM atak_screen_time
             WHERE tenant_id = ? AND user_id = ? AND kind = 'role' GROUP BY item_key ORDER BY seconds DESC LIMIT 12",
            [$tenantId, $userId]
        ) as $r) {
            $out[] = ['key' => (string) $r['item_key'], 'label' => (string) ($r['item_label'] ?? $r['item_key']), 'seconds' => (int) $r['seconds']];
        }

        return $out;
    }

    /** @return list<array{label: string, kind: string, seconds: int, meters: int}> */
    public function pilotFor(int $tenantId, int $userId): array
    {
        $out = [];
        foreach ($this->rows(
            'SELECT vehicle_class, vehicle_label, vehicle_kind, seconds, meters FROM atak_pilot_time
             WHERE tenant_id = ? AND user_id = ? ORDER BY seconds DESC LIMIT 12',
            [$tenantId, $userId]
        ) as $r) {
            $label = trim((string) ($r['vehicle_label'] ?? ''));
            $out[] = [
                'label' => $label !== '' ? $label : (string) $r['vehicle_class'],
                'kind' => (string) $r['vehicle_kind'],
                'seconds' => (int) $r['seconds'],
                'meters' => (int) $r['meters'],
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function certificatesFor(int $terminalId): array
    {
        return $this->rows(
            'SELECT id, certificate_ref, status, issued_at, expires_at, revoked_at, revoked_reason
             FROM atak_certificates WHERE terminal_id = ? ORDER BY id DESC LIMIT 10',
            [$terminalId]
        );
    }

    // ── Blocages ────────────────────────────────────────────────────────────

    /**
     * @param array{tenant_id: ?int, user_id: ?int, steam_uid: ?string, terminal_uid: ?string, label: string, reason: string, player_message: string, expires_at: ?string, actor_id: ?int} $b
     */
    public function createBlock(array $b): int
    {
        if (!$this->ready()) {
            return 0;
        }
        $steam = $b['steam_uid'] !== null ? SteamId::normalize($b['steam_uid']) : null;
        $st = $this->pdo()->prepare(
            'INSERT INTO atak_admin_blocks
                (tenant_id, user_id, steam_uid, terminal_uid, label, reason, player_message, expires_at, created_by_user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
        );
        $st->execute([
            $b['tenant_id'] ?: null,
            $b['user_id'] ?: null,
            $steam,
            $b['terminal_uid'] !== null && $b['terminal_uid'] !== '' ? mb_substr($b['terminal_uid'], 0, 64) : null,
            mb_substr($b['label'], 0, 190),
            $b['reason'] !== '' ? mb_substr($b['reason'], 0, 500) : null,
            $b['player_message'] !== '' ? mb_substr($b['player_message'], 0, 500) : null,
            $b['expires_at'],
            $b['actor_id'],
        ]);
        $this->blockCache = [];

        return (int) $this->pdo()->lastInsertId();
    }

    public function liftBlock(int $id, ?int $actorId): bool
    {
        if (!$this->ready() || $id < 1) {
            return false;
        }
        $st = $this->pdo()->prepare(
            'UPDATE atak_admin_blocks SET lifted_at = UTC_TIMESTAMP(), lifted_by_user_id = ? WHERE id = ? AND lifted_at IS NULL'
        );
        $st->execute([$actorId, $id]);
        $this->blockCache = [];

        return $st->rowCount() === 1;
    }

    /** @return array<string, mixed>|null */
    public function findBlock(int $id): ?array
    {
        $rows = $this->rows('SELECT * FROM atak_admin_blocks WHERE id = ? LIMIT 1', [$id]);

        return $rows[0] ?? null;
    }

    /**
     * Blocage actif qui vise ce joueur ou cet ATAK. Appelé sur le chemin des envois du jeu :
     * pas de création de table ici (une table absente = aucun blocage).
     *
     * @return array<string, mixed>|null
     */
    public function activeBlockFor(int $tenantId, ?int $userId, ?string $steamUid, ?string $terminalUid = null): ?array
    {
        $steam = $steamUid !== null && $steamUid !== '' ? SteamId::normalize($steamUid) : null;
        $userId = $userId !== null && $userId > 0 ? $userId : null;
        $terminalUid = $terminalUid !== null && trim($terminalUid) !== '' ? trim($terminalUid) : null;
        if ($steam === null && $userId === null && $terminalUid === null) {
            return null;
        }
        $key = $tenantId . '|' . $userId . '|' . $steam . '|' . $terminalUid;
        if (array_key_exists($key, $this->blockCache)) {
            return $this->blockCache[$key];
        }
        $match = [];
        $args = [$tenantId];
        if ($steam !== null) {
            $match[] = 'steam_uid = ?';
            $args[] = $steam;
        }
        if ($userId !== null) {
            $match[] = 'user_id = ?';
            $args[] = $userId;
        }
        if ($terminalUid !== null) {
            $match[] = 'terminal_uid = ?';
            $args[] = $terminalUid;
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT * FROM atak_admin_blocks
                 WHERE lifted_at IS NULL
                   AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())
                   AND (tenant_id IS NULL OR tenant_id = ?)
                   AND (' . implode(' OR ', $match) . ')
                 ORDER BY id DESC LIMIT 1'
            );
            $st->execute($args);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable) {
            $row = null;
        }

        return $this->blockCache[$key] = $row;
    }

    /**
     * Blocages en cours, toutes communautés (pour marquer les lignes de la liste).
     *
     * @return list<array<string, mixed>>
     */
    public function activeBlocks(): array
    {
        if (!$this->ready()) {
            return [];
        }

        return $this->rows(
            'SELECT * FROM atak_admin_blocks
             WHERE lifted_at IS NULL AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())
             ORDER BY id DESC LIMIT 2000'
        );
    }

    /**
     * Le blocage (parmi $blocks) qui vise cette fiche, s'il y en a un.
     *
     * @param array<string, mixed> $terminal
     * @param list<array<string, mixed>> $blocks
     * @return array<string, mixed>|null
     */
    public static function blockMatching(array $terminal, array $blocks): ?array
    {
        $tenantId = (int) ($terminal['tenant_id'] ?? 0);
        $userId = (int) ($terminal['user_id'] ?? 0);
        $steam = SteamId::normalize((string) ($terminal['steam_id'] ?? '')) ?? '';
        $uid = (string) ($terminal['terminal_uid'] ?? '');
        foreach ($blocks as $b) {
            $bt = (int) ($b['tenant_id'] ?? 0);
            if ($bt > 0 && $bt !== $tenantId) {
                continue;
            }
            if (($userId > 0 && (int) ($b['user_id'] ?? 0) === $userId)
                || ($steam !== '' && (string) ($b['steam_uid'] ?? '') === $steam)
                || ($uid !== '' && (string) ($b['terminal_uid'] ?? '') === $uid)) {
                return $b;
            }
        }

        return null;
    }

    /**
     * Historique des blocages d'un joueur ou d'un ATAK.
     *
     * @return list<array<string, mixed>>
     */
    public function blocksFor(?int $userId, ?string $steamUid, ?string $terminalUid): array
    {
        if (!$this->ready()) {
            return [];
        }
        [$match, $args] = $this->targetMatch($userId, $steamUid, $terminalUid);
        if ($match === '') {
            return [];
        }

        return $this->rows(
            "SELECT b.*, a.display_name AS actor_name, a.callsign AS actor_callsign
             FROM atak_admin_blocks b LEFT JOIN users a ON a.id = b.created_by_user_id
             WHERE {$match} ORDER BY b.id DESC LIMIT 30",
            $args
        );
    }

    // ── Messages imposés ────────────────────────────────────────────────────

    /**
     * @param array{tenant_id: int, user_id: ?int, steam_uid: ?string, terminal_uid: ?string, title: string, body: string, level: string, require_ack: bool, expires_at: ?string, actor_id: ?int} $m
     */
    public function createMessage(array $m): int
    {
        if (!$this->ready() || $m['tenant_id'] < 1) {
            return 0;
        }
        $st = $this->pdo()->prepare(
            'INSERT INTO atak_admin_messages
                (tenant_id, user_id, steam_uid, terminal_uid, title, body, level, require_ack, expires_at, created_by_user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
        );
        $st->execute([
            $m['tenant_id'],
            $m['user_id'] ?: null,
            $m['steam_uid'] !== null ? SteamId::normalize($m['steam_uid']) : null,
            $m['terminal_uid'] !== null && $m['terminal_uid'] !== '' ? mb_substr($m['terminal_uid'], 0, 64) : null,
            mb_substr($m['title'], 0, 120),
            mb_substr($m['body'], 0, 2000),
            isset(self::LEVELS[$m['level']]) ? $m['level'] : 'info',
            $m['require_ack'] ? 1 : 0,
            $m['expires_at'],
            $m['actor_id'],
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function cancelMessage(int $id): bool
    {
        if (!$this->ready() || $id < 1) {
            return false;
        }
        $st = $this->pdo()->prepare(
            'UPDATE atak_admin_messages SET cancelled_at = UTC_TIMESTAMP() WHERE id = ? AND cancelled_at IS NULL AND acked_at IS NULL'
        );
        $st->execute([$id]);

        return $st->rowCount() === 1;
    }

    /** @return array<string, mixed>|null */
    public function findMessage(int $id): ?array
    {
        $rows = $this->rows('SELECT * FROM atak_admin_messages WHERE id = ? LIMIT 1', [$id]);

        return $rows[0] ?? null;
    }

    /** @return list<array<string, mixed>> */
    public function messagesFor(?int $userId, ?string $steamUid, ?string $terminalUid): array
    {
        if (!$this->ready()) {
            return [];
        }
        [$match, $args] = $this->targetMatch($userId, $steamUid, $terminalUid);
        if ($match === '') {
            return [];
        }

        return $this->rows(
            "SELECT b.*, a.display_name AS actor_name, a.callsign AS actor_callsign
             FROM atak_admin_messages b LEFT JOIN users a ON a.id = b.created_by_user_id
             WHERE {$match} ORDER BY b.id DESC LIMIT 30",
            $args
        );
    }

    /**
     * Joueurs (clé « tenant:user ») qui ont un message pas encore lu.
     *
     * @return array<string, int>
     */
    public function pendingCountByUser(): array
    {
        if (!$this->ready()) {
            return [];
        }
        $out = [];
        foreach ($this->rows(
            'SELECT tenant_id, user_id, COUNT(*) AS n FROM atak_admin_messages
             WHERE acked_at IS NULL AND cancelled_at IS NULL AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP()) AND user_id IS NOT NULL
             GROUP BY tenant_id, user_id'
        ) as $r) {
            $out[(int) $r['tenant_id'] . ':' . (int) $r['user_id']] = (int) $r['n'];
        }

        return $out;
    }

    /**
     * Messages à afficher en jeu pour une communauté (serveur Arma), éventuellement limités à des Steam.
     *
     * @param list<string> $steamUids
     * @return list<array<string, mixed>>
     */
    public function pendingForTenant(int $tenantId, array $steamUids = []): array
    {
        if ($tenantId < 1 || !$this->ready()) {
            return [];
        }
        $sql = 'SELECT m.id, m.user_id, COALESCE(m.steam_uid, u.steam_id) AS steam_uid, m.terminal_uid, m.title, m.body, m.level,
                       m.require_ack, m.created_at, m.expires_at, m.delivered_at
                FROM atak_admin_messages m LEFT JOIN users u ON u.id = m.user_id
                WHERE m.tenant_id = ? AND m.acked_at IS NULL AND m.cancelled_at IS NULL
                  AND (m.expires_at IS NULL OR m.expires_at > UTC_TIMESTAMP())';
        $args = [$tenantId];
        $steams = array_values(array_filter(array_map(
            static fn (string $s): string => SteamId::normalize($s) ?? '',
            $steamUids
        )));
        if ($steams !== []) {
            $in = implode(',', array_fill(0, count($steams), '?'));
            $sql .= " AND COALESCE(m.steam_uid, u.steam_id) IN ({$in})";
            array_push($args, ...$steams);
        }

        return $this->rows($sql . ' ORDER BY m.id ASC LIMIT 200', $args);
    }

    /**
     * Le serveur Arma signale un message affiché (delivered) ou validé par le joueur (acked).
     *
     * @param list<int> $ids
     */
    public function markMessages(int $tenantId, array $ids, string $state): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
        if ($tenantId < 1 || $ids === [] || !$this->ready()) {
            return 0;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $set = $state === 'acked'
            ? 'acked_at = COALESCE(acked_at, UTC_TIMESTAMP()), delivered_at = COALESCE(delivered_at, UTC_TIMESTAMP())'
            : 'delivered_at = COALESCE(delivered_at, UTC_TIMESTAMP())';
        $st = $this->pdo()->prepare(
            "UPDATE atak_admin_messages SET {$set} WHERE tenant_id = ? AND cancelled_at IS NULL AND id IN ({$in})"
        );
        $st->execute(array_merge([$tenantId], $ids));

        return $st->rowCount();
    }

    // ── Retrait, déconnexion ────────────────────────────────────────────────

    /**
     * Ferme les sessions de jeu du joueur (le jeu doit se reconnecter, et un blocage le refuse alors).
     */
    public function revokeGameSessions(int $tenantId, int $userId): int
    {
        if ($userId < 1) {
            return 0;
        }
        try {
            $sql = 'UPDATE game_sessions SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND revoked_at IS NULL';
            $args = [$userId];
            if ($tenantId > 0) {
                $sql .= ' AND tenant_id = ?';
                $args[] = $tenantId;
            }
            $st = $this->pdo()->prepare($sql);
            $st->execute($args);

            return $st->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Retire un ATAK : certificats révoqués, appareil de confiance révoqué, sessions de jeu fermées, fiche supprimée.
     * Le joueur devra refaire la liaison ; tant qu'il n'est pas bloqué, son ATAK pourra se réenregistrer.
     *
     * @param array<string, mixed> $terminal
     */
    public function retireTerminal(array $terminal, string $reason): bool
    {
        $id = (int) ($terminal['id'] ?? 0);
        $tenantId = (int) ($terminal['tenant_id'] ?? 0);
        $uid = (string) ($terminal['terminal_uid'] ?? '');
        if ($id < 1 || $tenantId < 1) {
            return false;
        }
        $reason = mb_substr($reason !== '' ? $reason : 'Retiré par l’administration du site', 0, 255);
        $this->exec(
            "UPDATE atak_certificates SET status = 'revoked', revoked_at = UTC_TIMESTAMP(), revoked_reason = ?
             WHERE tenant_id = ? AND terminal_id = ? AND status <> 'revoked'",
            [$reason, $tenantId, $id]
        );
        $this->exec(
            "UPDATE atak_trusted_devices SET revoked_at = UTC_TIMESTAMP(), enrollment_status = 'revoked'
             WHERE tenant_id = ? AND revoked_at IS NULL AND (atak_terminal_id = ? OR terminal_uid = ?)",
            [$tenantId, $id, $uid]
        );
        $this->revokeGameSessions($tenantId, (int) ($terminal['user_id'] ?? 0));
        $this->exec('UPDATE atak_certificates SET terminal_id = NULL WHERE tenant_id = ? AND terminal_id = ?', [$tenantId, $id]);
        try {
            $st = $this->pdo()->prepare('DELETE FROM atak_terminals WHERE tenant_id = ? AND id = ?');
            $st->execute([$tenantId, $id]);

            return $st->rowCount() === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    // ── Journal ─────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $terminal fiche visée (tenant_id, user_id, terminal_uid)
     */
    public function log(array $terminal, string $action, string $detail, ?int $actorId, string $actorLabel): void
    {
        if (!$this->ready()) {
            return;
        }
        try {
            $this->pdo()->prepare(
                'INSERT INTO atak_admin_actions (tenant_id, user_id, terminal_uid, label, action, detail, actor_user_id, actor_label, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
            )->execute([
                ((int) ($terminal['tenant_id'] ?? 0)) ?: null,
                ((int) ($terminal['user_id'] ?? 0)) ?: null,
                ($terminal['terminal_uid'] ?? null) ?: null,
                mb_substr(self::playerLabel($terminal), 0, 190),
                mb_substr($action, 0, 32),
                $detail !== '' ? mb_substr($detail, 0, 1000) : null,
                $actorId,
                mb_substr($actorLabel, 0, 190),
            ]);
        } catch (\Throwable) {
            // Le journal ne doit jamais empêcher l'action.
        }
    }

    /** @return list<array<string, mixed>> */
    public function recentActions(int $limit = 40, ?int $userId = null, ?string $terminalUid = null): array
    {
        if (!$this->ready()) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        if ($userId !== null || $terminalUid !== null) {
            $match = [];
            $args = [];
            if ($userId !== null && $userId > 0) {
                $match[] = 'user_id = ?';
                $args[] = $userId;
            }
            if ($terminalUid !== null && $terminalUid !== '') {
                $match[] = 'terminal_uid = ?';
                $args[] = $terminalUid;
            }
            if ($match === []) {
                return [];
            }

            return $this->rows(
                'SELECT * FROM atak_admin_actions WHERE ' . implode(' OR ', $match) . ' ORDER BY id DESC LIMIT ' . $limit,
                $args
            );
        }

        return $this->rows(
            'SELECT a.*, tn.name AS tenant_name FROM atak_admin_actions a LEFT JOIN tenants tn ON tn.id = a.tenant_id
             ORDER BY a.id DESC LIMIT ' . $limit
        );
    }

    // ── Outils ──────────────────────────────────────────────────────────────

    /** Indicatif, sinon nom, sinon identifiant de l'ATAK. */
    public static function playerLabel(array $t): string
    {
        foreach (['operator_callsign', 'user_callsign', 'display_name', 'label', 'terminal_label', 'terminal_uid'] as $k) {
            $v = trim((string) ($t[$k] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return 'ATAK #' . (int) ($t['id'] ?? 0);
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function targetMatch(?int $userId, ?string $steamUid, ?string $terminalUid): array
    {
        $match = [];
        $args = [];
        if ($userId !== null && $userId > 0) {
            $match[] = 'b.user_id = ?';
            $args[] = $userId;
        }
        $steam = $steamUid !== null ? SteamId::normalize($steamUid) : null;
        if ($steam !== null) {
            $match[] = 'b.steam_uid = ?';
            $args[] = $steam;
        }
        if ($terminalUid !== null && $terminalUid !== '') {
            $match[] = 'b.terminal_uid = ?';
            $args[] = $terminalUid;
        }

        return [$match === [] ? '' : '(' . implode(' OR ', $match) . ')', $args];
    }

    /**
     * @param list<mixed> $args
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $args = []): array
    {
        try {
            $st = $this->pdo()->prepare($sql);
            $st->execute($args);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param list<mixed> $args */
    private function exec(string $sql, array $args): void
    {
        try {
            $this->pdo()->prepare($sql)->execute($args);
        } catch (\Throwable) {
            // Table ou colonne absente : on continue le retrait.
        }
    }
}
