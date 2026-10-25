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
        $ids = $this->appendMany($tenantId, $mapId, [['type' => $type, 'data' => $data]]);

        return $ids[0] ?? 0;
    }

    /**
     * Ajoute plusieurs entrées sous un seul verrou : une lecture et une écriture du fichier par lot
     * (au lieu d’un cycle complet par événement).
     *
     * @param list<array{type: string, data?: array<string, mixed>}> $entries
     * @return list<int> identifiants attribués, dans l’ordre des entrées (0 si échec)
     */
    public function appendMany(int $tenantId, int $mapId, array $entries): array
    {
        $fail = array_fill(0, count($entries), 0);
        if ($tenantId < 1 || $mapId < 1 || $entries === []) {
            return $fail;
        }

        $path = $this->path($tenantId, $mapId);
        $this->ensureDir();
        $lock = fopen($path . '.lock', 'c+');
        if ($lock === false) {
            return $fail;
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                return $fail;
            }
            $state = $this->readState($path);
            $nextId = ((int) ($state['next_id'] ?? 1));
            if ($nextId < 1) {
                $nextId = 1;
            }
            $events = is_array($state['events'] ?? null) ? $state['events'] : [];
            $now = time();
            $ids = [];
            foreach ($entries as $entry) {
                $type = strtolower(trim((string) ($entry['type'] ?? '')));
                if ($type === '') {
                    $type = 'unknown';
                }
                $data = is_array($entry['data'] ?? null) ? $entry['data'] : [];
                $events[] = [
                    'id' => $nextId,
                    'ts' => $now,
                    'type' => $type,
                    'data' => $data,
                ];
                $ids[] = $nextId;
                $nextId++;
            }
            if (count($events) > self::MAX_EVENTS) {
                $events = array_slice($events, -self::MAX_EVENTS);
            }
            $state = [
                'next_id' => $nextId,
                'events' => array_values($events),
            ];
            $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($json) || $json === '') {
                return $fail;
            }
            if (strlen($json) > self::MAX_FILE_BYTES) {
                $trim = (int) max(500, (int) floor(count($events) * 0.6));
                $state['events'] = array_slice($events, -$trim);
                $json = (string) json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            file_put_contents($path, $json, LOCK_EX);

            return $ids;
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
