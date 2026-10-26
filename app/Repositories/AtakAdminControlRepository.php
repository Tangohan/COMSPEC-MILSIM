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
        'compromise' => 'Compromission déclarée',
        'compromise_undo' => 'Compromission annulée',
        'cert_revoke' => 'Certificat révoqué',
        'cert_restore' => 'Certificat rétabli',
        'note' => 'Note interne',
        'note_gestion' => 'Note de la gestion',
        'link_force' => 'Liaison forcée',
        'link_approve' => 'Liaison validée',
        'link_code' => 'Code de liaison généré',
        'unlink' => 'Steam délié',
    ];

    /** États de compromission d'un ATAK (atak_terminals.compromise_state). */
    public const COMPROMISE_STATES = [
        'captured' => 'Capturé',
        'compromised' => 'Compromis',
        'none' => 'Contrôle rétabli',
    ];

    /** D'où vient une compromission. */
    public const COMPROMISE_SOURCES = [
        'game' => 'En jeu (validée sur le terrain)',
        'position' => 'Remontée du terrain',
        'web' => 'Poste de commandement',
        'admin' => 'Administration du site',
        'gestion' => 'Gestion de la communauté',
    ];

    /** Avis automatiques que l'ATAK du joueur met en forme et affiche en alerte (genre => niveau). */
    public const NOTICE_KINDS = [
        'message' => 'info',
        'compromise' => 'critical',
        'compromise_cleared' => 'info',
        'reset' => 'warning',
        'certificate' => 'warning',
        'phone' => 'info',
        // CR de fin d'opération imposé (AtakDebriefService) : le téléphone ouvre la fiche à remplir ou à lire.
        'debrief' => 'warning',
    ];

    /** Filtres d'état de la liste. */
    public const STATES = [
        '' => 'Tous',
        'actifs' => 'Vus depuis 7 jours',
        'bloques' => 'Bloqués',
        'compromis' => 'Compromis',
        'messages' => 'Message en attente',
        'dormants' => 'Sans signe depuis 30 jours',
        'steam' => 'Joueurs Steam sans ATAK',
        'a_verifier' => 'Compte à vérifier',
        'sans_carte' => 'Formule sans la carte',
    ];

    public const SORTS = [
        'recent' => 'Dernier signe de vie',
        'jeu' => 'Temps de jeu',
        'distance' => 'Distance totale',
        'nom' => 'Indicatif',
        'compte' => 'Compte Athena (problèmes d’abord)',
        'communaute' => 'Communauté',
        'formule' => 'Formule (sans la carte d’abord)',
        'etat' => 'État (bloqués d’abord)',
    ];

    /** Filtres et tris réservés à l'administration de la plateforme (corrélation, formule, joueurs sans compte). */
    public const PLATFORM_ONLY = ['steam', 'a_verifier', 'sans_carte', 'compte', 'communaute', 'formule'];

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

    // ── Joueurs Steam et corrélation (administration de la plateforme) ──────

    /**
     * Joueurs vus en jeu par leur Steam (operator_game_profiles, gardé même sans compte Athena), plus récents d'abord.
     *
     * @return list<array<string, mixed>>
     */
    public function steamPlayers(string $search = '', int $tenantId = 0, int $limit = 2000): array
    {
        $where = ['1 = 1'];
        $args = [];
        if ($tenantId > 0) {
            $where[] = 'p.tenant_id = ?';
            $args[] = $tenantId;
        }
        $search = trim($search);
        if ($search !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($search, 0, 80)) . '%';
            $where[] = '(p.steam_id LIKE ? OR p.callsign LIKE ? OR p.arma_player_name LIKE ? OR p.display_name LIKE ? OR tn.name LIKE ?)';
            array_push($args, $like, $like, $like, $like, $like);
        }

        return $this->rows(
            'SELECT p.tenant_id, p.user_id, p.steam_id, p.callsign, p.arma_player_name, p.display_name, p.role, p.faction,
                    p.atak_version, p.server_name, p.mission_name, p.first_seen_at, p.last_seen_at,
                    tn.name AS tenant_name, tn.slug AS tenant_slug
             FROM operator_game_profiles p LEFT JOIN tenants tn ON tn.id = p.tenant_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY p.last_seen_at DESC LIMIT ' . max(1, min(2000, $limit)),
            $args
        );
    }

    /**
     * Steam vus par les rapports du mod (refus 401/403 compris) sur les $days derniers jours, un par Steam.
     * Seule trace d'un joueur sans compte quand le serveur ne remonte pas ses joueurs.
     *
     * @return list<array<string, mixed>>
     */
    public function steamFromModReports(string $search = '', int $days = 30, int $limit = 500): array
    {
        $args = [max(1, min(365, $days))];
        $filter = '';
        $search = trim($search);
        if ($search !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($search, 0, 80)) . '%';
            $filter = ' AND (steam_uid LIKE ? OR callsign LIKE ? OR player_name LIKE ?)';
            array_push($args, $like, $like, $like);
        }
        $out = [];
        foreach ($this->rows(
            "SELECT steam_uid, callsign, player_name, mod_version, extension_version, message, hit_count, last_seen_at
             FROM atak_mod_reports
             WHERE steam_uid IS NOT NULL AND steam_uid <> '' AND last_seen_at >= UTC_TIMESTAMP() - INTERVAL ? DAY" . $filter . '
             ORDER BY last_seen_at DESC LIMIT 5000',
            $args
        ) as $r) {
            $steam = SteamId::normalize((string) $r['steam_uid']);
            if ($steam === null) {
                continue;
            }
            if (!isset($out[$steam])) {
                if (count($out) >= $limit) {
                    continue;
                }
                $out[$steam] = $r + ['steam_id' => $steam, 'reports' => 0, 'denied' => 0, 'last_denial' => ''];
            }
            $out[$steam]['reports'] += max(1, (int) $r['hit_count']);
            $text = strtolower((string) $r['message']);
            if (str_contains($text, '403') || str_contains($text, '401') || str_contains($text, 'unauthorized') || str_contains($text, 'forbidden')
                || str_contains($text, 'feature_unavailable') || str_contains($text, 'blocked')) {
                $out[$steam]['denied'] += max(1, (int) $r['hit_count']);
                if ($out[$steam]['last_denial'] === '') {
                    $out[$steam]['last_denial'] = mb_substr((string) $r['message'], 0, 160);
                }
            }
            foreach (['callsign', 'player_name', 'mod_version'] as $k) {
                if (trim((string) ($out[$steam][$k] ?? '')) === '' && trim((string) ($r[$k] ?? '')) !== '') {
                    $out[$steam][$k] = $r[$k];
                }
            }
        }

        return array_values($out);
    }

    /**
     * Fiches membres qui portent ces Steam, toutes communautés (plusieurs fiches = doublon).
     *
     * @param list<string> $steamIds
     * @return array<string, list<array{id: int, tenant_id: int, status: string, email: string, steam_id: string, name: string}>>
     */
    public function usersBySteam(array $steamIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn ($s): string => SteamId::normalize((string) $s) ?? '', $steamIds))));
        $out = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach ($this->rows(
                'SELECT id, tenant_id, status, email, steam_id, callsign, display_name FROM users WHERE steam_id IN ('
                . implode(',', array_fill(0, count($chunk), '?')) . ') ORDER BY id',
                $chunk
            ) as $u) {
                $steam = SteamId::normalize((string) $u['steam_id']) ?? '';
                if ($steam === '') {
                    continue;
                }
                $out[$steam][] = [
                    'id' => (int) $u['id'],
                    'tenant_id' => (int) ($u['tenant_id'] ?? 0),
                    'status' => (string) ($u['status'] ?? ''),
                    'email' => (string) ($u['email'] ?? ''),
                    'steam_id' => $steam,
                    'name' => trim((string) ($u['callsign'] ?? '')) ?: trim((string) ($u['display_name'] ?? '')),
                ];
            }
        }

        return $out;
    }

    /**
     * Steam présents sur un compte Athena (athena_accounts), pour repérer ceux qui ne sont sur aucune fiche membre.
     *
     * @param list<string> $steamIds
     * @return array<string, true>
     */
    public function accountSteams(array $steamIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn ($s): string => SteamId::normalize((string) $s) ?? '', $steamIds))));
        $out = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach ($this->rows(
                'SELECT steam_id FROM athena_accounts WHERE steam_id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')',
                $chunk
            ) as $a) {
                $out[(string) $a['steam_id']] = true;
            }
        }

        return $out;
    }

    /**
     * Steam vu en jeu pour ces membres (clé « tenant:user »).
     *
     * @param list<int> $userIds
     * @return array<string, string>
     */
    public function gameSteamByUser(array $userIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        $out = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach ($this->rows(
                'SELECT tenant_id, user_id, steam_id FROM operator_game_profiles WHERE user_id IN ('
                . implode(',', array_fill(0, count($chunk), '?')) . ') ORDER BY last_seen_at',
                $chunk
            ) as $p) {
                $steam = SteamId::normalize((string) $p['steam_id']);
                if ($steam !== null) {
                    $out[(int) $p['tenant_id'] . ':' . (int) $p['user_id']] = $steam;
                }
            }
        }

        return $out;
    }

    /**
     * Fiches membres par id (corrélation des ATAK enregistrés).
     *
     * @param list<int> $userIds
     * @return array<int, array<string, mixed>>
     */
    public function usersById(array $userIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        $out = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach ($this->rows(
                'SELECT id, tenant_id, status, email, steam_id FROM users WHERE id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')',
                $chunk
            ) as $u) {
                $out[(int) $u['id']] = $u;
            }
        }

        return $out;
    }

    /**
     * Communautés : nom, type, formule appliquée (offerte, abonnement, essai) et accès du jeu qu'elle donne.
     *
     * @param list<int> $tenantIds
     * @return array<int, array{id: int, name: string, slug: string, type: string, type_label: string, plan: string, plan_label: string, gift: bool, atak: string}>
     */
    public function tenantProfiles(array $tenantIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $tenantIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $plans = [];
        foreach ($this->rows('SELECT slug, name FROM subscription_plans') as $p) {
            $plans[(string) $p['slug']] = (string) $p['name'];
        }
        $out = [];
        foreach ($this->rows('SELECT * FROM tenants WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids) as $t) {
            $id = (int) $t['id'];
            $plan = \App\Support\PlanGift::effectivePlanSlug($t);
            $type = \App\Services\Community\TenantTypeConfig::normalizeType((string) ($t['tenant_type'] ?? 'full'));
            $out[$id] = [
                'id' => $id,
                'name' => trim((string) ($t['name'] ?? '')) ?: ('Communauté #' . $id),
                'slug' => (string) ($t['slug'] ?? ''),
                'type' => $type,
                'type_label' => \App\Services\Community\TenantTypeConfig::label($type),
                'plan' => $plan,
                'plan_label' => $plans[$plan] ?? ucfirst($plan),
                'gift' => \App\Support\PlanGift::activeFromSettings($t['settings'] ?? null) !== null,
                'atak' => \App\Support\AtakPlanAccess::allows($id) ? 'full' : (\App\Support\AtakPlanAccess::allowsBasic($id) ? 'basic' : 'none'),
            ];
        }

        return $out;
    }

    /**
     * Joueur Steam (ligne sans ATAK enregistré) visé par une action : profil vu en jeu, sinon rapports du mod.
     *
     * @return array<string, mixed>|null
     */
    public function findSteamPlayer(int $tenantId, string $steamId): ?array
    {
        $steam = SteamId::normalize($steamId);
        if ($steam === null) {
            return null;
        }
        $label = '';
        if ($tenantId > 0) {
            $p = $this->rows('SELECT callsign, arma_player_name, display_name FROM operator_game_profiles WHERE tenant_id = ? AND steam_id = ? LIMIT 1', [$tenantId, $steam]);
            $label = trim((string) ($p[0]['callsign'] ?? '')) ?: trim((string) ($p[0]['arma_player_name'] ?? '')) ?: trim((string) ($p[0]['display_name'] ?? ''));
        }
        if ($label === '') {
            $r = $this->rows('SELECT callsign, player_name FROM atak_mod_reports WHERE steam_uid = ? ORDER BY last_seen_at DESC LIMIT 1', [$steam]);
            $label = trim((string) ($r[0]['callsign'] ?? '')) ?: trim((string) ($r[0]['player_name'] ?? ''));
        }
        $userId = 0;
        foreach ($this->usersBySteam([$steam])[$steam] ?? [] as $u) {
            if ($tenantId > 0 && $u['tenant_id'] === $tenantId) {
                $userId = $u['id'];
            }
        }

        return [
            'id' => 0,
            'tenant_id' => $tenantId,
            'user_id' => $userId ?: null,
            'terminal_uid' => '',
            'steam_id' => $steam,
            'operator_callsign' => $label !== '' ? $label : 'Steam ' . $steam,
        ];
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
    public function blocksFor(?int $userId, ?string $steamUid, ?string $terminalUid, int $tenantId = 0): array
    {
        if (!$this->ready()) {
            return [];
        }
        [$match, $args] = $this->targetMatch($userId, $steamUid, $terminalUid);
        if ($match === '') {
            return [];
        }
        // Vue d'une communauté : ses blocages et ceux de la plateforme (toutes communautés), jamais ceux d'une autre.
        if ($tenantId > 0) {
            $match .= ' AND (b.tenant_id IS NULL OR b.tenant_id = ?)';
            $args[] = $tenantId;
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
     * @param array{tenant_id: int, user_id: ?int, steam_uid: ?string, terminal_uid: ?string, title: string, body: string, level: string, require_ack: bool, expires_at: ?string, actor_id: ?int, kind?: string} $m
     */
    public function createMessage(array $m): int
    {
        if (!$this->ready() || $m['tenant_id'] < 1) {
            return 0;
        }
        $st = $this->pdo()->prepare(
            'INSERT INTO atak_admin_messages
                (tenant_id, user_id, steam_uid, terminal_uid, kind, title, body, level, require_ack, expires_at, created_by_user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
        );
        $kind = (string) ($m['kind'] ?? 'message');
        $st->execute([
            $m['tenant_id'],
            $m['user_id'] ?: null,
            $m['steam_uid'] !== null ? SteamId::normalize($m['steam_uid']) : null,
            $m['terminal_uid'] !== null && $m['terminal_uid'] !== '' ? mb_substr($m['terminal_uid'], 0, 64) : null,
            isset(self::NOTICE_KINDS[$kind]) ? $kind : 'message',
            mb_substr($m['title'], 0, 120),
            mb_substr($m['body'], 0, 2000),
            isset(self::LEVELS[$m['level']]) ? $m['level'] : 'info',
            $m['require_ack'] ? 1 : 0,
            $m['expires_at'],
            $m['actor_id'],
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Avis automatique au joueur, affiché en alerte par son ATAK (compromission, annulation, réinitialisation…).
     * Le joueur le valide en jeu. Ne bloque jamais l'action qui l'envoie.
     *
     * @param array<string, mixed> $target fiche ATAK ou joueur (tenant_id, user_id, steam_id, terminal_uid)
     */
    public function notifyPlayer(array $target, string $kind, string $title, string $detail = '', ?int $actorId = null): int
    {
        $tenantId = (int) ($target['tenant_id'] ?? 0);
        $userId = (int) ($target['user_id'] ?? 0);
        $steam = trim((string) ($target['steam_id'] ?? $target['steam_uid'] ?? ''));
        if ($tenantId < 1 || ($userId < 1 && $steam === '') || !isset(self::NOTICE_KINDS[$kind])) {
            return 0;
        }
        try {
            return $this->createMessage([
                'tenant_id' => $tenantId,
                'user_id' => $userId ?: null,
                'steam_uid' => $steam !== '' ? $steam : null,
                'terminal_uid' => trim((string) ($target['terminal_uid'] ?? '')) ?: null,
                'kind' => $kind,
                'title' => $title,
                'body' => $detail,
                'level' => self::NOTICE_KINDS[$kind],
                'require_ack' => true,
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 14 * 86400),
                'actor_id' => $actorId,
            ]);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Messages pas encore validés d'un joueur (son ATAK les récupère avec sa session de jeu).
     *
     * @return list<array<string, mixed>>
     */
    public function pendingForPlayer(int $tenantId, ?int $userId, ?string $steamUid): array
    {
        if ($tenantId < 1 || !$this->ready()) {
            return [];
        }
        [$match, $args] = $this->playerMatch($userId, $steamUid);
        if ($match === '') {
            return [];
        }

        return $this->rows(
            "SELECT m.* FROM atak_admin_messages m
             WHERE m.tenant_id = ? AND m.acked_at IS NULL AND m.cancelled_at IS NULL
               AND (m.expires_at IS NULL OR m.expires_at > UTC_TIMESTAMP()) AND {$match}
             ORDER BY m.id ASC LIMIT 20",
            array_merge([$tenantId], $args)
        );
    }

    /**
     * L'ATAK du joueur signale ses messages affichés (delivered) ou validés (acked) ; seulement les siens.
     *
     * @param list<int> $ids
     */
    public function markMessagesForPlayer(int $tenantId, ?int $userId, ?string $steamUid, array $ids, string $state): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
        [$match, $args] = $this->playerMatch($userId, $steamUid);
        if ($tenantId < 1 || $ids === [] || $match === '' || !$this->ready()) {
            return 0;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $set = $state === 'acked'
            ? 'acked_at = COALESCE(acked_at, UTC_TIMESTAMP()), delivered_at = COALESCE(delivered_at, UTC_TIMESTAMP())'
            : 'delivered_at = COALESCE(delivered_at, UTC_TIMESTAMP())';
        try {
            $st = $this->pdo()->prepare(
                "UPDATE atak_admin_messages m SET {$set} WHERE m.tenant_id = ? AND m.cancelled_at IS NULL AND m.id IN ({$in}) AND {$match}"
            );
            $st->execute(array_merge([$tenantId], $ids, $args));

            return $st->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function playerMatch(?int $userId, ?string $steamUid): array
    {
        $match = [];
        $args = [];
        if ($userId !== null && $userId > 0) {
            $match[] = 'm.user_id = ?';
            $args[] = $userId;
        }
        $steam = $steamUid !== null ? SteamId::normalize($steamUid) : null;
        if ($steam !== null) {
            $match[] = 'm.steam_uid = ?';
            $args[] = $steam;
        }

        return [$match === [] ? '' : '(' . implode(' OR ', $match) . ')', $args];
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
    public function messagesFor(?int $userId, ?string $steamUid, ?string $terminalUid, int $tenantId = 0): array
    {
        if (!$this->ready()) {
            return [];
        }
        [$match, $args] = $this->targetMatch($userId, $steamUid, $terminalUid);
        if ($match === '') {
            return [];
        }
        if ($tenantId > 0) {
            $match .= ' AND b.tenant_id = ?';
            $args[] = $tenantId;
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
        $sql = 'SELECT m.*, COALESCE(m.steam_uid, u.steam_id) AS steam_uid,
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

    // ── Compromissions ──────────────────────────────────────────────────────

    /**
     * Inscrit une compromission (ou un retour au contrôle) dans l'historique de l'ATAK.
     *
     * @param array<string, mixed> $terminal fiche atak_terminals (id, tenant_id, terminal_uid, indicatif)
     * @param list<int> $certificateIds certificats révoqués par cet événement
     */
    public function recordCompromise(
        array $terminal,
        string $state,
        ?string $reason,
        string $source,
        array $certificateIds = [],
        ?int $actorId = null,
        string $actorLabel = ''
    ): int {
        $tenantId = (int) ($terminal['tenant_id'] ?? 0);
        $uid = trim((string) ($terminal['terminal_uid'] ?? ''));
        if ($tenantId < 1 || $uid === '' || !isset(self::COMPROMISE_STATES[$state]) || !$this->ready()) {
            return 0;
        }
        $ids = array_values(array_filter(array_map('intval', $certificateIds), static fn (int $id): bool => $id > 0));
        try {
            $this->pdo()->prepare(
                'INSERT INTO atak_compromise_events
                    (tenant_id, terminal_id, terminal_uid, label, state, reason, source, certificate_ids, actor_user_id, actor_label, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
            )->execute([
                $tenantId,
                ((int) ($terminal['id'] ?? 0)) ?: null,
                mb_substr($uid, 0, 64),
                mb_substr(self::playerLabel($terminal), 0, 190),
                $state,
                $reason !== null && trim($reason) !== '' ? mb_substr(trim($reason), 0, 255) : null,
                isset(self::COMPROMISE_SOURCES[$source]) ? $source : 'game',
                $ids !== [] ? mb_substr(implode(',', $ids), 0, 500) : null,
                $actorId,
                $actorLabel !== '' ? mb_substr($actorLabel, 0, 190) : null,
            ]);

            return (int) $this->pdo()->lastInsertId();
        } catch (\Throwable) {
            // L'historique ne doit jamais empêcher la compromission.
            return 0;
        }
    }

    /**
     * Historique des compromissions d'un ATAK, plus récentes d'abord.
     *
     * @return list<array<string, mixed>>
     */
    public function compromiseEventsFor(int $tenantId, string $terminalUid, int $limit = 50): array
    {
        if ($tenantId < 1 || trim($terminalUid) === '' || !$this->ready()) {
            return [];
        }

        return $this->rows(
            'SELECT * FROM atak_compromise_events WHERE tenant_id = ? AND terminal_uid = ?
             ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min(200, $limit)),
            [$tenantId, trim($terminalUid)]
        );
    }

    /** @return array<string, mixed>|null */
    public function findCompromiseEvent(int $id): ?array
    {
        if ($id < 1 || !$this->ready()) {
            return null;
        }

        return $this->rows('SELECT * FROM atak_compromise_events WHERE id = ? LIMIT 1', [$id])[0] ?? null;
    }

    /**
     * Déclare l'ATAK capturé ou compromis depuis l'administration. « Compromis » révoque ses certificats valides,
     * comme une compromission validée sur le terrain.
     *
     * @param array<string, mixed> $terminal
     * @return array{event_id: int, revoked: int}
     */
    public function markCompromised(array $terminal, string $state, string $reason, bool $revokeCertificates, ?int $actorId, string $actorLabel, string $source = 'admin'): array
    {
        $source = isset(self::COMPROMISE_SOURCES[$source]) ? $source : 'admin';
        $tenantId = (int) ($terminal['tenant_id'] ?? 0);
        $id = (int) ($terminal['id'] ?? 0);
        $state = $state === 'compromised' ? 'compromised' : 'captured';
        if ($tenantId < 1 || $id < 1) {
            return ['event_id' => 0, 'revoked' => 0];
        }
        $this->exec(
            'UPDATE atak_terminals SET compromise_state = ?, compromised_at = UTC_TIMESTAMP(), compromise_reason = ?, updated_at = UTC_TIMESTAMP()
             WHERE tenant_id = ? AND id = ?',
            [$state, mb_substr($reason !== '' ? $reason : ($source === 'gestion' ? 'Déclaré par la gestion de la communauté' : 'Déclaré par l’administration du site'), 0, 255), $tenantId, $id]
        );
        $revoked = [];
        if ($revokeCertificates) {
            foreach ($this->certificatesFor($id) as $c) {
                if (in_array((string) $c['status'], ['active', 'issued'], true) && $this->setCertificateRevoked($terminal, (int) $c['id'], true, 'keyCompromise')) {
                    $revoked[] = (int) $c['id'];
                }
            }
        }

        return [
            'event_id' => $this->recordCompromise($terminal, $state, $reason, $source, $revoked, $actorId, $actorLabel),
            'revoked' => count($revoked),
        ];
    }

    /**
     * Annule une compromission : l'ATAK repasse sous contrôle (si l'événement annulé est la compromission en cours)
     * et, au choix, les certificats qu'elle avait révoqués sont rétablis (sauf expirés).
     * Sans événement (compromission plus ancienne que l'historique), on annule l'état en cours.
     *
     * @param array<string, mixed> $terminal
     * @param array<string, mixed>|null $event
     * @return array{cleared: bool, restored: int}
     */
    public function undoCompromise(array $terminal, ?array $event, bool $restoreCertificates, string $note, ?int $actorId, string $actorLabel, string $source = 'admin'): array
    {
        $source = isset(self::COMPROMISE_SOURCES[$source]) ? $source : 'admin';
        $tenantId = (int) ($terminal['tenant_id'] ?? 0);
        $id = (int) ($terminal['id'] ?? 0);
        $uid = (string) ($terminal['terminal_uid'] ?? '');
        if ($tenantId < 1 || $id < 1) {
            return ['cleared' => false, 'restored' => 0];
        }
        $current = (string) ($terminal['compromise_state'] ?? 'none');
        $isCurrent = !in_array($current, ['', 'none'], true);
        if ($event !== null) {
            // N'annule l'état en cours que si aucune compromission plus récente (encore valable) ne le porte.
            foreach ($this->compromiseEventsFor($tenantId, $uid, 200) as $e) {
                if ((int) $e['id'] === (int) $event['id']) {
                    break;
                }
                if ((string) $e['state'] !== 'none' && empty($e['undone_at'])) {
                    $isCurrent = false;
                    break;
                }
            }
        }

        $restored = 0;
        if ($restoreCertificates) {
            $certIds = $event !== null
                ? array_filter(array_map('intval', explode(',', (string) ($event['certificate_ids'] ?? ''))))
                : $this->certificatesRevokedByCompromise($terminal);
            foreach ($certIds as $certId) {
                if ($this->setCertificateRevoked($terminal, (int) $certId, false)) {
                    $restored++;
                }
            }
        }

        if ($isCurrent) {
            $this->exec(
                "UPDATE atak_terminals SET compromise_state = 'none', compromised_at = NULL, compromise_reason = NULL, updated_at = UTC_TIMESTAMP()
                 WHERE tenant_id = ? AND id = ?",
                [$tenantId, $id]
            );
        }
        $note = mb_substr(trim($note), 0, 255);
        $where = $event !== null ? 'id = ?' : "tenant_id = ? AND terminal_uid = ? AND state <> 'none'";
        $args = $event !== null ? [(int) $event['id']] : [$tenantId, $uid];
        $this->exec(
            'UPDATE atak_compromise_events SET undone_at = UTC_TIMESTAMP(), undone_by_user_id = ?, undone_by_label = ?, undo_note = ?
             WHERE undone_at IS NULL AND ' . $where,
            array_merge([$actorId, mb_substr($actorLabel, 0, 190), $note !== '' ? $note : null], $args)
        );
        if ($isCurrent) {
            $this->recordCompromise($terminal, 'none', $note !== '' ? $note : ($source === 'gestion' ? 'Compromission annulée par la gestion de la communauté' : 'Compromission annulée par l’administration du site'), $source, [], $actorId, $actorLabel);
        }

        return ['cleared' => $isCurrent, 'restored' => $restored];
    }

    /**
     * Certificats d'un ATAK révoqués pour compromission (motif keyCompromise) depuis sa compromission en cours.
     *
     * @param array<string, mixed> $terminal
     * @return list<int>
     */
    private function certificatesRevokedByCompromise(array $terminal): array
    {
        $since = \App\Support\UtcDateTime::timestamp($terminal['compromised_at'] ?? null);
        $out = [];
        foreach ($this->certificatesFor((int) ($terminal['id'] ?? 0)) as $c) {
            if ((string) $c['status'] !== 'revoked' || (string) ($c['revoked_reason'] ?? '') !== 'keyCompromise') {
                continue;
            }
            $at = \App\Support\UtcDateTime::timestamp($c['revoked_at'] ?? null);
            if ($since === null || ($at !== null && $at >= $since - 120)) {
                $out[] = (int) $c['id'];
            }
        }

        return $out;
    }

    // ── Certificats ─────────────────────────────────────────────────────────

    /**
     * Révoque ou rétablit un certificat de l'ATAK. Un certificat expiré n'est jamais rétabli.
     *
     * @param array<string, mixed> $terminal
     */
    public function setCertificateRevoked(array $terminal, int $certificateId, bool $revoke, string $reason = 'Révoqué par l’administration du site'): bool
    {
        $tenantId = (int) ($terminal['tenant_id'] ?? 0);
        $terminalId = (int) ($terminal['id'] ?? 0);
        if ($tenantId < 1 || $terminalId < 1 || $certificateId < 1) {
            return false;
        }
        try {
            if ($revoke) {
                $st = $this->pdo()->prepare(
                    "UPDATE atak_certificates SET status = 'revoked', revoked_at = UTC_TIMESTAMP(), revoked_reason = ?, updated_at = UTC_TIMESTAMP()
                     WHERE tenant_id = ? AND terminal_id = ? AND id = ? AND status IN ('active', 'issued')"
                );
                $st->execute([mb_substr($reason, 0, 255), $tenantId, $terminalId, $certificateId]);
            } else {
                $st = $this->pdo()->prepare(
                    "UPDATE atak_certificates SET status = 'active', revoked_at = NULL, revoked_reason = NULL, updated_at = UTC_TIMESTAMP()
                     WHERE tenant_id = ? AND terminal_id = ? AND id = ? AND status = 'revoked'
                       AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())"
                );
                $st->execute([$tenantId, $terminalId, $certificateId]);
            }

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
    public function recentActions(int $limit = 40, ?int $userId = null, ?string $terminalUid = null, int $tenantId = 0): array
    {
        if (!$this->ready()) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        // Vue d'une communauté : seulement les actions sur ses ATAK.
        // Les notes internes de l'administration de la plateforme ne sont pas montrées à la gestion.
        $scope = $tenantId > 0 ? ' AND a.tenant_id = ' . $tenantId . " AND a.action <> 'note'" : '';
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
                'SELECT a.* FROM atak_admin_actions a WHERE (' . implode(' OR ', $match) . ')' . $scope . ' ORDER BY a.id DESC LIMIT ' . $limit,
                $args
            );
        }

        return $this->rows(
            'SELECT a.*, tn.name AS tenant_name FROM atak_admin_actions a LEFT JOIN tenants tn ON tn.id = a.tenant_id
             WHERE 1 = 1' . $scope . ' ORDER BY a.id DESC LIMIT ' . $limit
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
