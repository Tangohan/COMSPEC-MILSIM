<?php

declare(strict_types=1);

namespace App\Services\AtakToc;

use App\Core\Database;
use PDO;

/**
 * Accès aux deux tables des actions sur les téléphones (bootstrap/atak_toc_phone_migration.php) : réglage de la
 * communauté et journal des demandes. SQL portable (MySQL, MariaDB, sqlite) : les instants sont calculés en PHP (UTC).
 */
final class TocPhoneStore
{
    private ?PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo;
    }

    private function pdo(): PDO
    {
        return $this->db ??= Database::getPdo();
    }

    public static function dt(int $ts): string
    {
        return gmdate('Y-m-d H:i:s', $ts);
    }

    public static function ts(?string $dt): ?int
    {
        if ($dt === null || $dt === '') {
            return null;
        }
        $t = strtotime($dt . ' UTC');

        return $t === false ? null : $t;
    }

    /** Les deux tables existent (étape 4760 lancée). */
    public function ready(): bool
    {
        try {
            $this->pdo()->query('SELECT 1 FROM atak_toc_phone_settings LIMIT 1');
            $this->pdo()->query('SELECT 1 FROM atak_toc_phone_events LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array{enabled: bool, hide_hostile_journal: bool, updated_by: ?int, updated_at: ?string} */
    public function settings(int $tenantId): array
    {
        $out = ['enabled' => false, 'hide_hostile_journal' => false, 'updated_by' => null, 'updated_at' => null];
        try {
            $st = $this->pdo()->prepare('SELECT enabled, hide_hostile_journal, updated_by, updated_at FROM atak_toc_phone_settings WHERE tenant_id = ? LIMIT 1');
            $st->execute([$tenantId]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return $out;
        }
        if (!is_array($r)) {
            return $out;
        }

        return [
            'enabled' => (int) $r['enabled'] === 1,
            'hide_hostile_journal' => (int) $r['hide_hostile_journal'] === 1,
            'updated_by' => $r['updated_by'] !== null ? (int) $r['updated_by'] : null,
            'updated_at' => $r['updated_at'] !== null ? (string) $r['updated_at'] : null,
        ];
    }

    public function saveSettings(int $tenantId, bool $enabled, bool $hideHostileJournal, ?int $userId, int $now): void
    {
        $st = $this->pdo()->prepare('UPDATE atak_toc_phone_settings SET enabled = ?, hide_hostile_journal = ?, updated_by = ?, updated_at = ? WHERE tenant_id = ?');
        $st->execute([$enabled ? 1 : 0, $hideHostileJournal ? 1 : 0, $userId, self::dt($now), $tenantId]);
        if ($st->rowCount() === 0) {
            $this->pdo()->prepare('INSERT INTO atak_toc_phone_settings (tenant_id, enabled, hide_hostile_journal, updated_by, updated_at) VALUES (?, ?, ?, ?, ?)')
                ->execute([$tenantId, $enabled ? 1 : 0, $hideHostileJournal ? 1 : 0, $userId, self::dt($now)]);
        }
    }

    /**
     * @param array<string, mixed> $e
     */
    public function insertEvent(array $e, int $now): int
    {
        $this->pdo()->prepare(
            'INSERT INTO atak_toc_phone_events
                (tenant_id, map_id, user_id, operator, target_ref, target_label, target_kind, action, level, message, ack_requested,
                 command_id, status, result_text, pos_x, pos_y, accuracy_m, detail_json, ready_at, expires_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            (int) $e['tenant_id'], (int) ($e['map_id'] ?? 1), $e['user_id'] ?? null, mb_substr((string) ($e['operator'] ?? ''), 0, 60),
            (string) $e['target_ref'], mb_substr((string) ($e['target_label'] ?? ''), 0, 80), (string) ($e['target_kind'] ?? 'player'),
            (string) $e['action'], $e['level'] ?? null, isset($e['message']) ? mb_substr((string) $e['message'], 0, 200) : null,
            !empty($e['ack_requested']) ? 1 : 0, $e['command_id'] ?? null, (string) ($e['status'] ?? 'queued'),
            mb_substr((string) ($e['result_text'] ?? ''), 0, 255), $e['pos_x'] ?? null, $e['pos_y'] ?? null, $e['accuracy_m'] ?? null,
            $e['detail_json'] ?? null, $e['ready_at'] ?? null, $e['expires_at'] ?? null, self::dt($now), self::dt($now),
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /** @param array<string, mixed> $fields colonnes à changer (liste blanche) */
    public function updateEvent(int $tenantId, int $id, array $fields, int $now): void
    {
        $allowed = ['status', 'result_text', 'pos_x', 'pos_y', 'accuracy_m', 'detail_json', 'command_id', 'ready_at', 'expires_at'];
        $sets = [];
        $vals = [];
        foreach ($fields as $k => $v) {
            if (in_array($k, $allowed, true)) {
                $sets[] = $k . ' = ?';
                $vals[] = $k === 'result_text' && is_string($v) ? mb_substr($v, 0, 255) : $v;
            }
        }
        if ($sets === []) {
            return;
        }
        $sets[] = 'updated_at = ?';
        $vals[] = self::dt($now);
        $vals[] = $tenantId;
        $vals[] = $id;
        $this->pdo()->prepare('UPDATE atak_toc_phone_events SET ' . implode(', ', $sets) . ' WHERE tenant_id = ? AND id = ?')->execute($vals);
    }

    /** @return array<string, mixed>|null */
    public function event(int $tenantId, int $id): ?array
    {
        $st = $this->pdo()->prepare('SELECT * FROM atak_toc_phone_events WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($r) ? $r : null;
    }

    /**
     * Demandes encore ouvertes (statuts non finaux) : à rafraîchir auprès de la file des commandes.
     *
     * @return list<array<string, mixed>>
     */
    public function openEvents(int $tenantId, int $limit = 60): array
    {
        $st = $this->pdo()->prepare(
            "SELECT * FROM atak_toc_phone_events WHERE tenant_id = ? AND status IN ('queued', 'delivered', 'running') ORDER BY id DESC LIMIT " . max(1, min(200, $limit))
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array{target_ref?: string, since?: int, actions?: list<string>, statuses?: list<string>} $filter
     * @return list<array<string, mixed>>
     */
    public function events(int $tenantId, array $filter = [], int $limit = 60): array
    {
        $sql = 'SELECT * FROM atak_toc_phone_events WHERE tenant_id = ?';
        $args = [$tenantId];
        if (isset($filter['target_ref'])) {
            $sql .= ' AND target_ref = ?';
            $args[] = $filter['target_ref'];
        }
        if (isset($filter['since'])) {
            $sql .= ' AND created_at >= ?';
            $args[] = self::dt((int) $filter['since']);
        }
        foreach (['actions' => 'action', 'statuses' => 'status'] as $key => $col) {
            if (!empty($filter[$key])) {
                $sql .= ' AND ' . $col . ' IN (' . implode(',', array_fill(0, count($filter[$key]), '?')) . ')';
                array_push($args, ...$filter[$key]);
            }
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . max(1, min(300, $limit));
        $st = $this->pdo()->prepare($sql);
        $st->execute($args);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Nombre de demandes comptées pour une limite de débit (les refus ne comptent pas).
     *
     * @param array{user_id?: int, target_ref?: string, status?: string} $where
     */
    public function countRecent(int $tenantId, string $action, int $since, array $where = []): int
    {
        $sql = "SELECT COUNT(*) FROM atak_toc_phone_events WHERE tenant_id = ? AND action = ? AND created_at >= ? AND status <> 'refused'";
        $args = [$tenantId, $action, self::dt($since)];
        if (isset($where['user_id'])) {
            $sql .= ' AND user_id = ?';
            $args[] = $where['user_id'];
        }
        if (isset($where['target_ref'])) {
            $sql .= ' AND target_ref = ?';
            $args[] = $where['target_ref'];
        }
        $st = $this->pdo()->prepare($sql);
        $st->execute($args);

        return (int) $st->fetchColumn();
    }

    /** Triangulations dont le résultat n'est pas encore livré (écoute en cours). */
    public function countRunning(int $tenantId, ?int $userId, int $now): int
    {
        $sql = "SELECT COUNT(*) FROM atak_toc_phone_events WHERE tenant_id = ? AND action = 'triangulate' AND status = 'running' AND ready_at > ?";
        $args = [$tenantId, self::dt($now)];
        if ($userId !== null) {
            $sql .= ' AND user_id = ?';
            $args[] = $userId;
        }
        $st = $this->pdo()->prepare($sql);
        $st->execute($args);

        return (int) $st->fetchColumn();
    }

    /** Notifications d'un téléphone encore en file ou non lues sur le téléphone (plafond par cible). */
    public function countOpenNotifies(int $tenantId, string $targetRef): int
    {
        $st = $this->pdo()->prepare("SELECT COUNT(*) FROM atak_toc_phone_events WHERE tenant_id = ? AND target_ref = ? AND action = 'notify' AND status IN ('queued', 'delivered')");
        $st->execute([$tenantId, $targetRef]);

        return (int) $st->fetchColumn();
    }
}
