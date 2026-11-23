<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use App\Support\Migrations\SchemaInspector;
use PDO;
use PDOException;

/**
 * Codes de session, réglages de l'app Session et journal (étape 4960_roleplay_session_app).
 * SQL portable MySQL / SQLite : les dates sont fournies par l'appelant (horloge du service), jamais NOW().
 */
class RoleplaySessionAppRepository
{
    use LazyDatabaseConnection;

    public const CODE_STATUSES = ['active', 'revoked', 'replaced', 'expired'];

    private ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function schemaReady(): bool
    {
        if ($this->ready !== null) {
            return $this->ready;
        }
        try {
            $schema = new SchemaInspector($this->pdo());
            $this->ready = $schema->tableExists('roleplay_game_session_codes')
                && $schema->tableExists('roleplay_game_session_app')
                && $schema->tableExists('roleplay_game_session_journal');
        } catch (\Throwable) {
            $this->ready = false;
        }

        return $this->ready;
    }

    /* —— Codes —— */

    /** @return array<string, mixed>|null */
    public function activeCode(int $tenantId, int $sessionId): ?array
    {
        $st = $this->pdo()->prepare(
            "SELECT * FROM roleplay_game_session_codes WHERE tenant_id = ? AND session_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1"
        );
        $st->execute([$tenantId, $sessionId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null dernier code de la session, quel que soit son état */
    public function latestCode(int $tenantId, int $sessionId): ?array
    {
        $st = $this->pdo()->prepare('SELECT * FROM roleplay_game_session_codes WHERE tenant_id = ? AND session_id = ? ORDER BY id DESC LIMIT 1');
        $st->execute([$tenantId, $sessionId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findCode(int $tenantId, string $code): ?array
    {
        $st = $this->pdo()->prepare('SELECT * FROM roleplay_game_session_codes WHERE tenant_id = ? AND code = ? LIMIT 1');
        $st->execute([$tenantId, $code]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Codes actifs de plusieurs sessions (liste des sessions).
     *
     * @param list<int> $sessionIds
     * @return array<int, string> session_id => code
     */
    public function activeCodesFor(int $tenantId, array $sessionIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $sessionIds), static fn (int $v): bool => $v > 0)));
        if ($ids === []) {
            return [];
        }
        $st = $this->pdo()->prepare(
            "SELECT session_id, code FROM roleplay_game_session_codes
             WHERE tenant_id = ? AND status = 'active' AND session_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ')
             ORDER BY id ASC'
        );
        $st->execute(array_merge([$tenantId], $ids));
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[(int) $r['session_id']] = (string) $r['code'];
        }

        return $out;
    }

    /** Insère un code actif ; false si le code existe déjà dans la communauté (collision de tirage). */
    public function insertCode(int $tenantId, int $sessionId, string $code, ?int $by, string $now): bool
    {
        try {
            $this->pdo()->prepare(
                "INSERT INTO roleplay_game_session_codes (tenant_id, session_id, code, status, created_by, created_at, ended_at, ended_by)
                 VALUES (?, ?, ?, 'active', ?, ?, NULL, NULL)"
            )->execute([$tenantId, $sessionId, $code, $by !== null && $by > 0 ? $by : null, $now]);
        } catch (PDOException $e) {
            if (self::isDuplicate($e)) {
                return false;
            }
            throw $e;
        }

        return true;
    }

    /** Termine le code actif de la session (revoked, replaced ou expired) ; nombre de codes terminés. */
    public function endActiveCodes(int $tenantId, int $sessionId, string $status, ?int $by, string $now): int
    {
        if (!in_array($status, ['revoked', 'replaced', 'expired'], true)) {
            return 0;
        }
        $st = $this->pdo()->prepare(
            "UPDATE roleplay_game_session_codes SET status = ?, ended_at = ?, ended_by = ?
             WHERE tenant_id = ? AND session_id = ? AND status = 'active'"
        );
        $st->execute([$status, $now, $by !== null && $by > 0 ? $by : null, $tenantId, $sessionId]);

        return $st->rowCount();
    }

    /**
     * Codes actifs dont la session est close, annulée ou introuvable, ou ouverte depuis trop longtemps (tâche planifiée).
     *
     * @return list<array{tenant_id: int, session_id: int, status: string, ended_at: ?string, started_at: ?string, created_at: string}>
     */
    public function activeCodesWithSession(int $limit = 500): array
    {
        $st = $this->pdo()->prepare(
            "SELECT c.tenant_id, c.session_id, c.created_at, s.status, s.ended_at, s.started_at
             FROM roleplay_game_session_codes c
             LEFT JOIN roleplay_game_sessions s ON s.id = c.session_id AND s.tenant_id = c.tenant_id
             WHERE c.status = 'active'
             ORDER BY c.id ASC
             LIMIT " . max(1, min(2000, $limit))
        );
        $st->execute();
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[] = [
                'tenant_id' => (int) $r['tenant_id'],
                'session_id' => (int) $r['session_id'],
                'status' => (string) ($r['status'] ?? ''),
                'ended_at' => $r['ended_at'] !== null ? (string) $r['ended_at'] : null,
                'started_at' => $r['started_at'] !== null ? (string) $r['started_at'] : null,
                'created_at' => (string) $r['created_at'],
            ];
        }

        return $out;
    }

    /* —— Réglages de l'app par session —— */

    /** @return array{notify_enabled: bool, remind_15: bool, remind_5: bool, briefing: string, stored: bool} */
    public function settings(int $tenantId, int $sessionId): array
    {
        $st = $this->pdo()->prepare('SELECT * FROM roleplay_game_session_app WHERE tenant_id = ? AND session_id = ? LIMIT 1');
        $st->execute([$tenantId, $sessionId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return self::defaultSettings();
        }

        return [
            'notify_enabled' => (int) $row['notify_enabled'] === 1,
            'remind_15' => (int) $row['remind_15'] === 1,
            'remind_5' => (int) $row['remind_5'] === 1,
            'briefing' => (string) ($row['briefing'] ?? ''),
            'stored' => true,
        ];
    }

    /** @return array{notify_enabled: bool, remind_15: bool, remind_5: bool, briefing: string, stored: bool} */
    public static function defaultSettings(): array
    {
        return ['notify_enabled' => true, 'remind_15' => true, 'remind_5' => true, 'briefing' => '', 'stored' => false];
    }

    /** @param array{notify_enabled: bool, remind_15: bool, remind_5: bool, briefing: string} $s */
    public function saveSettings(int $tenantId, int $sessionId, array $s, ?int $by, string $now): void
    {
        $values = [
            $s['notify_enabled'] ? 1 : 0,
            $s['remind_15'] ? 1 : 0,
            $s['remind_5'] ? 1 : 0,
            $s['briefing'] !== '' ? mb_substr($s['briefing'], 0, 400) : null,
            $by !== null && $by > 0 ? $by : null,
            $now,
        ];
        $st = $this->pdo()->prepare(
            'UPDATE roleplay_game_session_app SET notify_enabled = ?, remind_15 = ?, remind_5 = ?, briefing = ?, updated_by = ?, updated_at = ?
             WHERE tenant_id = ? AND session_id = ?'
        );
        $st->execute(array_merge($values, [$tenantId, $sessionId]));
        if ($st->rowCount() > 0 || $this->settings($tenantId, $sessionId)['stored']) {
            return;
        }
        try {
            $this->pdo()->prepare(
                'INSERT INTO roleplay_game_session_app (tenant_id, session_id, notify_enabled, remind_15, remind_5, briefing, updated_by, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute(array_merge([$tenantId, $sessionId], $values));
        } catch (PDOException $e) {
            // Deux enregistrements simultanés : la ligne existe désormais, on la met à jour.
            if (!self::isDuplicate($e)) {
                throw $e;
            }
            $st->execute(array_merge($values, [$tenantId, $sessionId]));
        }
    }

    /* —— Journal —— */

    /**
     * Ajoute une ligne au journal. Avec $dedupeKey, une seule ligne par clé et par session : false si elle existait déjà
     * (rappel déjà envoyé, joueur déjà rattaché).
     */
    public function log(int $tenantId, int $sessionId, string $event, ?string $dedupeKey, ?int $actor, string $detail, string $now): bool
    {
        try {
            $this->pdo()->prepare(
                'INSERT INTO roleplay_game_session_journal (tenant_id, session_id, event, dedupe_key, actor_user_id, detail, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $tenantId,
                $sessionId,
                mb_substr($event, 0, 24),
                $dedupeKey !== null && $dedupeKey !== '' ? mb_substr($dedupeKey, 0, 64) : null,
                $actor !== null && $actor > 0 ? $actor : null,
                $detail !== '' ? mb_substr($detail, 0, 255) : null,
                $now,
            ]);
        } catch (PDOException $e) {
            if (self::isDuplicate($e)) {
                return false;
            }
            throw $e;
        }

        return true;
    }

    public function hasDedupe(int $tenantId, int $sessionId, string $dedupeKey): bool
    {
        $st = $this->pdo()->prepare('SELECT 1 FROM roleplay_game_session_journal WHERE tenant_id = ? AND session_id = ? AND dedupe_key = ? LIMIT 1');
        $st->execute([$tenantId, $sessionId, $dedupeKey]);

        return (bool) $st->fetchColumn();
    }

    /** @return list<array<string, mixed>> le plus récent d'abord */
    public function journal(int $tenantId, int $sessionId, int $limit = 30): array
    {
        $st = $this->pdo()->prepare(
            'SELECT j.id, j.event, j.actor_user_id, j.detail, j.created_at, u.display_name, u.callsign
             FROM roleplay_game_session_journal j
             LEFT JOIN users u ON u.id = j.actor_user_id AND u.tenant_id = j.tenant_id
             WHERE j.tenant_id = ? AND j.session_id = ?
             ORDER BY j.id DESC
             LIMIT ' . max(1, min(200, $limit))
        );
        $st->execute([$tenantId, $sessionId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Sessions ouvertes, pas encore démarrées, dont le début prévu tombe dans la fenêtre (rappels T-15 / T-5).
     *
     * @return list<array<string, mixed>>
     */
    public function sessionsStartingBetween(string $from, string $until, ?int $tenantId = null, int $limit = 200): array
    {
        $st = $this->pdo()->prepare(
            "SELECT s.* FROM roleplay_game_sessions s
             WHERE s.status = 'open' AND s.started_at IS NULL AND s.planned_starts_at IS NOT NULL
               AND s.planned_starts_at > ? AND s.planned_starts_at <= ?" . ($tenantId !== null ? ' AND s.tenant_id = ?' : '') . '
             ORDER BY s.planned_starts_at ASC
             LIMIT ' . max(1, min(1000, $limit))
        );
        $st->execute($tenantId !== null ? [$from, $until, $tenantId] : [$from, $until]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* —— Lecture pour l'app Session —— */

    /**
     * Session la plus pertinente du joueur : celle qu'il a rejointe (ouverte, ou terminée depuis $endedSince), la plus récente.
     *
     * @return array<string, mixed>|null session + colonnes du membre préfixées m_
     */
    public function memberSession(int $tenantId, int $userId, string $endedSince): ?array
    {
        $st = $this->pdo()->prepare(
            "SELECT s.*, m.id AS m_id, m.joined_at AS m_joined_at, m.checked_in_at AS m_checked_in_at, m.left_at AS m_left_at,
                    m.raw_seconds AS m_raw_seconds, m.validated_seconds AS m_validated_seconds, m.attendance_status AS m_attendance_status,
                    m.attendance_valid AS m_attendance_valid, m.rh_excluded AS m_rh_excluded
             FROM roleplay_game_session_members m
             INNER JOIN roleplay_game_sessions s ON s.id = m.session_id AND s.tenant_id = m.tenant_id
             WHERE m.tenant_id = ? AND m.user_id = ?
               AND (s.status IN ('open', 'discovered') OR (s.status IN ('closed', 'cancelled') AND s.ended_at IS NOT NULL AND s.ended_at >= ?))
             ORDER BY CASE WHEN s.status IN ('open', 'discovered') THEN 0 ELSE 1 END, m.id DESC
             LIMIT 1"
        );
        $st->execute([$tenantId, $userId, $endedSince]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Session de la communauté à proposer au joueur qui n'en a rejoint aucune : en cours (démarrée depuis $startedSince),
     * sinon la prochaine prévue avant $plannedUntil. Seules les sessions ayant un code actif sont proposées.
     *
     * @return array<string, mixed>|null
     */
    public function communitySession(int $tenantId, string $startedSince, string $plannedUntil): ?array
    {
        $st = $this->pdo()->prepare(
            "SELECT s.* FROM roleplay_game_sessions s
             INNER JOIN roleplay_game_session_codes c ON c.session_id = s.id AND c.tenant_id = s.tenant_id AND c.status = 'active'
             WHERE s.tenant_id = ? AND s.status IN ('open', 'discovered') AND s.ended_at IS NULL
               AND ((s.started_at IS NOT NULL AND s.started_at >= ?)
                 OR (s.started_at IS NULL AND s.planned_starts_at IS NOT NULL AND s.planned_starts_at <= ?))
             ORDER BY CASE WHEN s.started_at IS NOT NULL THEN 0 ELSE 1 END, COALESCE(s.started_at, s.planned_starts_at) DESC, s.id DESC
             LIMIT 1"
        );
        $st->execute([$tenantId, $startedSince, $plannedUntil]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Compteurs de la jauge : rattachés, pointés, en jeu maintenant (plage de temps ouverte).
     *
     * @return array{joined: int, checked_in: int, playing: int}
     */
    public function gauge(int $tenantId, int $sessionId): array
    {
        $st = $this->pdo()->prepare(
            'SELECT COUNT(*) AS joined, COALESCE(SUM(CASE WHEN checked_in_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS checked_in
             FROM roleplay_game_session_members WHERE tenant_id = ? AND session_id = ?'
        );
        $st->execute([$tenantId, $sessionId]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $open = $this->pdo()->prepare(
            'SELECT COUNT(DISTINCT member_id) FROM roleplay_game_session_segments WHERE tenant_id = ? AND session_id = ? AND ended_at IS NULL'
        );
        $open->execute([$tenantId, $sessionId]);

        return [
            'joined' => (int) ($row['joined'] ?? 0),
            'checked_in' => (int) ($row['checked_in'] ?? 0),
            'playing' => (int) $open->fetchColumn(),
        ];
    }

    /** @return array{id: int, status: string, label: string, steam_id: string}|null membre de la communauté */
    public function user(int $tenantId, int $userId): ?array
    {
        $st = $this->pdo()->prepare('SELECT id, status, display_name, callsign, steam_id FROM users WHERE id = ? AND tenant_id = ? LIMIT 1');
        $st->execute([$userId, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'status' => (string) ($row['status'] ?? 'active'),
            'label' => trim((string) ($row['callsign'] ?? '')) ?: trim((string) ($row['display_name'] ?? '')),
            'steam_id' => trim((string) ($row['steam_id'] ?? '')),
        ];
    }

    private static function isDuplicate(PDOException $e): bool
    {
        $info = $e->errorInfo;
        $sqlState = is_array($info) ? (string) ($info[0] ?? '') : '';
        $driver = is_array($info) ? (int) ($info[1] ?? 0) : 0;

        return $driver === 1062 || ($sqlState === '23000' && ($driver === 19 || $driver === 2067 || $driver === 1555)) || $sqlState === '23505';
    }
}
