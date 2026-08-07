<?php

declare(strict_types=1);

namespace App\Services\Tactical;

/**
 * Roster des caméras casque / drone publiées depuis Arma (pas de flux RTMP).
 * Stockage fichier par tenant/carte — TTL sur last_seen.
 */
final class AtakVideoFeedsService
{
    /** Secondes après lesquelles un flux n’est plus considéré en ligne. */
    public const ONLINE_TTL_SEC = 90;

    /**
     * @return array{mapId: int, feeds: list<array<string, mixed>>, updated_at: string}
     */
    public function get(int $tenantId, int $mapId): array
    {
        $empty = [
            'mapId' => $mapId,
            'feeds' => [],
            'updated_at' => '',
        ];
        $path = $this->path($tenantId, $mapId);
        if (!is_file($path)) {
            return $empty;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return $empty;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return $empty;
        }
        $feeds = is_array($data['feeds'] ?? null) ? $data['feeds'] : [];
        $now = time();
        $alive = [];
        foreach ($feeds as $feed) {
            if (!is_array($feed)) {
                continue;
            }
            $id = trim((string) ($feed['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $seen = (int) ($feed['last_seen_at'] ?? 0);
            $online = $seen > 0 && ($now - $seen) <= self::ONLINE_TTL_SEC;
            $feed['online'] = $online;
            $feed['age_sec'] = $seen > 0 ? max(0, $now - $seen) : null;
            if ($online || ($now - $seen) <= (self::ONLINE_TTL_SEC * 3)) {
                $alive[] = $feed;
            }
        }
        usort($alive, static function (array $a, array $b): int {
            $ka = (string) ($a['kind'] ?? '');
            $kb = (string) ($b['kind'] ?? '');
            if ($ka !== $kb) {
                return $ka <=> $kb;
            }

            return strcmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        return [
            'mapId' => $mapId,
            'feeds' => $alive,
            'updated_at' => (string) ($data['updated_at'] ?? ''),
        ];
    }

    /**
     * Fusionne les flux publiés par un client (upsert par id).
     *
     * @param list<array<string, mixed>> $incoming
     * @return array{mapId: int, feeds: list<array<string, mixed>>, updated_at: string}
     */
    public function put(int $tenantId, int $mapId, array $incoming, string $reporter = ''): array
    {
        $current = $this->get($tenantId, $mapId);
        $byId = [];
        foreach ($current['feeds'] as $feed) {
            $id = trim((string) ($feed['id'] ?? ''));
            if ($id !== '') {
                $byId[$id] = $feed;
            }
        }
        $now = time();
        foreach ($incoming as $feed) {
            if (!is_array($feed)) {
                continue;
            }
            $id = trim((string) ($feed['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $kind = strtolower(trim((string) ($feed['kind'] ?? $feed['type'] ?? 'helmet')));
            if (!in_array($kind, ['helmet', 'drone', 'uav', 'vehicle'], true)) {
                $kind = 'helmet';
            }
            $label = trim((string) ($feed['label'] ?? ''));
            if ($label === '') {
                $label = $kind === 'drone' || $kind === 'uav'
                    ? 'Caméra drone'
                    : 'Caméra casque';
            }
            $byId[$id] = [
                'id' => $id,
                'kind' => $kind,
                'label' => $label,
                'callsign' => trim((string) ($feed['callsign'] ?? $feed['call_sign'] ?? '')),
                'steam_uid' => trim((string) ($feed['steam_uid'] ?? $feed['steamUid'] ?? '')),
                'pos_x' => self::finiteOrNull($feed['pos_x'] ?? $feed['x'] ?? null),
                'pos_y' => self::finiteOrNull($feed['pos_y'] ?? $feed['y'] ?? null),
                'pos_z' => self::finiteOrNull($feed['pos_z'] ?? null),
                'grid' => trim((string) ($feed['grid'] ?? $feed['grid_ref'] ?? '')),
                'reporter' => $reporter !== '' ? $reporter : trim((string) ($feed['reporter'] ?? '')),
                'streaming' => !empty($feed['streaming']) || !empty($feed['stream_active']),
                'last_seen_at' => $now,
                'online' => true,
                'age_sec' => 0,
            ];
        }
        $merged = [
            'mapId' => $mapId,
            'feeds' => array_values($byId),
            'updated_at' => gmdate('c'),
        ];
        $dir = dirname($this->path($tenantId, $mapId));
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents(
            $this->path($tenantId, $mapId),
            json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );

        return $this->get($tenantId, $mapId);
    }

    private function path(int $tenantId, int $mapId): string
    {
        return base_path('storage/cache/atak-video-feeds/t' . $tenantId . '_m' . $mapId . '.json');
    }

    private static function finiteOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        $n = (float) $value;

        return is_finite($n) ? $n : null;
    }
}
