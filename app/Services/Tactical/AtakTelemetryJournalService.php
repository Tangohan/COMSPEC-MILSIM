<?php

declare(strict_types=1);

namespace App\Services\Tactical;

/**
 * Journal append-only des lots de télémétrie jeu → Athena.
 * Lecture incrémentale via after_id (curseur monotone).
 */
final class AtakTelemetryJournalService
{
    private const MAX_EVENTS = 4000;
    private const MAX_FILE_BYTES = 4_000_000;

    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? (dirname(__DIR__, 3) . '/storage/cache/atak-telemetry');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function append(int $tenantId, int $mapId, string $type, array $data = []): int
    {
        if ($tenantId < 1 || $mapId < 1) {
            return 0;
        }
        $type = strtolower(trim($type));
        if ($type === '') {
            $type = 'unknown';
        }

        $path = $this->path($tenantId, $mapId);
        $this->ensureDir();
        $lock = fopen($path . '.lock', 'c+');
        if ($lock === false) {
            return 0;
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                return 0;
            }
            $state = $this->readState($path);
            $nextId = ((int) ($state['next_id'] ?? 1));
            if ($nextId < 1) {
                $nextId = 1;
            }
            $events = is_array($state['events'] ?? null) ? $state['events'] : [];
            $events[] = [
                'id' => $nextId,
                'ts' => time(),
                'type' => $type,
                'data' => $data,
            ];
            if (count($events) > self::MAX_EVENTS) {
                $events = array_slice($events, -self::MAX_EVENTS);
            }
            $state = [
                'next_id' => $nextId + 1,
                'events' => array_values($events),
            ];
            $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($json) || $json === '') {
                return 0;
            }
            if (strlen($json) > self::MAX_FILE_BYTES) {
                $trim = (int) max(500, (int) floor(count($events) * 0.6));
                $state['events'] = array_slice($events, -$trim);
                $json = (string) json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            file_put_contents($path, $json, LOCK_EX);

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
        if ($tenantId < 1 || $mapId < 1) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $afterId = max(0, $afterId);
        $state = $this->readState($this->path($tenantId, $mapId));
        $events = is_array($state['events'] ?? null) ? $state['events'] : [];
        $out = [];
        foreach ($events as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            $id = (int) ($ev['id'] ?? 0);
            if ($id <= $afterId) {
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
        $next = (int) ($state['next_id'] ?? 1);

        return max(0, $next - 1);
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
        if (!is_array($decoded)) {
            return ['next_id' => 1, 'events' => []];
        }

        return $decoded;
    }
}
