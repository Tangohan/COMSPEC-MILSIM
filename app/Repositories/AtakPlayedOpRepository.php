<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\AtakMissionKey;
use App\Support\AtakPlayedOpRules;
use PDO;

/**
 * Parties détectées en jeu (clé de mission ATAK) et leur gestion : nom, type, catégorie, partie ignorée ou supprimée
 * (logiquement), rattachement à une opération planifiée, un cycle de mission ou un événement ; types, règles
 * d'exclusion et catégories de la communauté. Tout est filtré par communauté.
 *
 * Données lues (rien n'est inventé) : atak_screen_time (joueurs, temps de jeu, dernière remontée), atak_tactical_reports
 * (remontées), atak_debriefs (CR imposés), aar_reports (comptes rendus liés), mission_plans / theatre_mission_cycles /
 * community_events (ce qui était prévu), pilot_logbook_entries (heures de vol).
 */
final class AtakPlayedOpRepository
{
    private static ?bool $schema = null;

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /** Crée colonnes et tables au premier usage (bootstrap/atak_played_ops_migration.php). */
    public function ready(): bool
    {
        if (self::$schema !== null) {
            return self::$schema;
        }
        try {
            require_once dirname(__DIR__, 2) . '/bootstrap/atak_played_ops_migration.php';
            ob_start();
            try {
                run_atak_played_ops_migration($this->pdo());
            } finally {
                ob_end_clean();
            }
            self::$schema = true;
        } catch (\Throwable) {
            self::$schema = false;
        }

        return self::$schema;
    }

    // ── Parties détectées ───────────────────────────────────────────────────

    /**
     * Parties remontées par les ATAK sur les $days derniers jours, la plus récente d'abord.
     *
     * @return array<string, array{mission_key: string, day: string, players: int, play_seconds: int, max_play_seconds: int, last_seen: string, reports: int}>
     */
    public function detected(int $tenantId, int $days = 90, int $limit = 80): array
    {
        $out = [];
        $since = date('Y-m-d', time() - max(1, $days) * 86400);
        foreach ($this->rows(
            "SELECT mission_key, MAX(day) AS day, COUNT(DISTINCT user_id) AS players, MAX(updated_at) AS last_seen,
                    SUM(CASE WHEN kind = 'play' AND item_key = 'TOTAL' THEN seconds ELSE 0 END) AS play_seconds
             FROM atak_screen_time WHERE tenant_id = ? AND mission_key <> '' AND day >= ?
             GROUP BY mission_key ORDER BY MAX(updated_at) DESC LIMIT " . max(1, min(300, $limit)),
            [$tenantId, $since]
        ) as $r) {
            $key = (string) $r['mission_key'];
            $out[$key] = [
                'mission_key' => $key, 'day' => (string) $r['day'], 'players' => (int) $r['players'],
                'play_seconds' => (int) $r['play_seconds'], 'max_play_seconds' => 0,
                'last_seen' => (string) ($r['last_seen'] ?? ''), 'reports' => 0,
            ];
        }
        if ($out !== []) {
            $keys = array_keys($out);
            $in = implode(',', array_fill(0, count($keys), '?'));
            foreach ($this->rows(
                "SELECT mission_key, MAX(s) AS m FROM (SELECT mission_key, user_id, SUM(seconds) AS s FROM atak_screen_time
                 WHERE tenant_id = ? AND kind = 'play' AND item_key = 'TOTAL' AND mission_key IN ({$in}) GROUP BY mission_key, user_id) x
                 GROUP BY mission_key",
                array_merge([$tenantId], $keys)
            ) as $r) {
                $out[(string) $r['mission_key']]['max_play_seconds'] = (int) $r['m'];
            }
        }
        foreach ($this->rows(
            "SELECT mission_key, DATE(MAX(created_at)) AS day, COUNT(*) AS n, MAX(created_at) AS last_seen,
                    COUNT(DISTINCT COALESCE(submitter_user_id, submitter_steam_id)) AS players
             FROM atak_tactical_reports WHERE tenant_id = ? AND mission_key IS NOT NULL AND mission_key <> '' AND created_at >= ?
             GROUP BY mission_key LIMIT 300",
            [$tenantId, $since . ' 00:00:00']
        ) as $r) {
            $key = (string) $r['mission_key'];
            if (isset($out[$key])) {
                $out[$key]['reports'] = (int) $r['n'];
                continue;
            }
            $out[$key] = [
                'mission_key' => $key, 'day' => (string) $r['day'], 'players' => (int) $r['players'], 'play_seconds' => 0,
                'max_play_seconds' => 0, 'last_seen' => (string) ($r['last_seen'] ?? ''), 'reports' => (int) $r['n'],
            ];
        }

        return $out;
    }

    /** @return list<int> joueurs (comptes du site) d'une partie, d'après le temps de jeu */
    public function userIds(int $tenantId, string $missionKey): array
    {
        return array_map('intval', array_column($this->rows(
            'SELECT DISTINCT user_id FROM atak_screen_time WHERE tenant_id = ? AND mission_key = ? LIMIT 400',
            [$tenantId, $missionKey]
        ), 'user_id'));
    }

    /** @return array<string, list<int>> clé → joueurs, pour plusieurs parties */
    public function userIdsFor(int $tenantId, array $keys): array
    {
        $keys = array_values(array_filter($keys, 'is_string'));
        if ($keys === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($keys), '?'));
        $out = [];
        foreach ($this->rows(
            "SELECT DISTINCT mission_key, user_id FROM atak_screen_time WHERE tenant_id = ? AND mission_key IN ({$in})",
            array_merge([$tenantId], $keys)
        ) as $r) {
            $out[(string) $r['mission_key']][] = (int) $r['user_id'];
        }

        return $out;
    }

    /** @return array<string, array<string, mixed>> clé → réglages (atak_debrief_ops) */
    public function meta(int $tenantId): array
    {
        if (!$this->ready()) {
            return [];
        }
        $out = [];
        foreach ($this->rows('SELECT * FROM atak_debrief_ops WHERE tenant_id = ?', [$tenantId]) as $r) {
            $out[(string) $r['mission_key']] = $r;
        }

        return $out;
    }

    /**
     * Met à jour les réglages d'une partie (champs absents inchangés).
     *
     * @param array<string, mixed> $fields label, type_id, category, ignored_at, deleted_at, count_anyway, plan_id, cycle_id, event_id, link_mode
     */
    public function saveMeta(int $tenantId, string $missionKey, array $fields, ?int $actorId): bool
    {
        $missionKey = AtakMissionKey::normalize($missionKey);
        $allowed = ['label', 'type_id', 'category', 'ignored_at', 'deleted_at', 'count_anyway', 'plan_id', 'cycle_id', 'event_id', 'link_mode'];
        $fields = array_intersect_key($fields, array_flip($allowed));
        if ($missionKey === '' || $fields === [] || !$this->ready()) {
            return false;
        }
        $cols = array_keys($fields);
        $now = static fn (mixed $v): mixed => $v === 'NOW' ? date('Y-m-d H:i:s') : $v;
        $vals = array_map($now, array_values($fields));
        $sql = 'INSERT INTO atak_debrief_ops (tenant_id, mission_key, updated_by_user_id, ' . implode(', ', array_map(static fn (string $c): string => '`' . $c . '`', $cols)) . ')
                VALUES (?, ?, ?, ' . implode(', ', array_fill(0, count($cols), '?')) . ')
                ON DUPLICATE KEY UPDATE updated_by_user_id = VALUES(updated_by_user_id), '
            . implode(', ', array_map(static fn (string $c): string => '`' . $c . '` = VALUES(`' . $c . '`)', $cols));

        return $this->exec($sql, array_merge([$tenantId, $missionKey, $actorId], $vals)) >= 0 && $this->lastOk;
    }

    // ── Types, règles d'exclusion, catégories ───────────────────────────────

    /** @return list<array<string, mixed>> */
    public function types(int $tenantId, bool $withArchived = false): array
    {
        if (!$this->ready()) {
            return [];
        }

        return $this->rows(
            'SELECT * FROM atak_op_types WHERE tenant_id = ?' . ($withArchived ? '' : ' AND archived_at IS NULL') . ' ORDER BY id',
            [$tenantId]
        );
    }

    /** @return array<string, mixed>|null */
    public function findType(int $tenantId, int $id): ?array
    {
        return $id > 0 && $this->ready()
            ? ($this->rows('SELECT * FROM atak_op_types WHERE tenant_id = ? AND id = ? LIMIT 1', [$tenantId, $id])[0] ?? null)
            : null;
    }

    /** @param array{name: string, color?: string, icon?: string, match_field?: string, pattern?: string, category?: ?string} $d */
    public function createType(int $tenantId, array $d, ?int $actorId): int
    {
        $name = mb_substr(trim((string) $d['name']), 0, 80);
        if ($name === '' || !$this->ready()) {
            return 0;
        }
        $category = mb_substr(trim((string) ($d['category'] ?? '')), 0, 80);
        $this->exec(
            'INSERT INTO atak_op_types (tenant_id, name, color, icon, match_field, pattern, category, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $tenantId, $name,
                AtakPlayedOpRules::normalizeColor((string) ($d['color'] ?? '')),
                AtakPlayedOpRules::normalizeIcon((string) ($d['icon'] ?? '')),
                AtakPlayedOpRules::normalizeField((string) ($d['match_field'] ?? 'mission')),
                AtakPlayedOpRules::normalizePattern((string) ($d['pattern'] ?? '')),
                $category !== '' ? $category : null,
                $actorId,
            ]
        );

        return $this->lastOk ? (int) $this->pdo()->lastInsertId() : 0;
    }

    public function setTypeArchived(int $tenantId, int $id, bool $archived): bool
    {
        return $this->ready() && $this->exec(
            'UPDATE atak_op_types SET archived_at = ' . ($archived ? 'NOW()' : 'NULL') . ' WHERE tenant_id = ? AND id = ?',
            [$tenantId, $id]
        ) > 0;
    }

    /** @return list<array<string, mixed>> */
    public function rules(int $tenantId, bool $withDisabled = false): array
    {
        if (!$this->ready()) {
            return [];
        }

        return $this->rows(
            'SELECT * FROM atak_op_ignore_rules WHERE tenant_id = ?' . ($withDisabled ? '' : ' AND disabled_at IS NULL') . ' ORDER BY id',
            [$tenantId]
        );
    }

    public function createRule(int $tenantId, string $field, string $pattern, string $note, ?int $actorId): int
    {
        $pattern = AtakPlayedOpRules::normalizePattern($pattern);
        if ($pattern === '' || !$this->ready()) {
            return 0;
        }
        $field = AtakPlayedOpRules::normalizeField($field, true);
        foreach ($this->rules($tenantId) as $r) {
            if ((string) $r['match_field'] === $field && mb_strtolower((string) $r['pattern']) === mb_strtolower($pattern)) {
                return (int) $r['id'];
            }
        }
        $note = mb_substr(trim($note), 0, 200);
        $this->exec(
            'INSERT INTO atak_op_ignore_rules (tenant_id, match_field, pattern, like_pattern, note, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?)',
            [$tenantId, $field, $pattern, AtakPlayedOpRules::toLike($pattern), $note !== '' ? $note : null, $actorId]
        );

        return $this->lastOk ? (int) $this->pdo()->lastInsertId() : 0;
    }

    public function setRuleDisabled(int $tenantId, int $id, bool $disabled): bool
    {
        return $this->ready() && $this->exec(
            'UPDATE atak_op_ignore_rules SET disabled_at = ' . ($disabled ? 'NOW()' : 'NULL') . ' WHERE tenant_id = ? AND id = ?',
            [$tenantId, $id]
        ) > 0;
    }

    /** @return list<string> catégories de la communauté, sinon celles proposées par défaut */
    public function categories(int $tenantId): array
    {
        $rows = $this->ready() ? $this->rows('SELECT name FROM atak_op_categories WHERE tenant_id = ? ORDER BY position, id', [$tenantId]) : [];

        return $rows !== [] ? array_map(static fn (array $r): string => (string) $r['name'], $rows) : AtakPlayedOpRules::DEFAULT_CATEGORIES;
    }

    /** @param list<string> $names */
    public function saveCategories(int $tenantId, array $names): bool
    {
        if (!$this->ready()) {
            return false;
        }
        $this->exec('DELETE FROM atak_op_categories WHERE tenant_id = ?', [$tenantId]);
        foreach (array_values($names) as $i => $n) {
            $this->exec('INSERT INTO atak_op_categories (tenant_id, name, position) VALUES (?, ?, ?)', [$tenantId, $n, $i]);
        }

        return true;
    }

    // ── Ce qui était prévu (rapprochement) ──────────────────────────────────

    /**
     * Opérations planifiées, cycles de mission et événements autour de la période, au format du rapprochement
     * (AtakOpReconciler) : kind, id, title, start_ts, end_ts, map, users, status, plus les liens utiles.
     *
     * @return list<array<string, mixed>>
     */
    public function candidates(int $tenantId, int $days = 100): array
    {
        $since = date('Y-m-d H:i:s', time() - max(1, $days) * 86400);
        $until = date('Y-m-d H:i:s', time() + 30 * 86400);
        $out = [];
        $plans = $this->rows(
            "SELECT p.id, p.title, p.status, p.event_id, p.cycle_id, p.published_at, p.closed_at,
                    COALESCE(p.h_hour_at, e.starts_at) AS starts_at, e.ends_at, e.title AS event_title, e.campaign_tag, m.world_name
             FROM mission_plans p
             LEFT JOIN community_events e ON e.id = p.event_id AND e.tenant_id = p.tenant_id
             LEFT JOIN atak_maps m ON m.id = p.map_id
             WHERE p.tenant_id = ? AND COALESCE(p.h_hour_at, e.starts_at, p.updated_at) BETWEEN ? AND ?
             ORDER BY starts_at DESC LIMIT 120",
            [$tenantId, $since, $until]
        );
        $planUsers = [];
        if ($plans !== []) {
            $ids = array_map(static fn (array $p): int => (int) $p['id'], $plans);
            $in = implode(',', array_fill(0, count($ids), '?'));
            foreach ($this->rows(
                "SELECT a.plan_id, COALESCE(a.current_user_id, a.planned_user_id) AS uid FROM mission_to_assignments a
                 JOIN mission_plans p ON p.id = a.plan_id AND p.tenant_id = ?
                 WHERE a.plan_id IN ({$in}) AND COALESCE(a.current_user_id, a.planned_user_id) IS NOT NULL",
                array_merge([$tenantId], $ids)
            ) as $r) {
                $planUsers[(int) $r['plan_id']][] = (int) $r['uid'];
            }
        }
        $planEvents = [];
        $planCycles = [];
        foreach ($plans as $p) {
            $id = (int) $p['id'];
            $planEvents[(int) ($p['event_id'] ?? 0)] = $id;
            $planCycles[(int) ($p['cycle_id'] ?? 0)] = $id;
            $out[] = [
                'kind' => 'plan', 'id' => $id, 'title' => (string) $p['title'], 'status' => (string) $p['status'],
                'start_ts' => self::localTs($p['starts_at'] ?? null), 'end_ts' => self::localTs($p['ends_at'] ?? null),
                'map' => (string) ($p['world_name'] ?? ''), 'users' => $planUsers[$id] ?? [],
                'event_id' => (int) ($p['event_id'] ?? 0), 'cycle_id' => (int) ($p['cycle_id'] ?? 0),
                'event_title' => (string) ($p['event_title'] ?? ''), 'campaign' => (string) ($p['campaign_tag'] ?? ''),
                'published' => !empty($p['published_at']) || in_array((string) $p['status'], ['published', 'live', 'closed'], true),
            ];
        }
        foreach ($this->rows(
            "SELECT c.id, c.title, c.status, c.started_at, c.ended_at, c.created_at, m.world_name
             FROM theatre_mission_cycles c LEFT JOIN atak_maps m ON m.id = c.map_id
             WHERE c.tenant_id = ? AND COALESCE(c.started_at, c.created_at) BETWEEN ? AND ? ORDER BY c.id DESC LIMIT 80",
            [$tenantId, $since, $until]
        ) as $c) {
            $id = (int) $c['id'];
            $out[] = [
                'kind' => 'cycle', 'id' => $id, 'title' => (string) $c['title'], 'status' => (string) $c['status'],
                'start_ts' => self::localTs($c['started_at'] ?? null), 'end_ts' => self::localTs($c['ended_at'] ?? null),
                'map' => (string) ($c['world_name'] ?? ''), 'users' => [], 'plan_id' => $planCycles[$id] ?? 0,
            ];
        }
        $events = $this->rows(
            'SELECT id, title, starts_at, ends_at, campaign_tag, event_type FROM community_events
             WHERE tenant_id = ? AND cancelled_at IS NULL AND starts_at BETWEEN ? AND ? ORDER BY starts_at DESC LIMIT 120',
            [$tenantId, $since, $until]
        );
        $rsvp = [];
        if ($events !== []) {
            $ids = array_map(static fn (array $e): int => (int) $e['id'], $events);
            $in = implode(',', array_fill(0, count($ids), '?'));
            foreach ($this->rows("SELECT event_id, user_id FROM community_event_rsvps WHERE status = 'yes' AND event_id IN ({$in})", $ids) as $r) {
                $rsvp[(int) $r['event_id']][] = (int) $r['user_id'];
            }
        }
        foreach ($events as $e) {
            $id = (int) $e['id'];
            if (isset($planEvents[$id])) {
                continue; // l'opération planifiée de cet événement le représente déjà
            }
            $out[] = [
                'kind' => 'event', 'id' => $id, 'title' => (string) $e['title'], 'status' => (string) ($e['event_type'] ?? ''),
                'start_ts' => self::localTs($e['starts_at'] ?? null), 'end_ts' => self::localTs($e['ends_at'] ?? null),
                'map' => '', 'users' => $rsvp[$id] ?? [], 'campaign' => (string) ($e['campaign_tag'] ?? ''),
            ];
        }

        return $out;
    }

    /** La cible d'un rattachement (plan, cycle, event) appartient-elle à la communauté ? */
    public function ownsTarget(int $tenantId, string $kind, int $id): bool
    {
        $table = ['plan' => 'mission_plans', 'cycle' => 'theatre_mission_cycles', 'event' => 'community_events'][$kind] ?? null;

        return $table !== null && $id > 0
            && $this->rows('SELECT 1 AS ok FROM ' . $table . ' WHERE tenant_id = ? AND id = ? LIMIT 1', [$tenantId, $id]) !== [];
    }

    // ── Suivi : CR, comptes rendus, effets ──────────────────────────────────

    /** @return array<string, int> clé → nombre de CR imposés */
    public function debriefCounts(int $tenantId, array $keys): array
    {
        $keys = array_values(array_filter($keys, 'is_string'));
        if ($keys === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($keys), '?'));
        $out = [];
        foreach ($this->rows("SELECT mission_key, COUNT(*) AS n FROM atak_debriefs WHERE tenant_id = ? AND mission_key IN ({$in}) GROUP BY mission_key", array_merge([$tenantId], $keys)) as $r) {
            $out[(string) $r['mission_key']] = (int) $r['n'];
        }

        return $out;
    }

    /** @return array<string, array{n: int, s: int}> clé → vols du carnet (nombre, secondes) */
    public function flightCounts(int $tenantId, array $keys): array
    {
        $keys = array_values(array_filter($keys, 'is_string'));
        if ($keys === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($keys), '?'));
        $out = [];
        foreach ($this->rows("SELECT mission_key, COUNT(*) AS n, COALESCE(SUM(seconds), 0) AS s FROM pilot_logbook_entries WHERE tenant_id = ? AND mission_key IN ({$in}) GROUP BY mission_key", array_merge([$tenantId], $keys)) as $r) {
            $out[(string) $r['mission_key']] = ['n' => (int) $r['n'], 's' => (int) $r['s']];
        }

        return $out;
    }

    /** @return list<array{id: int, title: string, status: string, plan_id: int, cycle_id: int}> comptes rendus post-op liés à un plan ou un cycle */
    public function aarReports(int $tenantId): array
    {
        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'], 'title' => (string) $r['title'], 'status' => (string) $r['status'],
            'plan_id' => (int) ($r['mission_plan_id'] ?? 0), 'cycle_id' => (int) ($r['mission_cycle_id'] ?? 0),
        ], $this->rows(
            'SELECT id, title, status, mission_plan_id, mission_cycle_id FROM aar_reports
             WHERE tenant_id = ? AND (mission_plan_id IS NOT NULL OR mission_cycle_id IS NOT NULL) ORDER BY id DESC LIMIT 300',
            [$tenantId]
        ));
    }

    /**
     * Ce que la suppression ou l'exclusion d'une partie retire des comptes (affiché avant de confirmer).
     *
     * @return array{players: int, play_seconds: int, reports: int, debriefs: int, flights: int, flight_seconds: int}
     */
    public function impact(int $tenantId, string $missionKey): array
    {
        $play = $this->rows(
            "SELECT COUNT(DISTINCT user_id) AS players, COALESCE(SUM(CASE WHEN kind = 'play' AND item_key = 'TOTAL' THEN seconds ELSE 0 END), 0) AS s
             FROM atak_screen_time WHERE tenant_id = ? AND mission_key = ?",
            [$tenantId, $missionKey]
        )[0] ?? [];
        $flights = $this->rows(
            'SELECT COUNT(*) AS n, COALESCE(SUM(seconds), 0) AS s FROM pilot_logbook_entries WHERE tenant_id = ? AND mission_key = ?',
            [$tenantId, $missionKey]
        )[0] ?? [];

        return [
            'players' => (int) ($play['players'] ?? 0),
            'play_seconds' => (int) ($play['s'] ?? 0),
            'reports' => (int) ($this->rows('SELECT COUNT(*) AS n FROM atak_tactical_reports WHERE tenant_id = ? AND mission_key = ?', [$tenantId, $missionKey])[0]['n'] ?? 0),
            'debriefs' => $this->debriefCounts($tenantId, [$missionKey])[$missionKey] ?? 0,
            'flights' => (int) ($flights['n'] ?? 0),
            'flight_seconds' => (int) ($flights['s'] ?? 0),
        ];
    }

    /** Date locale « Y-m-d H:i:s » (NOW() de MySQL, champs saisis sur le site) → horodatage. */
    private static function localTs(mixed $raw): ?int
    {
        $s = trim((string) ($raw ?? ''));
        if ($s === '') {
            return null;
        }
        $ts = strtotime($s);

        return $ts === false ? null : $ts;
    }

    private bool $lastOk = true;

    /** @param list<mixed> $args @return list<array<string, mixed>> */
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
    private function exec(string $sql, array $args = []): int
    {
        try {
            $st = $this->pdo()->prepare($sql);
            $st->execute($args);
            $this->lastOk = true;

            return $st->rowCount();
        } catch (\Throwable) {
            $this->lastOk = false;

            return 0;
        }
    }
}
