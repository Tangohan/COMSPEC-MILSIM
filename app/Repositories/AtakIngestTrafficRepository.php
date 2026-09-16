<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

final class AtakIngestTrafficRepository
{
    use LazyDatabaseConnection;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function record(int $tenantId, int $mapId, int $bytes, bool $isPhoto = false): void
    {
        if ($tenantId < 1 || $bytes < 1) {
            return;
        }
        $mapId = max(1, $mapId);
        $photo = $isPhoto ? $bytes : 0;
        try {
            $st = $this->pdo()->prepare(
                'INSERT INTO atak_ingest_traffic (tenant_id, map_id, bucket_ts, bytes, photo_bytes, requests)
                 VALUES (?, ?, FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP()/10)*10), ?, ?, 1)
                 ON DUPLICATE KEY UPDATE
                    bytes = bytes + VALUES(bytes),
                    photo_bytes = photo_bytes + VALUES(photo_bytes),
                    requests = requests + 1'
            );
            $st->execute([$tenantId, $mapId, $bytes, $photo]);
        } catch (\Throwable) {
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(int $tenantId, int $mapId): array
    {
        $empty = [
            'kbps_now' => 0.0,
            'bytes_15m' => 0,
            'photo_bytes_15m' => 0,
            'last_sync_ago' => 'Aucune remontée',
            'series' => array_fill(0, 18, 0),
        ];
        if ($tenantId < 1) {
            return $empty;
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT bucket_ts, bytes, photo_bytes, requests
                 FROM atak_ingest_traffic
                 WHERE tenant_id = ? AND map_id = ? AND bucket_ts >= (NOW() - INTERVAL 15 MINUTE)
                 ORDER BY bucket_ts ASC'
            );
            $st->execute([$tenantId, max(1, $mapId)]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return $empty;
        }
        $bytes15 = 0;
        $photo15 = 0;
        $last = null;
        $byBucket = [];
        foreach ($rows as $row) {
            $bytes15 += (int) ($row['bytes'] ?? 0);
            $photo15 += (int) ($row['photo_bytes'] ?? 0);
            $ts = strtotime((string) ($row['bucket_ts'] ?? '')) ?: 0;
            if ($ts > 0) {
                $byBucket[$ts] = (int) ($row['bytes'] ?? 0);
                $last = max($last ?? 0, $ts);
            }
        }
        $nowBucket = (int) (floor(time() / 10) * 10);
        $series = [];
        for ($i = 17; $i >= 0; $i--) {
            $t = $nowBucket - ($i * 50);
            $acc = 0;
            for ($j = 0; $j < 5; $j++) {
                $acc += $byBucket[$t + ($j * 10)] ?? 0;
            }
            $series[] = $acc;
        }
        $recent = 0;
        for ($k = 0; $k < 3; $k++) {
            $recent += $byBucket[$nowBucket - ($k * 10)] ?? 0;
        }
        $kbps = $recent / 30.0 / 1024.0 * 8.0; // bits? Plan asked ko/s of Content-Length
        $kos = $recent / 30.0 / 1024.0;

        return [
            'kbps_now' => round($kos, 2),
            'bytes_15m' => $bytes15,
            'photo_bytes_15m' => $photo15,
            'last_sync_ago' => $this->agoLabel($last),
            'series' => $series,
        ];
    }

    private function agoLabel(?int $ts): string
    {
        if ($ts === null || $ts < 1) {
            return 'Aucune remontée';
        }
        $sec = max(0, time() - $ts);
        if ($sec < 8) {
            return 'À l’instant';
        }
        if ($sec < 60) {
            return 'il y a ' . $sec . ' s';
        }
        if ($sec < 3600) {
            return 'il y a ' . (int) round($sec / 60) . ' min';
        }

        return 'il y a ' . (int) round($sec / 3600) . ' h';
    }
}
