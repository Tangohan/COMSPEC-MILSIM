<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\AtakMissionKey;
use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Pertes amies remontées par les téléphones de l'ATAK natif (télémétrie « combat », kind « kia », EntityKilled
 * de XEH_postInitClient.sqf). Lues par la relecture (couche des pertes, ReplayRepository::getCasualties).
 * Plusieurs téléphones peuvent signaler le même mort : un seul enregistrement par session, indicatif et lieu (25 m).
 */
class AtakCasualtyRepository
{
    use LazyDatabaseConnection;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public static function dedupeKey(int $tenantId, int $mapId, string $missionKey, string $callSign, float $x, float $y): string
    {
        return sha1($tenantId . '|' . $mapId . '|' . $missionKey . '|' . mb_strtolower($callSign) . '|' . (int) round($x / 25) . '|' . (int) round($y / 25));
    }

    /** @return bool vrai si la perte est nouvelle */
    public function record(int $tenantId, int $mapId, string $missionKey, string $callSign, float $x, float $y, string $kind = 'kia', ?string $reportedBy = null): bool
    {
        $callSign = mb_substr(trim($callSign), 0, 64);
        if ($tenantId < 1 || $callSign === '' || (abs($x) < 1 && abs($y) < 1)) {
            return false;
        }
        $args = [
            $tenantId,
            max(1, $mapId),
            $missionKey,
            $callSign,
            in_array($kind, ['kia', 'wia'], true) ? $kind : 'kia',
            round($x, 2),
            round($y, 2),
            $reportedBy !== null && $reportedBy !== '' ? mb_substr($reportedBy, 0, 64) : null,
            self::dedupeKey($tenantId, $mapId, $missionKey, $callSign, $x, $y),
        ];
        $sql = 'INSERT IGNORE INTO atak_casualties (tenant_id, map_id, mission_key, call_sign, kind, pos_x, pos_y, reported_by, dedupe_key, killed_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())';
        try {
            $st = $this->pdo()->prepare($sql);
            $st->execute($args);
        } catch (\PDOException $e) {
            if (!AtakMissionKey::isMissingSchema($e)) {
                throw $e;
            }
            AtakMissionKey::ensureSchema($this->pdo());
            $st = $this->pdo()->prepare($sql);
            $st->execute($args);
        }

        return $st->rowCount() > 0;
    }
}
