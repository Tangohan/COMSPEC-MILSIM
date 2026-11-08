<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\Watch\WatchSchema;
use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Alertes d'activité anormale (mod) et de charge, pour l'administration de la plateforme seulement.
 *
 * Une alerte garde : la règle, la communauté, le joueur (compte et Steam), les valeurs mesurées et l'heure. Aucune
 * adresse IP, aucun e-mail. Les doublons sont regroupés : même règle, même communauté, même acteur = une seule ligne dont
 * le compteur d'occurrences monte (pendant 2 h tant qu'elle est ouverte ou prise en compte ; 24 h après un « ignorer »).
 * Conservation : WatchSchema::RETENTION_ALERT_DAYS, purge par lots.
 */
class PlatformAbuseAlertRepository
{
    use LazyDatabaseConnection;

    public const STATUS_OPEN = 'open';
    public const STATUS_ACK = 'ack';
    public const STATUS_DISMISSED = 'dismissed';

    public const KIND_MOD = 'mod';
    public const KIND_LOAD = 'load';

    /** Regroupement des répétitions d'une alerte encore active (secondes). */
    public const MERGE_ACTIVE_SECONDS = 7200;

    /** Silence après un « ignorer » (secondes). */
    public const MERGE_DISMISSED_SECONDS = 86400;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /** Identité de regroupement d'une détection. */
    public static function fingerprint(string $kind, string $rule, ?int $tenantId, string $key): string
    {
        return sha1($kind . '|' . $rule . '|' . (int) $tenantId . '|' . $key);
    }

    /**
     * Enregistre une détection ou l'ajoute à l'alerte existante. Faux si elle est ignorée (silence après « ignorer »)
     * ou en cas d'échec ; ne lève pas.
     *
     * @param array{kind?: string, rule: string, tenant_id?: ?int, steam_uid?: ?string, key?: string, measured?: array<string, mixed>} $d
     * @return 'created'|'merged'|'skipped'|'error'
     */
    public function raise(array $d, ?int $now = null): string
    {
        $now ??= time();
        $kind = (string) ($d['kind'] ?? self::KIND_MOD);
        $rule = substr((string) ($d['rule'] ?? ''), 0, 48);
        $tenantId = isset($d['tenant_id']) && (int) $d['tenant_id'] > 0 ? (int) $d['tenant_id'] : null;
        $steam = isset($d['steam_uid']) && $d['steam_uid'] !== '' ? substr((string) $d['steam_uid'], 0, 32) : null;
        if ($rule === '') {
            return 'error';
        }
        $fingerprint = self::fingerprint($kind, $rule, $tenantId, (string) ($d['key'] ?? ($steam ?? '')));
        $at = gmdate('Y-m-d H:i:s', $now);
        $measured = json_encode($d['measured'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        try {
            $st = $this->pdo()->prepare('SELECT id, status, last_seen_at FROM platform_abuse_alerts WHERE fingerprint = ? ORDER BY id DESC LIMIT 1');
            $st->execute([$fingerprint]);
            $last = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            if (is_array($last)) {
                $age = $now - (int) (strtotime((string) $last['last_seen_at'] . ' UTC') ?: 0);
                $status = (string) $last['status'];
                if ($status === self::STATUS_DISMISSED && $age < self::MERGE_DISMISSED_SECONDS) {
                    return 'skipped';
                }
                if (in_array($status, [self::STATUS_OPEN, self::STATUS_ACK], true) && $age < self::MERGE_ACTIVE_SECONDS) {
                    $up = $this->pdo()->prepare('UPDATE platform_abuse_alerts SET occurrences = occurrences + 1, last_seen_at = ?, measured_json = ? WHERE id = ?');
                    $up->execute([$at, $measured, (int) $last['id']]);

                    return 'merged';
                }
            }
            $this->pdo()->prepare(
                'INSERT INTO platform_abuse_alerts (kind, rule, fingerprint, tenant_id, user_id, steam_uid, measured_json, occurrences, first_seen_at, last_seen_at, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)'
            )->execute([$kind, $rule, $fingerprint, $tenantId, $this->userIdFor($tenantId, $steam), $steam, $measured, $at, $at, self::STATUS_OPEN]);

            return 'created';
        } catch (\Throwable) {
            return 'error';
        }
    }

    private function userIdFor(?int $tenantId, ?string $steam): ?int
    {
        if ($tenantId === null || $steam === null) {
            return null;
        }
        try {
            $st = $this->pdo()->prepare('SELECT id FROM users WHERE tenant_id = ? AND steam_id = ? LIMIT 1');
            $st->execute([$tenantId, $steam]);
            $id = (int) $st->fetchColumn();

            return $id > 0 ? $id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param string $status open, ack, dismissed ou '' (toutes)
     * @return list<array<string, mixed>> lignes avec nom de communauté et identité du joueur (administration seulement)
     */
    public function recent(string $status = '', string $kind = '', int $limit = 100): array
    {
        $where = [];
        $args = [];
        if ($status !== '') {
            $where[] = 'a.status = ?';
            $args[] = $status;
        }
        if ($kind !== '') {
            $where[] = 'a.kind = ?';
            $args[] = $kind;
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT a.id, a.kind, a.rule, a.tenant_id, a.user_id, a.steam_uid, a.measured_json, a.occurrences, a.first_seen_at, a.last_seen_at,
                        a.status, a.handled_at, t.name AS tenant_name, u.display_name AS user_name, u.callsign AS user_callsign
                 FROM platform_abuse_alerts a
                 LEFT JOIN tenants t ON t.id = a.tenant_id
                 LEFT JOIN users u ON u.id = a.user_id
                 ' . ($where === [] ? '' : 'WHERE ' . implode(' AND ', $where)) . '
                 ORDER BY a.last_seen_at DESC, a.id DESC LIMIT ' . max(1, min(500, $limit))
            );
            $st->execute($args);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string, int> nombre d'alertes par statut */
    public function countsByStatus(string $kind = ''): array
    {
        $out = [self::STATUS_OPEN => 0, self::STATUS_ACK => 0, self::STATUS_DISMISSED => 0];
        try {
            $st = $this->pdo()->prepare(
                'SELECT status, COUNT(*) FROM platform_abuse_alerts' . ($kind !== '' ? ' WHERE kind = ?' : '') . ' GROUP BY status'
            );
            $st->execute($kind !== '' ? [$kind] : []);
            foreach ($st->fetchAll(PDO::FETCH_NUM) ?: [] as [$status, $n]) {
                $out[(string) $status] = (int) $n;
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    /** Décision humaine : prise en compte, ignorée ou rouverte. Faux si la ligne n'existe pas ou l'état est inconnu. */
    public function setStatus(int $id, string $status, ?int $userId, ?int $now = null): bool
    {
        if ($id < 1 || !in_array($status, [self::STATUS_OPEN, self::STATUS_ACK, self::STATUS_DISMISSED], true)) {
            return false;
        }
        try {
            $st = $this->pdo()->prepare('UPDATE platform_abuse_alerts SET status = ?, handled_by = ?, handled_at = ? WHERE id = ?');
            $st->execute([$status, $userId, gmdate('Y-m-d H:i:s', $now ?? time()), $id]);

            return $st->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        try {
            $st = $this->pdo()->prepare('SELECT * FROM platform_abuse_alerts WHERE id = ?');
            $st->execute([$id]);

            return $st->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Supprime les alertes plus anciennes que la conservation, par lots bornés. Retourne le nombre supprimé.
     */
    public function purgeOlderThan(int $days, int $now, int $batch = 500, int $maxBatches = 20): int
    {
        $limit = gmdate('Y-m-d H:i:s', $now - max(1, $days) * 86400);
        $total = 0;
        try {
            $st = $this->pdo()->prepare('DELETE FROM platform_abuse_alerts WHERE last_seen_at < ? LIMIT ' . max(1, $batch));
            for ($i = 0; $i < $maxBatches; $i++) {
                $st->execute([$limit]);
                $n = $st->rowCount();
                $total += $n;
                if ($n < $batch) {
                    break;
                }
            }
        } catch (\Throwable) {
        }

        return $total;
    }

    public function ensure(): void
    {
        try {
            WatchSchema::ensure($this->pdo());
        } catch (\Throwable) {
        }
    }
}
