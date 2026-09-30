<?php

declare(strict_types=1);

namespace App\Services\Tactical;

/**
 * Journal COMMS (métadonnées TX radio ACRE/TFAR) — Phase C.
 * Pas d’enregistrement vocal : fréquence, canal, durée, indicatif.
 */
final class AtakTelemetryCommsJournal
{
    private const MAX_EVENTS = 800;

    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? (dirname(__DIR__, 3) . '/storage/cache/atak-telemetry-comms');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function append(int $tenantId, int $mapId, array $data): int
    {
        if ($tenantId < 1 || $mapId < 1) {
            return 0;
        }
        $path = $this->path($tenantId, $mapId);
        $this->ensureDir();
        $lock = fopen($path . '.lock', 'c+');
        if ($lock === false) {
            return 0;
        }
        try {
            flock($lock, LOCK_EX);
            $state = $this->readState($path);
            $nextId = max(1, (int) ($state['next_id'] ?? 1));
            $events = is_array($state['events'] ?? null) ? $state['events'] : [];
            $events[] = [
                'id' => $nextId,
                'ts' => time(),
                'call_sign' => trim((string) ($data['call_sign'] ?? $data['cs'] ?? '')),
                'action' => strtolower(trim((string) ($data['action'] ?? $data['state'] ?? 'tx'))),
                'freq' => (string) ($data['freq'] ?? $data['radio_freq'] ?? ''),
                'channel' => (string) ($data['channel'] ?? $data['radio_channel'] ?? ''),
                'net' => (string) ($data['net'] ?? $data['radio_net'] ?? ''),
                'radio' => (string) ($data['radio'] ?? $data['radio_id'] ?? $data['model'] ?? ''),
                'duration_s' => isset($data['duration_s']) && is_numeric($data['duration_s'])
                    ? (float) $data['duration_s']
                    : null,
                'x' => isset($data['x']) && is_numeric($data['x']) ? (float) $data['x'] : null,
                'y' => isset($data['y']) && is_numeric($data['y']) ? (float) $data['y'] : null,
            ];
            if (count($events) > self::MAX_EVENTS) {
                $events = array_slice($events, -self::MAX_EVENTS);
            }
            $state = ['next_id' => $nextId + 1, 'events' => array_values($events)];
            file_put_contents(
                $path,
                (string) json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                LOCK_EX
            );

            return $nextId;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAfter(int $tenantId, int $mapId, int $afterId = 0, int $limit = 100): array
    {
        $limit = max(1, min(300, $limit));
        $afterId = max(0, $afterId);
        $state = $this->readState($this->path($tenantId, $mapId));
        $events = is_array($state['events'] ?? null) ? $state['events'] : [];
        $out = [];
        foreach ($events as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            if ((int) ($ev['id'] ?? 0) <= $afterId) {
                continue;
            }
            $out[] = $ev;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    public function latestId(int $tenantId, int $mapId): int
    {
        $state = $this->readState($this->path($tenantId, $mapId));

        return max(0, ((int) ($state['next_id'] ?? 1)) - 1);
    }

    private function path(int $tenantId, int $mapId): string
    {
        return $this->dir . '/t' . $tenantId . '_m' . $mapId . '.json';
    }

    private function ensureDir(): void
    {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
    }

    /**
     * @return array{next_id?:int, events?:list<array<string, mixed>>}
     */
    private function readState(string $path): array
    {
        if (!is_file($path)) {
            return ['next_id' => 1, 'events' => []];
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return ['next_id' => 1, 'events' => []];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : ['next_id' => 1, 'events' => []];
    }
}
