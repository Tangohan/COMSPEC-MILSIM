<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Compteurs journaliers d'ingestion jeu, par joueur et par motif de rejet.
 *
 * Alimente le Data Inspector : débit transmis par joueur, événements acceptés/rejetés,
 * taux d'erreur d'ingestion. Écritures best-effort (jamais bloquantes pour le jeu).
 */
final class AtakIngestPlayerStatsRepository
{
    private static bool $ensured = false;

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public static function schemaSql(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS atak_ingest_player_daily (
                tenant_id INT UNSIGNED NOT NULL,
                day DATE NOT NULL,
                steam_uid VARCHAR(32) NOT NULL,
                callsign VARCHAR(100) NULL,
                bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
                photo_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
                requests INT UNSIGNED NOT NULL DEFAULT 0,
                events_accepted INT UNSIGNED NOT NULL DEFAULT 0,
                events_rejected INT UNSIGNED NOT NULL DEFAULT 0,
                last_at DATETIME NOT NULL,
                PRIMARY KEY (tenant_id, day, steam_uid),
                KEY idx_aipd_last (tenant_id, last_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS atak_ingest_error_daily (
                tenant_id INT UNSIGNED NOT NULL,
                day DATE NOT NULL,
                event_type VARCHAR(32) NOT NULL,
                error_code VARCHAR(64) NOT NULL,
                hits INT UNSIGNED NOT NULL DEFAULT 0,
                last_steam_uid VARCHAR(32) NULL,
                last_at DATETIME NOT NULL,
                PRIMARY KEY (tenant_id, day, event_type, error_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
    }

    /** Pose les tables si absentes (aussi appelé en lecture par le Data Inspector). */
    public function ensure(): void
    {
        if (self::$ensured) {
            return;
        }
        self::$ensured = true;
        foreach (self::schemaSql() as $sql) {
            try {
                $this->pdo()->exec($sql);
            } catch (\Throwable) {
            }
        }
    }

    /**
     * Une requête jeu reçue (octets bruts du corps).
     */
    public function recordRequest(int $tenantId, ?string $steamUid, ?string $callsign, int $bytes, bool $isPhoto = false): void
    {
        $steam = $this->key($steamUid, $callsign);
        if ($tenantId < 1 || $steam === null || $bytes < 1) {
            return;
        }
        $this->ensure();
        try {
            $this->pdo()->prepare(
                'INSERT INTO atak_ingest_player_daily (tenant_id, day, steam_uid, callsign, bytes, photo_bytes, requests, last_at)
                 VALUES (?, CURDATE(), ?, ?, ?, ?, 1, NOW())
                 ON DUPLICATE KEY UPDATE bytes = bytes + VALUES(bytes), photo_bytes = photo_bytes + VALUES(photo_bytes),
                    requests = requests + 1, callsign = COALESCE(VALUES(callsign), callsign), last_at = VALUES(last_at)'
            )->execute([$tenantId, $steam, $this->cs($callsign), $bytes, $isPhoto ? $bytes : 0]);
        } catch (\Throwable) {
        }
    }

    /**
     * Résultat d'un lot télémétrie : compteurs acceptés/rejetés + motifs de rejet.
     *
     * @param list<array<string, mixed>> $results lignes `results` de AtakTelemetryBatchIngest
     */
    public function recordBatchOutcome(int $tenantId, ?string $steamUid, ?string $callsign, int $accepted, int $rejected, array $results): void
    {
        $steam = $this->key($steamUid, $callsign);
        if ($tenantId < 1 || ($accepted < 1 && $rejected < 1)) {
            return;
        }
        $this->ensure();
        try {
            if ($steam !== null) {
                $this->pdo()->prepare(
                    'INSERT INTO atak_ingest_player_daily (tenant_id, day, steam_uid, callsign, events_accepted, events_rejected, last_at)
                     VALUES (?, CURDATE(), ?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE events_accepted = events_accepted + VALUES(events_accepted),
                        events_rejected = events_rejected + VALUES(events_rejected),
                        callsign = COALESCE(VALUES(callsign), callsign), last_at = VALUES(last_at)'
                )->execute([$tenantId, $steam, $this->cs($callsign), max(0, $accepted), max(0, $rejected)]);
            }
            if ($rejected > 0) {
                $st = $this->pdo()->prepare(
                    'INSERT INTO atak_ingest_error_daily (tenant_id, day, event_type, error_code, hits, last_steam_uid, last_at)
                     VALUES (?, CURDATE(), ?, ?, 1, ?, NOW())
                     ON DUPLICATE KEY UPDATE hits = hits + 1, last_steam_uid = VALUES(last_steam_uid), last_at = VALUES(last_at)'
                );
                foreach ($results as $r) {
                    if (!is_array($r) || !empty($r['ok'])) {
                        continue;
                    }
                    $st->execute([
                        $tenantId,
                        mb_substr((string) ($r['t'] ?? 'inconnu'), 0, 32),
                        mb_substr((string) ($r['error'] ?? 'rejected'), 0, 64),
                        $steam,
                    ]);
                }
            }
        } catch (\Throwable) {
        }
    }

    /** Joueur sans Steam (solo/hôte local) : regroupé par indicatif pour ne pas perdre son trafic. */
    private function key(?string $steamUid, ?string $callsign): ?string
    {
        $s = trim((string) $steamUid);
        if ($s !== '') {
            return mb_substr($s, 0, 32);
        }
        $c = $this->cs($callsign);

        return $c !== null ? mb_substr('cs:' . $c, 0, 32) : null;
    }

    private function cs(?string $callsign): ?string
    {
        $c = trim((string) $callsign);

        return $c === '' ? null : mb_substr($c, 0, 100);
    }
}
