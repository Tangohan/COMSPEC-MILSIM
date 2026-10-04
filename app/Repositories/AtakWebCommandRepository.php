<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use App\Support\SilentSchemaMigration;
use PDO;

/**
 * File des commandes web → téléphones en jeu (drones, charges, notifications du téléphone)
 * et miroir des notifications reçues par le téléphone ATAK de chaque joueur.
 *
 * Format « fil » lu par le mod (ligne de GET /api/atak/explosive-timers/commands) :
 *   charge_id = "@wc", id = identifiant de commande,
 *   requested_by = "WC1~<kind>~<uid cible | *>~<cmd>~<réf>~clé=valeur~clé=valeur..."
 * La DLL nettoie | tabulations et retours ligne : le fil n’en contient jamais.
 */
class AtakWebCommandRepository
{
    use LazyDatabaseConnection;

    public const KINDS = ['uav', 'explo', 'notif'];

    /** Statuts : pending (en file) → accepted (pris par le téléphone) → done | failed ; expired | cancelled. */
    public const FINAL_STATUSES = ['done', 'failed', 'expired', 'cancelled'];

    public const NOTIF_TYPES = ['INFO', 'SUCCESS', 'WARNING', 'ERROR', 'MESSAGE', 'TACTICAL'];

    private static ?bool $schemaReady = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function ensureSchema(): bool
    {
        if (self::$schemaReady !== null) {
            return self::$schemaReady;
        }
        try {
            SilentSchemaMigration::run(dirname(__DIR__, 2) . '/bootstrap/atak_web_commands_migration.php', $this->pdo());
            $st = $this->pdo()->query(
                "SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME IN ('atak_web_commands', 'atak_phone_notifs', 'atak_phone_notif_prefs')"
            );
            self::$schemaReady = $st !== false && (int) $st->fetchColumn() === 3;
        } catch (\Throwable) {
            self::$schemaReady = false;
        }

        return self::$schemaReady;
    }

    /* ------------------------------------------------------------------ */
    /* Commandes                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * @param array<string, mixed> $args       arguments lisibles (journal web)
     * @param array<string, scalar> $wireArgs  arguments envoyés au jeu (clé=valeur)
     * @return array<string, mixed>|null
     */
    public function enqueue(
        int $tenantId,
        int $mapId,
        string $kind,
        string $cmd,
        string $targetUid,
        string $targetRef,
        string $targetLabel,
        array $args,
        array $wireArgs,
        string $requestedBy,
        ?int $userId,
        int $ttlSeconds = 90
    ): ?array {
        if (!$this->ensureSchema() || !in_array($kind, self::KINDS, true)) {
            return null;
        }
        $cmd = self::wireToken($cmd, 24);
        if ($cmd === '') {
            return null;
        }
        $targetUid = $targetUid === '*' ? '*' : (preg_replace('/\D/', '', $targetUid) ?? '');
        if ($targetUid === '') {
            return null;
        }
        $wire = self::buildWire($kind, $targetUid, $cmd, $targetRef, $wireArgs);
        if (strlen($wire) > 480) {
            return null;
        }
        $this->pdo()->prepare(
            'INSERT INTO atak_web_commands
                (tenant_id, map_id, kind, cmd, target_uid, target_ref, target_label, args_json, wire,
                 status, requested_by, requested_by_user_id, ttl_seconds)
             VALUES
                (:tenant_id, :map_id, :kind, :cmd, :target_uid, :target_ref, :target_label, :args_json, :wire,
                 \'pending\', :requested_by, :user_id, :ttl)'
        )->execute([
            'tenant_id' => $tenantId,
            'map_id' => max(1, $mapId),
            'kind' => $kind,
            'cmd' => $cmd,
            'target_uid' => $targetUid,
            'target_ref' => mb_substr($targetRef, 0, 400),
            'target_label' => mb_substr($targetLabel, 0, 160),
            'args_json' => json_encode($args, JSON_UNESCAPED_UNICODE) ?: '{}',
            'wire' => $wire,
            'requested_by' => mb_substr($requestedBy, 0, 120),
            'user_id' => $userId !== null && $userId > 0 ? $userId : null,
            'ttl' => max(15, min(86400, $ttlSeconds)),
        ]);

        return $this->find($tenantId, (int) $this->pdo()->lastInsertId());
    }

    /** Annule les commandes encore en file du même type pour la même cible (ex. préférences remplacées). */
    public function supersede(int $tenantId, string $kind, string $cmd, string $targetUid): void
    {
        if (!$this->ensureSchema()) {
            return;
        }
        $this->pdo()->prepare(
            "UPDATE atak_web_commands
             SET status = 'cancelled', result_text = 'Remplacée par une demande plus récente'
             WHERE tenant_id = ? AND kind = ? AND cmd = ? AND target_uid = ? AND status = 'pending'"
        )->execute([$tenantId, $kind, $cmd, $targetUid]);
    }

    public function expireStale(int $tenantId): void
    {
        if (!$this->ensureSchema()) {
            return;
        }
        try {
            $this->pdo()->prepare(
                "UPDATE atak_web_commands
                 SET status = 'expired',
                     result_text = IF(status = 'accepted', 'Pas de compte rendu du téléphone', 'Aucun téléphone n’a répondu à temps')
                 WHERE tenant_id = ? AND status IN ('pending', 'accepted')
                   AND created_at < (NOW() - INTERVAL ttl_seconds SECOND)"
            )->execute([$tenantId]);
        } catch (\Throwable) {
        }
    }

    /**
     * Lignes à fusionner dans GET /api/atak/explosive-timers/commands.
     *
     * @return list<array{id:int,charge_id:string,requested_by:string}>
     */
    public function wireRows(int $tenantId, int $mapId): array
    {
        if (!$this->ensureSchema()) {
            return [];
        }
        $this->expireStale($tenantId);
        $st = $this->pdo()->prepare(
            "SELECT id, wire FROM atak_web_commands
             WHERE tenant_id = :tenant_id AND status = 'pending'
               AND (map_id = :map_id OR kind = 'notif')
             ORDER BY id ASC
             LIMIT 24"
        );
        $st->execute(['tenant_id' => $tenantId, 'map_id' => max(1, $mapId)]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'charge_id' => '@wc',
                'requested_by' => (string) $row['wire'],
            ];
        }

        return $out;
    }

    /**
     * Compte rendu du téléphone. Une commande ciblée n’accepte que la réponse de son pilote / propriétaire.
     *
     * @param array<string, mixed> $data
     */
    public function ack(int $tenantId, int $id, ?string $steamUid, string $status, string $text, array $data = []): bool
    {
        if (!$this->ensureSchema() || $id < 1) {
            return false;
        }
        if (!in_array($status, ['accepted', 'done', 'failed'], true)) {
            return false;
        }
        $row = $this->find($tenantId, $id);
        if ($row === null || in_array($row['status'], self::FINAL_STATUSES, true)) {
            return false;
        }
        $uid = $steamUid !== null ? (preg_replace('/\D/', '', $steamUid) ?? '') : '';
        if ($row['target_uid'] !== '*' && $uid !== '' && $uid !== $row['target_uid']) {
            return false;
        }
        $this->pdo()->prepare(
            "UPDATE atak_web_commands
             SET status = :status, result_text = :text, result_json = :data, acked_by_uid = :uid, acked_at = NOW()
             WHERE id = :id AND tenant_id = :tenant_id AND status IN ('pending', 'accepted')"
        )->execute([
            'status' => $status,
            'text' => mb_substr($text, 0, 255),
            'data' => $data === [] ? null : (json_encode($data, JSON_UNESCAPED_UNICODE) ?: null),
            'uid' => $uid,
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);
        if ($row['kind'] === 'notif' && $row['cmd'] === 'prefs' && $status === 'done' && $row['target_uid'] !== '*') {
            $this->pdo()->prepare(
                'UPDATE atak_phone_notif_prefs SET synced_at = NOW() WHERE tenant_id = ? AND steam_uid = ?'
            )->execute([$tenantId, $row['target_uid']]);
        }

        return true;
    }

    public function cancel(int $tenantId, int $id): bool
    {
        if (!$this->ensureSchema()) {
            return false;
        }
        $st = $this->pdo()->prepare(
            "UPDATE atak_web_commands SET status = 'cancelled', result_text = 'Annulée depuis le poste'
             WHERE tenant_id = ? AND id = ? AND status = 'pending'"
        );
        $st->execute([$tenantId, $id]);

        return $st->rowCount() > 0;
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM atak_web_commands WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->present($row) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecent(int $tenantId, int $mapId, ?string $kind = null, int $limit = 40, ?string $targetUid = null): array
    {
        if (!$this->ensureSchema()) {
            return [];
        }
        $this->expireStale($tenantId);
        $sql = 'SELECT * FROM atak_web_commands WHERE tenant_id = :tenant_id AND map_id = :map_id
                AND created_at >= (NOW() - INTERVAL 6 HOUR)';
        $params = ['tenant_id' => $tenantId, 'map_id' => max(1, $mapId)];
        if ($kind !== null && in_array($kind, self::KINDS, true)) {
            $sql .= ' AND kind = :kind';
            $params['kind'] = $kind;
        }
        if ($targetUid !== null) {
            $sql .= ' AND target_uid = :target_uid';
            $params['target_uid'] = $targetUid;
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . max(1, min(200, $limit));
        $st = $this->pdo()->prepare($sql);
        $st->execute($params);

        return array_map([$this, 'present'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        $args = json_decode((string) ($row['args_json'] ?? ''), true);
        $data = json_decode((string) ($row['result_json'] ?? ''), true);

        return [
            'id' => (int) $row['id'],
            'map_id' => (int) $row['map_id'],
            'kind' => (string) $row['kind'],
            'cmd' => (string) $row['cmd'],
            'target_uid' => (string) $row['target_uid'],
            'target_ref' => (string) $row['target_ref'],
            'target_label' => (string) $row['target_label'],
            'args' => is_array($args) ? $args : [],
            'status' => (string) $row['status'],
            'result_text' => (string) $row['result_text'],
            'result' => is_array($data) ? $data : null,
            'requested_by' => (string) $row['requested_by'],
            'created_at' => (string) $row['created_at'],
            'acked_at' => $row['acked_at'] !== null ? (string) $row['acked_at'] : null,
            'ttl_seconds' => (int) $row['ttl_seconds'],
        ];
    }

    /**
     * @param array<string, scalar> $wireArgs
     */
    public static function buildWire(string $kind, string $targetUid, string $cmd, string $ref, array $wireArgs): string
    {
        // Jamais de segment vide : splitString côté SQF fusionnerait les séparateurs.
        $refWire = self::wireValue($ref, 360);
        $parts = ['WC1', $kind, $targetUid, $cmd, $refWire !== '' ? $refWire : '-'];
        foreach ($wireArgs as $k => $v) {
            $key = self::wireToken((string) $k, 12);
            if ($key === '') {
                continue;
            }
            if (is_bool($v)) {
                $v = $v ? '1' : '0';
            } elseif (is_float($v)) {
                $v = rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
            }
            $parts[] = $key . '=' . self::wireValue((string) $v, 120);
        }

        return implode('~', $parts);
    }

    /** Jeton simple (lettres, chiffres, _). */
    public static function wireToken(string $s, int $max): string
    {
        return substr(preg_replace('/[^A-Za-z0-9_]/', '', $s) ?? '', 0, $max);
    }

    /** Valeur de fil : ASCII sûr pour SQF (pas de ~ = | tab, retour ligne, guillemets). */
    public static function wireValue(string $s, int $max): string
    {
        return substr(preg_replace('/[^A-Za-z0-9 .:,_@\-]/', '', $s) ?? '', 0, $max);
    }

    /* ------------------------------------------------------------------ */
    /* Notifications du téléphone                                          */
    /* ------------------------------------------------------------------ */

    /**
     * @param list<array<string, mixed>> $items
     */
    public function ingestNotifs(int $tenantId, string $steamUid, array $items, ?int $unread): int
    {
        if (!$this->ensureSchema() || $steamUid === '') {
            return 0;
        }
        $ins = $this->pdo()->prepare(
            'INSERT INTO atak_phone_notifs (tenant_id, steam_uid, ntype, message, game_time)
             VALUES (?, ?, ?, ?, ?)'
        );
        $n = 0;
        foreach (array_slice($items, 0, 30) as $it) {
            if (!is_array($it)) {
                continue;
            }
            $type = strtoupper(trim((string) ($it['t'] ?? $it['type'] ?? 'INFO')));
            if (!in_array($type, self::NOTIF_TYPES, true)) {
                $type = 'INFO';
            }
            $msg = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($it['m'] ?? $it['message'] ?? ''))) ?? '');
            if ($msg === '') {
                continue;
            }
            $time = substr(preg_replace('/[^0-9:]/', '', (string) ($it['h'] ?? $it['time'] ?? '')) ?? '', 0, 8);
            $ins->execute([$tenantId, $steamUid, $type, mb_substr($msg, 0, 400), $time]);
            $n++;
        }
        // Présence + compteur non lu du téléphone (ligne de préférences créée au besoin).
        $this->pdo()->prepare(
            'INSERT INTO atak_phone_notif_prefs (tenant_id, steam_uid, game_unread, last_seen_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE game_unread = VALUES(game_unread), last_seen_at = NOW()'
        )->execute([$tenantId, $steamUid, max(0, (int) ($unread ?? 0))]);
        if ($n > 0) {
            // On garde les 300 dernières par joueur.
            $st = $this->pdo()->prepare('SELECT MAX(id) FROM atak_phone_notifs WHERE tenant_id = ? AND steam_uid = ?');
            $st->execute([$tenantId, $steamUid]);
            $max = (int) $st->fetchColumn();
            if ($max > 300) {
                $this->pdo()->prepare(
                    'DELETE n FROM atak_phone_notifs n
                     JOIN (SELECT id FROM atak_phone_notifs WHERE tenant_id = ? AND steam_uid = ?
                           ORDER BY id DESC LIMIT 1 OFFSET 300) cut ON n.id <= cut.id
                     WHERE n.tenant_id = ? AND n.steam_uid = ?'
                )->execute([$tenantId, $steamUid, $tenantId, $steamUid]);
            }
        }

        return $n;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listNotifs(int $tenantId, string $steamUid, int $limit = 60): array
    {
        if (!$this->ensureSchema() || $steamUid === '') {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT id, ntype, message, game_time, received_at, read_at FROM atak_phone_notifs
             WHERE tenant_id = ? AND steam_uid = ? ORDER BY id DESC LIMIT ' . max(1, min(200, $limit))
        );
        $st->execute([$tenantId, $steamUid]);

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'type' => (string) $r['ntype'],
            'message' => (string) $r['message'],
            'game_time' => (string) $r['game_time'],
            'received_at' => (string) $r['received_at'],
            'read' => $r['read_at'] !== null,
        ], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function markAllRead(int $tenantId, string $steamUid): int
    {
        if (!$this->ensureSchema() || $steamUid === '') {
            return 0;
        }
        $st = $this->pdo()->prepare(
            'UPDATE atak_phone_notifs SET read_at = NOW() WHERE tenant_id = ? AND steam_uid = ? AND read_at IS NULL'
        );
        $st->execute([$tenantId, $steamUid]);

        return $st->rowCount();
    }

    /** @return array<string, mixed> */
    public function getPrefs(int $tenantId, string $steamUid): array
    {
        $out = [
            'muted' => [],
            'silent' => false,
            'banners' => true,
            'toast_seconds' => 0,
            'game_unread' => 0,
            'last_seen_at' => null,
            'synced_at' => null,
            'updated_at' => null,
        ];
        if (!$this->ensureSchema() || $steamUid === '') {
            return $out;
        }
        $st = $this->pdo()->prepare('SELECT * FROM atak_phone_notif_prefs WHERE tenant_id = ? AND steam_uid = ? LIMIT 1');
        $st->execute([$tenantId, $steamUid]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            return $out;
        }
        $muted = array_values(array_filter(
            explode(',', (string) $r['muted_types']),
            static fn (string $t): bool => in_array($t, self::NOTIF_TYPES, true)
        ));

        return [
            'muted' => $muted,
            'silent' => (bool) $r['silent'],
            'banners' => (bool) $r['banners'],
            'toast_seconds' => (int) $r['toast_seconds'],
            'game_unread' => (int) $r['game_unread'],
            'last_seen_at' => $r['last_seen_at'] !== null ? (string) $r['last_seen_at'] : null,
            'synced_at' => $r['synced_at'] !== null ? (string) $r['synced_at'] : null,
            'updated_at' => (string) $r['updated_at'],
        ];
    }

    /**
     * @param list<string> $muted
     */
    public function savePrefs(int $tenantId, string $steamUid, array $muted, bool $silent, bool $banners, int $toastSeconds): void
    {
        if (!$this->ensureSchema() || $steamUid === '') {
            return;
        }
        $muted = array_values(array_unique(array_filter(
            array_map(static fn ($t): string => strtoupper(trim((string) $t)), $muted),
            static fn (string $t): bool => in_array($t, self::NOTIF_TYPES, true)
        )));
        $this->pdo()->prepare(
            'INSERT INTO atak_phone_notif_prefs (tenant_id, steam_uid, muted_types, silent, banners, toast_seconds, synced_at)
             VALUES (?, ?, ?, ?, ?, ?, NULL)
             ON DUPLICATE KEY UPDATE muted_types = VALUES(muted_types), silent = VALUES(silent),
                banners = VALUES(banners), toast_seconds = VALUES(toast_seconds), synced_at = NULL'
        )->execute([$tenantId, $steamUid, implode(',', $muted), $silent ? 1 : 0, $banners ? 1 : 0, max(0, min(30, $toastSeconds))]);
    }
}
