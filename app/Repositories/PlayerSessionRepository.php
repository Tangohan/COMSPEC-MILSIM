<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\Personnel\PlayerSessionService;
use App\Support\LazyDatabaseConnection;
use App\Support\SteamId;
use PDO;

/**
 * Sessions de jeu d'un joueur sur le serveur de sa communauté (table player_sessions, étape 4710).
 *
 * SQL portable (aucune fonction de date MySQL : les bornes sont calculées ici), pour tourner aussi sous SQLite en test.
 * Tout est limité à la communauté (tenant_id). Le rattachement Steam ↔ membre est relu à chaque lecture depuis
 * users.steam_id : un Steam relié après coup rattrape ses anciennes sessions. Les lectures se font par lots de
 * membres (une requête pour les comptes, une pour les sessions) : jamais une requête par membre.
 */
class PlayerSessionRepository
{
    use LazyDatabaseConnection;

    private ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /** La table existe (sinon : mode dégradé, le calcul d'avant s'applique). */
    public function schemaReady(): bool
    {
        if ($this->ready === null) {
            try {
                $this->pdo()->query('SELECT 1 FROM player_sessions WHERE 1 = 0');
                $this->ready = true;
            } catch (\Throwable) {
                $this->ready = false;
            }
        }

        return $this->ready;
    }

    /**
     * Enregistre un signe de vie : prolonge la session en cours si le précédent est plus récent que la coupure tolérée,
     * sinon en ouvre une nouvelle (et ferme la précédente à son dernier signe de vie). Idempotent pour un même instant.
     *
     * @return array{action: string, session_id: int}|null null si le Steam est invalide ou la table absente
     */
    public function recordHeartbeat(int $tenantId, string $steamUid, int $at, int $gapSeconds, ?int $userId = null, ?int $serverId = null, string $source = 'heartbeat'): ?array
    {
        $steam = SteamId::normalize($steamUid);
        if ($steam === null || $tenantId < 1 || $at < 1 || !$this->schemaReady()) {
            return null;
        }
        $gapSeconds = max(60, $gapSeconds);
        $pdo = $this->pdo();
        // Session qui précède ou contient l'instant, et première session qui le suit (signes de vie reçus en retard).
        $st = $pdo->prepare('SELECT * FROM player_sessions WHERE tenant_id = ? AND steam_uid = ? AND started_at <= ? ORDER BY started_at DESC LIMIT 1');
        $st->execute([$tenantId, $steam, self::fmt($at)]);
        $prev = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        $st = $pdo->prepare('SELECT * FROM player_sessions WHERE tenant_id = ? AND steam_uid = ? AND started_at > ? ORDER BY started_at ASC LIMIT 1');
        $st->execute([$tenantId, $steam, self::fmt($at)]);
        $next = $st->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($prev !== null && $at - self::ts((string) $prev['last_seen_at']) <= $gapSeconds) {
            $start = self::ts((string) $prev['started_at']);
            $seen = max(self::ts((string) $prev['last_seen_at']), $at);
            $id = (int) $prev['id'];
            $action = $seen === self::ts((string) $prev['last_seen_at']) ? 'ignored' : 'extended';
            if ($next !== null && self::ts((string) $next['started_at']) - $seen <= $gapSeconds) {
                // Le retard comble le trou entre deux sessions : elles n'en font plus qu'une.
                $seen = max($seen, self::ts((string) $next['last_seen_at']));
                $pdo->prepare('DELETE FROM player_sessions WHERE id = ? AND tenant_id = ?')->execute([(int) $next['id'], $tenantId]);
                $action = 'merged';
            }
            $raw = max(0, $seen - $start);
            $pdo->prepare('UPDATE player_sessions SET last_seen_at = ?, ended_at = NULL, duration_s = ?, aberrant = ?, user_id = COALESCE(user_id, ?), server_id = COALESCE(server_id, ?) WHERE id = ? AND tenant_id = ?')
                ->execute([self::fmt($seen), min($raw, PlayerSessionService::MAX_SESSION_SECONDS), $raw > PlayerSessionService::MAX_SESSION_SECONDS ? 1 : 0, $userId, $serverId, $id, $tenantId]);

            return ['action' => $action, 'session_id' => $id];
        }
        if ($next !== null && self::ts((string) $next['started_at']) - $at <= $gapSeconds) {
            // Signe de vie plus ancien que la session suivante, à moins de la coupure tolérée : elle commence plus tôt.
            $seen = self::ts((string) $next['last_seen_at']);
            $raw = max(0, $seen - $at);
            $pdo->prepare('UPDATE player_sessions SET started_at = ?, duration_s = ?, aberrant = ?, user_id = COALESCE(user_id, ?) WHERE id = ? AND tenant_id = ?')
                ->execute([self::fmt($at), min($raw, PlayerSessionService::MAX_SESSION_SECONDS), $raw > PlayerSessionService::MAX_SESSION_SECONDS ? 1 : 0, $userId, (int) $next['id'], $tenantId]);

            return ['action' => 'extended', 'session_id' => (int) $next['id']];
        }
        if ($prev !== null && trim((string) ($prev['ended_at'] ?? '')) === '') {
            $pdo->prepare('UPDATE player_sessions SET ended_at = last_seen_at WHERE id = ? AND tenant_id = ?')->execute([(int) $prev['id'], $tenantId]);
        }
        $id = $this->insertSession($tenantId, $steam, $userId, $at, $at, $next !== null ? $at : null, 0, false, $source, $serverId);

        return ['action' => 'started', 'session_id' => $id];
    }

    /**
     * Écrit une session reconstituée (rattrapage) : insère ou met à jour la ligne qui a le même début. Rejouable.
     *
     * @return string inserted|updated|unchanged
     */
    public function upsertSession(int $tenantId, string $steamUid, ?int $userId, int $startedAt, int $lastSeenAt, ?int $endedAt, int $durationS, bool $aberrant, string $source, ?int $serverId, bool $dryRun = false): string
    {
        $steam = SteamId::normalize($steamUid);
        if ($steam === null || $tenantId < 1) {
            return 'unchanged';
        }
        $pdo = $this->pdo();
        $st = $pdo->prepare('SELECT id, last_seen_at, ended_at, duration_s FROM player_sessions WHERE tenant_id = ? AND steam_uid = ? AND started_at = ? LIMIT 1');
        $st->execute([$tenantId, $steam, self::fmt($startedAt)]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row === null) {
            if ($dryRun) {
                return 'inserted';
            }
            $this->insertSession($tenantId, $steam, $userId, $startedAt, $lastSeenAt, $endedAt, $durationS, $aberrant, $source, $serverId);

            return 'inserted';
        }
        if (self::ts((string) $row['last_seen_at']) >= $lastSeenAt && (int) $row['duration_s'] >= $durationS) {
            return 'unchanged';
        }
        if ($dryRun) {
            return 'updated';
        }
        $up = $pdo->prepare('UPDATE player_sessions SET last_seen_at = ?, ended_at = ?, duration_s = ?, aberrant = ?, user_id = COALESCE(user_id, ?) WHERE id = ? AND tenant_id = ?');
        $up->execute([self::fmt($lastSeenAt), $endedAt !== null ? self::fmt($endedAt) : null, $durationS, $aberrant ? 1 : 0, $userId, (int) $row['id'], $tenantId]);

        return 'updated';
    }

    private function insertSession(int $tenantId, string $steam, ?int $userId, int $start, int $lastSeen, ?int $end, int $duration, bool $aberrant, string $source, ?int $serverId): int
    {
        $pdo = $this->pdo();
        $ins = $pdo->prepare('INSERT INTO player_sessions (tenant_id, user_id, steam_uid, started_at, ended_at, last_seen_at, duration_s, aberrant, source, server_id, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        try {
            $ins->execute([$tenantId, $userId, $steam, self::fmt($start), $end !== null ? self::fmt($end) : null, self::fmt($lastSeen), max(0, $duration), $aberrant ? 1 : 0, mb_substr($source, 0, 16), $serverId, self::fmt(time())]);
        } catch (\PDOException) {
            // Deux signes de vie simultanés : l'autre requête a ouvert la session, on retrouve sa ligne.
            $f = $pdo->prepare('SELECT id FROM player_sessions WHERE tenant_id = ? AND steam_uid = ? AND started_at = ? LIMIT 1');
            $f->execute([$tenantId, $steam, self::fmt($start)]);

            return (int) $f->fetchColumn();
        }

        return (int) $pdo->lastInsertId();
    }

    /**
     * Sessions des membres sur une période, par membre (rattachement par Steam), sans requête par membre.
     *
     * @param list<int> $userIds
     * @return array<int, list<array<string, mixed>>> sessions triées par début, clés = id du membre ; membre sans Steam : tableau vide
     */
    public function sessionsForUsers(int $tenantId, array $userIds, int $sinceTs, int $now): array
    {
        $out = [];
        foreach ($userIds as $id) {
            if ((int) $id > 0) {
                $out[(int) $id] = [];
            }
        }
        if ($out === [] || $tenantId < 1 || !$this->schemaReady()) {
            return $out;
        }
        $steamByUser = $this->steamByUser($tenantId, array_keys($out));
        $userBySteam = [];
        foreach ($steamByUser as $uid => $steam) {
            $userBySteam[$steam][] = $uid;
        }
        foreach (array_chunk(array_keys($userBySteam), 400) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $st = $this->pdo()->prepare(
                "SELECT steam_uid, started_at, last_seen_at, ended_at, duration_s, aberrant, source, server_id
                 FROM player_sessions
                 WHERE tenant_id = ? AND steam_uid IN ({$in}) AND last_seen_at >= ?
                 ORDER BY started_at ASC"
            );
            $st->execute(array_merge([$tenantId], $chunk, [self::fmt($sinceTs)]));
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $session = self::rowToSession($r);
                foreach ($userBySteam[(string) $r['steam_uid']] ?? [] as $uid) {
                    $out[$uid][] = $session;
                }
            }
        }

        return $out;
    }

    /**
     * Agrégats par membre (7/30/90 jours, durée cumulée, dernière session qualifiante, série, fiabilité).
     *
     * @param list<int> $userIds
     * @return array<int, array<string, mixed>> PlayerSessionService::summarize, une entrée par membre
     */
    public function summariesForUsers(int $tenantId, array $userIds, int $minSeconds, int $gapSeconds, int $now, string $timezone = 'Europe/Paris'): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $v): bool => $v > 0)));
        if ($ids === []) {
            return [];
        }
        $steamByUser = $this->steamByUser($tenantId, $ids);
        $sessions = $this->sessionsForUsers($tenantId, $ids, $now - 90 * 86400, $now);
        $out = [];
        foreach ($ids as $uid) {
            $list = self::markOngoing($sessions[$uid] ?? [], $gapSeconds, $now);
            $out[$uid] = PlayerSessionService::summarize($list, $minSeconds, $now, $timezone, isset($steamByUser[$uid]));
        }

        return $out;
    }

    /**
     * Sessions qui comptent par membre depuis $sinceTs (page « Métriques joueurs » : période choisie), en une requête.
     *
     * @param list<int> $userIds
     * @return array<int, array{sessions: int, seconds: int}> une entrée par membre relié à un Steam ; absent sinon
     */
    public function qualifyingCountsForUsers(int $tenantId, array $userIds, int $sinceTs, int $minSeconds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $v): bool => $v > 0)));
        if ($ids === [] || !$this->schemaReady()) {
            return [];
        }
        $linked = $this->steamByUser($tenantId, $ids);
        $out = [];
        foreach ($linked as $uid => $_steam) {
            $out[$uid] = ['sessions' => 0, 'seconds' => 0];
        }
        foreach ($this->sessionsForUsers($tenantId, array_keys($linked), $sinceTs, time()) as $uid => $list) {
            foreach ($list as $s) {
                if (PlayerSessionService::qualifies($s, $minSeconds)) {
                    $out[$uid]['sessions']++;
                    $out[$uid]['seconds'] += (int) $s['duration_s'];
                }
            }
        }

        return $out;
    }

    /**
     * Onglet « Disponibilité » de la fiche d'un membre : agrégats et dernières sessions (qualifiantes ou non).
     *
     * @return array{ready: bool, summary: array<string, mixed>, recent: list<array<string, mixed>>, min_minutes: int, gap_minutes: int, tz: string, now: int}
     */
    public function panelForUser(int $tenantId, int $userId, int $minSeconds, int $gapSeconds, int $now, string $timezone = 'Europe/Paris', int $limit = 12): array
    {
        $base = ['ready' => false, 'min_minutes' => intdiv($minSeconds, 60), 'gap_minutes' => intdiv($gapSeconds, 60), 'tz' => $timezone, 'now' => $now];
        if (!$this->schemaReady()) {
            return $base + ['summary' => PlayerSessionService::summarize([], $minSeconds, $now, $timezone, false), 'recent' => []];
        }
        $linked = isset($this->steamByUser($tenantId, [$userId])[$userId]);
        $list = self::markOngoing($this->sessionsForUsers($tenantId, [$userId], $now - 90 * 86400, $now)[$userId] ?? [], $gapSeconds, $now);
        $recent = [];
        foreach (array_slice(array_reverse($list), 0, max(1, $limit)) as $s) {
            $recent[] = $s + ['qualifies' => PlayerSessionService::qualifies($s, $minSeconds)];
        }

        return ['ready' => true, 'summary' => PlayerSessionService::summarize($list, $minSeconds, $now, $timezone, $linked), 'recent' => $recent] + $base;
    }

    /**
     * Fragment SQL « le membre a une session qui compte depuis $sinceTs » pour les requêtes d'inactivité.
     * Alias attendu : la table users (colonnes tenant_id, steam_id).
     *
     * @return array{0: string, 1: list<int|string>}
     */
    public static function qualifyingExistsClause(string $userAlias, int $sinceTs, int $minSeconds): array
    {
        $a = preg_replace('/[^A-Za-z0-9_]/', '', $userAlias) ?: 'u';

        return [
            "EXISTS (SELECT 1 FROM player_sessions ps WHERE ps.tenant_id = {$a}.tenant_id AND ps.steam_uid = {$a}.steam_id AND ps.last_seen_at >= ? AND ps.duration_s >= ?)",
            [self::fmt($sinceTs), max(60, $minSeconds)],
        ];
    }

    /**
     * Membres de la communauté qui ont une session qui compte depuis $sinceTs (liste d'effectifs : filtre « actif »).
     *
     * @param list<int> $userIds
     * @return array<int, true>
     */
    public function qualifyingUserIds(int $tenantId, array $userIds, int $sinceTs, int $minSeconds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $v): bool => $v > 0)));
        if ($ids === [] || !$this->schemaReady()) {
            return [];
        }
        $steamByUser = $this->steamByUser($tenantId, $ids);
        $userBySteam = [];
        foreach ($steamByUser as $uid => $steam) {
            $userBySteam[$steam][] = $uid;
        }
        $out = [];
        foreach (array_chunk(array_keys($userBySteam), 400) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $st = $this->pdo()->prepare("SELECT DISTINCT steam_uid FROM player_sessions WHERE tenant_id = ? AND steam_uid IN ({$in}) AND last_seen_at >= ? AND duration_s >= ?");
            $st->execute(array_merge([$tenantId], $chunk, [self::fmt($sinceTs), max(60, $minSeconds)]));
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $steam) {
                foreach ($userBySteam[(string) $steam] ?? [] as $uid) {
                    $out[$uid] = true;
                }
            }
        }

        return $out;
    }

    /**
     * Widget « Disponibilité à vérifier » de l'accueil du back-office : membres actifs sans session qui compte depuis
     * $sinceTs (ou sans Steam relié) et sans vérification de disponibilité enregistrée depuis. Deux requêtes (total,
     * premiers membres) quel que soit l'effectif. Sans la table des sessions : null (suivi indisponible).
     *
     * @return array{total: int, rows: list<array{id: int, name: string, linked: bool, last_at: ?int}>}|null
     */
    public function membersToVerify(int $tenantId, int $sinceTs, int $minSeconds, int $limit = 5): ?array
    {
        if ($tenantId < 1 || !$this->schemaReady()) {
            return null;
        }
        $since = self::fmt($sinceTs);
        $min = max(60, $minSeconds);
        $where = "u.tenant_id = ? AND u.status = 'active' AND (u.display_name IS NULL OR u.display_name <> 'Compte supprimé')
            AND NOT EXISTS (SELECT 1 FROM player_sessions ps WHERE ps.tenant_id = u.tenant_id AND ps.steam_uid = u.steam_id AND ps.last_seen_at >= ? AND ps.duration_s >= ?)";
        $params = [$tenantId, $since, $min];
        if ($this->tableReady('member_availability_checks')) {
            $where .= ' AND NOT EXISTS (SELECT 1 FROM member_availability_checks c WHERE c.tenant_id = u.tenant_id AND c.user_id = u.id AND c.created_at >= ?)';
            $params[] = $since;
        }
        try {
            $st = $this->pdo()->prepare("SELECT COUNT(*) FROM users u WHERE {$where}");
            $st->execute($params);
            $total = (int) $st->fetchColumn();
            $rows = [];
            if ($total > 0) {
                $st = $this->pdo()->prepare(
                    "SELECT t.* FROM (
                        SELECT u.id, u.display_name, u.steam_id,
                               (SELECT MAX(ps2.last_seen_at) FROM player_sessions ps2 WHERE ps2.tenant_id = u.tenant_id AND ps2.steam_uid = u.steam_id AND ps2.duration_s >= ?) AS last_at
                        FROM users u WHERE {$where}
                     ) t ORDER BY (t.last_at IS NULL) DESC, t.last_at ASC, t.id ASC LIMIT " . max(1, min(20, $limit))
                );
                $st->execute(array_merge([$min], $params));
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                    $rows[] = [
                        'id' => (int) $r['id'],
                        'name' => trim((string) ($r['display_name'] ?? '')),
                        'linked' => SteamId::normalize((string) ($r['steam_id'] ?? '')) !== null,
                        'last_at' => trim((string) ($r['last_at'] ?? '')) !== '' ? self::ts((string) $r['last_at']) : null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            error_log('[player-sessions] à vérifier : ' . $e->getMessage());

            return null;
        }

        return ['total' => $total, 'rows' => $rows];
    }

    /**
     * Widget « Sessions de jeu récentes » : dernières sessions de la communauté, avec le membre rattaché par Steam.
     * Une requête. Sans la table des sessions : null.
     *
     * @return list<array{user_id: int, name: string, started_at: int, last_seen_at: int, duration_s: int, aberrant: bool}>|null
     */
    public function recentForTenant(int $tenantId, int $sinceTs, int $limit = 8): ?array
    {
        if ($tenantId < 1 || !$this->schemaReady()) {
            return null;
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT ps.started_at, ps.last_seen_at, ps.duration_s, ps.aberrant, u.id AS user_id, u.display_name
                 FROM player_sessions ps
                 LEFT JOIN users u ON u.tenant_id = ps.tenant_id AND u.steam_id = ps.steam_uid
                 WHERE ps.tenant_id = ? AND ps.last_seen_at >= ?
                 ORDER BY ps.last_seen_at DESC LIMIT ' . max(1, min(30, $limit))
            );
            $st->execute([$tenantId, self::fmt($sinceTs)]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $start = self::ts((string) $r['started_at']);
                $seen = self::ts((string) $r['last_seen_at']);
                $out[] = [
                    'user_id' => (int) ($r['user_id'] ?? 0),
                    'name' => trim((string) ($r['display_name'] ?? '')),
                    'started_at' => $start,
                    'last_seen_at' => $seen,
                    'duration_s' => (int) ($r['duration_s'] ?? max(0, $seen - $start)),
                    'aberrant' => (int) ($r['aberrant'] ?? 0) === 1,
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            error_log('[player-sessions] récentes : ' . $e->getMessage());

            return null;
        }
    }

    private function tableReady(string $table): bool
    {
        try {
            $this->pdo()->query('SELECT 1 FROM ' . $table . ' WHERE 1 = 0');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Fuseau de la communauté (colonne tenants.timezone), Europe/Paris par défaut. */
    public function tenantTimezone(int $tenantId): string
    {
        try {
            $st = $this->pdo()->prepare('SELECT timezone FROM tenants WHERE id = ? LIMIT 1');
            $st->execute([$tenantId]);
            $tz = trim((string) $st->fetchColumn());
            if ($tz !== '') {
                new \DateTimeZone($tz);

                return $tz;
            }
        } catch (\Throwable) {
        }

        return 'Europe/Paris';
    }

    /**
     * Événements d'une période pour le rattrapage : segments de présence des sessions de mission (existants) par Steam.
     * Chaque segment fermé donne une connexion à son début et une déconnexion à sa fin ; un segment encore ouvert ne
     * donne qu'un signe de vie à son début.
     *
     * @return array<string, array{user_id: ?int, events: list<array{at: int, type: string, server_id: null, source: string}>}> clé = Steam
     */
    public function historicalEvents(int $tenantId, ?int $sinceTs = null): array
    {
        $sql = "SELECT m.user_id, m.steam_uid AS m_steam, u.steam_id AS u_steam, g.started_at, g.ended_at
                FROM roleplay_game_session_segments g
                INNER JOIN roleplay_game_session_members m ON m.id = g.member_id AND m.tenant_id = g.tenant_id
                LEFT JOIN users u ON u.id = m.user_id AND u.tenant_id = m.tenant_id
                WHERE g.tenant_id = ? AND g.reason <> 'check_in' AND g.started_at IS NOT NULL";
        $params = [$tenantId];
        if ($sinceTs !== null) {
            $sql .= ' AND g.started_at >= ?';
            $params[] = self::fmt($sinceTs);
        }
        $sql .= ' ORDER BY g.started_at ASC';
        $out = [];
        try {
            $st = $this->pdo()->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
        foreach ($rows as $r) {
            $steam = SteamId::normalize((string) ($r['u_steam'] ?? '')) ?? SteamId::normalize((string) ($r['m_steam'] ?? ''));
            if ($steam === null) {
                continue;
            }
            $out[$steam]['user_id'] ??= (int) ($r['user_id'] ?? 0) > 0 ? (int) $r['user_id'] : null;
            $out[$steam]['events'] ??= [];
            $start = self::ts((string) $r['started_at']);
            $end = trim((string) ($r['ended_at'] ?? '')) !== '' ? self::ts((string) $r['ended_at']) : 0;
            if ($start < 1) {
                continue;
            }
            $out[$steam]['events'][] = ['at' => $start, 'type' => PlayerSessionService::EVENT_CONNECT, 'server_id' => null, 'source' => 'backfill'];
            if ($end > $start) {
                $out[$steam]['events'][] = ['at' => $end, 'type' => PlayerSessionService::EVENT_DISCONNECT, 'server_id' => null, 'source' => 'backfill'];
            }
        }

        return $out;
    }

    /** @return list<int> communautés qui ont des sessions de mission (rattrapage sans --tenant) */
    public function tenantsWithHistory(): array
    {
        try {
            $st = $this->pdo()->query('SELECT DISTINCT tenant_id FROM roleplay_game_session_segments ORDER BY tenant_id');

            return array_map('intval', $st ? ($st->fetchAll(PDO::FETCH_COLUMN) ?: []) : []);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param list<int> $userIds
     * @return array<int, string> membre => SteamID64
     */
    private function steamByUser(int $tenantId, array $userIds): array
    {
        $out = [];
        foreach (array_chunk($userIds, 500) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $st = $this->pdo()->prepare("SELECT id, steam_id FROM users WHERE tenant_id = ? AND id IN ({$in})");
            $st->execute(array_merge([$tenantId], $chunk));
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $steam = SteamId::normalize((string) ($r['steam_id'] ?? ''));
                if ($steam !== null) {
                    $out[(int) $r['id']] = $steam;
                }
            }
        }

        return $out;
    }

    /**
     * Une session dont le dernier signe de vie est assez récent et qui n'est pas terminée est « en cours ».
     *
     * @param list<array<string, mixed>> $sessions
     * @return list<array<string, mixed>>
     */
    private static function markOngoing(array $sessions, int $gapSeconds, int $now): array
    {
        foreach ($sessions as &$s) {
            $open = ($s['ended_at'] ?? null) === null && $now - (int) $s['last_seen_at'] <= $gapSeconds;
            $s['ongoing'] = $open;
            if (!$open && ($s['ended_at'] ?? null) === null) {
                $s['ended_at'] = (int) $s['last_seen_at'];
            }
        }
        unset($s);

        return $sessions;
    }

    /** @param array<string, mixed> $r */
    private static function rowToSession(array $r): array
    {
        $start = self::ts((string) $r['started_at']);
        $seen = self::ts((string) $r['last_seen_at']);

        return [
            'started_at' => $start,
            'last_seen_at' => $seen,
            'ended_at' => trim((string) ($r['ended_at'] ?? '')) !== '' ? self::ts((string) $r['ended_at']) : null,
            'duration_s' => (int) ($r['duration_s'] ?? max(0, $seen - $start)),
            'aberrant' => (int) ($r['aberrant'] ?? 0) === 1,
            'ongoing' => false,
            'source' => (string) ($r['source'] ?? 'heartbeat'),
            'server_id' => isset($r['server_id']) && $r['server_id'] !== null ? (int) $r['server_id'] : null,
        ];
    }

    private static function ts(string $v): int
    {
        $t = strtotime($v);

        return $t === false ? 0 : $t;
    }

    private static function fmt(int $ts): string
    {
        return date('Y-m-d H:i:s', $ts);
    }
}
