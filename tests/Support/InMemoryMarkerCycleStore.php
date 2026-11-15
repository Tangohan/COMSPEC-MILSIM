<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Atak\MarkerCycle\MarkerCycleStore;

/**
 * Dépôt du cycle des marqueurs en mémoire (aucune base) : mêmes règles que le dépôt MySQL pour ce que les tests vérifient
 * (cloisonnement par communauté et par carte, éléments épinglés ou masqués jamais proposés, un élément n'est jamais
 * archivé deux fois dans la même mission, bornes des listes, verrou).
 */
final class InMemoryMarkerCycleStore implements MarkerCycleStore
{
    public bool $ready = true;

    /** @var array<int, array<string, mixed>> */
    public array $settingsByTenant = [];

    /** @var array<int, string> */
    public array $timezones = [];

    /** @var list<array{tenant: int, map: int, kind: string, id: int, at: string, payload: array<string, mixed>}> */
    public array $live = [];

    /** @var array<int, array<string, mixed>> */
    public array $archives = [];

    /** @var array<int, list<array{kind: string, id: int, at: string, payload: array<string, mixed>}>> */
    public array $items = [];

    /** Simule une base qui refuse l'écriture de l'instantané. */
    public bool $failWrites = false;

    /** Simule un autre passage en cours. */
    public bool $lockHeld = false;

    public int $locks = 0;

    public int $unlocks = 0;

    /** Instant « maintenant » des éléments remis sur la carte (UTC). */
    public string $now = '2026-11-15 12:00:00';

    private int $nextArchive = 1;

    private int $nextLive = 1000;

    public function ready(): bool
    {
        return $this->ready;
    }

    public function settingsRow(int $tenantId): ?array
    {
        return $this->settingsByTenant[$tenantId] ?? null;
    }

    public function saveSettings(int $tenantId, array $settings, ?int $userId): void
    {
        $this->settingsByTenant[$tenantId] = $settings + ['updated_by' => $userId];
    }

    public function tenantTimezone(int $tenantId): ?string
    {
        return $this->timezones[$tenantId] ?? null;
    }

    /** Pose un marqueur affiché. @param array<string, mixed> $data marker_data décodé */
    public function addMarker(int $tenant, int $map, int $id, string $at, array $data = [], ?string $armaName = null): void
    {
        $this->live[] = [
            'tenant' => $tenant, 'map' => $map, 'kind' => 'marker', 'id' => $id, 'at' => $at,
            'payload' => ['id' => $id, 'layer_id' => 1, 'marker_data' => json_encode($data + ['text' => 'M' . $id, 'pos' => [100 + $id, 200]]), 'arma_name' => $armaName, 'updated_at' => $at],
        ];
    }

    /** @param array<string, mixed> $meta */
    public function addShape(int $tenant, int $map, int $id, string $at, array $meta = []): void
    {
        $this->live[] = [
            'tenant' => $tenant, 'map' => $map, 'kind' => 'shape', 'id' => $id, 'at' => $at,
            'payload' => ['id' => $id, 'shape_uid' => 's' . $id, 'type' => 'ROUTE', 'label' => 'T' . $id, 'color' => '#3388ff', 'geometry' => ['type' => 'LineString', 'coordinates' => [[0, 0], [10, 10]]], 'meta' => $meta],
        ];
    }

    /** @return list<string> « tenant:map:kind:id » des éléments affichés */
    public function liveKeys(): array
    {
        $out = array_map(static fn (array $i): string => $i['tenant'] . ':' . $i['map'] . ':' . $i['kind'] . ':' . $i['id'], $this->live);
        sort($out);

        return $out;
    }

    public function liveScopes(): array
    {
        $seen = [];
        foreach ($this->live as $i) {
            $seen[$i['tenant'] . ':' . $i['map']] = ['tenant' => $i['tenant'], 'map' => $i['map']];
        }
        ksort($seen);

        return array_values($seen);
    }

    /** @param array<string, mixed> $payload */
    private static function isPinned(string $kind, array $payload): bool
    {
        if ($kind === 'marker') {
            $d = json_decode((string) ($payload['marker_data'] ?? ''), true);

            return is_array($d) && !empty($d['web_permanent']);
        }

        return is_array($payload['meta'] ?? null) && !empty($payload['meta']['web_permanent']);
    }

    private static function isSuppressed(array $payload): bool
    {
        $d = json_decode((string) ($payload['marker_data'] ?? ''), true);

        return is_array($d) && !empty($d['suppressed']);
    }

    public function staleItems(int $tenantId, int $mapId, string $beforeUtc, int $limit): array
    {
        $out = [];
        foreach ($this->live as $i) {
            if ($i['tenant'] !== $tenantId || $i['map'] !== $mapId || $i['at'] >= $beforeUtc) {
                continue;
            }
            if (self::isPinned($i['kind'], $i['payload']) || ($i['kind'] === 'marker' && self::isSuppressed($i['payload']))) {
                continue;
            }
            $out[] = ['kind' => $i['kind'], 'id' => $i['id'], 'at' => $i['at'], 'payload' => $i['payload']];
        }
        usort($out, static fn (array $a, array $b): int => [$a['at'], $a['kind'], $a['id']] <=> [$b['at'], $b['kind'], $b['id']]);

        return array_slice($out, 0, max(1, $limit));
    }

    public function writeArchive(int $tenantId, int $mapId, array $window, string $source, ?int $userId, array $items): int
    {
        if ($this->failWrites) {
            throw new \RuntimeException('écriture refusée');
        }
        $id = null;
        foreach ($this->archives as $a) {
            if ($a['tenant_id'] === $tenantId && $a['map_id'] === $mapId && $a['cycle_key'] === $window['key']) {
                $id = $a['id'];
            }
        }
        if ($id === null) {
            $id = $this->nextArchive++;
            $this->archives[$id] = [
                'id' => $id, 'tenant_id' => $tenantId, 'map_id' => $mapId, 'cycle_key' => $window['key'], 'label' => $window['label'],
                'starts_at' => $window['start_utc'], 'ends_at' => $window['end_utc'], 'markers_count' => 0, 'shapes_count' => 0,
                'source' => $source, 'created_by' => $userId, 'created_at' => $this->now,
            ];
            $this->items[$id] = [];
        } else {
            $this->archives[$id]['starts_at'] = min($this->archives[$id]['starts_at'], $window['start_utc']);
            $this->archives[$id]['ends_at'] = max($this->archives[$id]['ends_at'], $window['end_utc']);
        }
        foreach ($items as $item) {
            $key = $item['kind'] . ':' . $item['id'];
            foreach ($this->items[$id] as $have) {
                if ($have['kind'] . ':' . $have['id'] === $key) {
                    continue 2;
                }
            }
            $this->items[$id][] = $item;
        }
        $m = $s = 0;
        foreach ($this->items[$id] as $it) {
            $it['kind'] === 'marker' ? $m++ : $s++;
        }
        $this->archives[$id]['markers_count'] = $m;
        $this->archives[$id]['shapes_count'] = $s;

        return $id;
    }

    public function deleteLive(int $tenantId, int $mapId, array $refs): int
    {
        $n = 0;
        foreach ($refs as $ref) {
            foreach ($this->live as $k => $i) {
                if ($i['tenant'] === $tenantId && $i['map'] === $mapId && $i['kind'] === $ref['kind'] && $i['id'] === $ref['id'] && !self::isPinned($i['kind'], $i['payload'])) {
                    unset($this->live[$k]);
                    $n++;
                }
            }
        }
        $this->live = array_values($this->live);

        return $n;
    }

    public function insertLive(int $tenantId, int $mapId, string $kind, array $payload): ?int
    {
        $id = $this->nextLive++;
        $payload['id'] = $id;
        $this->live[] = ['tenant' => $tenantId, 'map' => $mapId, 'kind' => $kind, 'id' => $id, 'at' => $this->now, 'payload' => $payload];

        return $id;
    }

    public function setPinned(int $tenantId, string $kind, int $id, bool $pinned): bool
    {
        foreach ($this->live as $k => $i) {
            if ($i['tenant'] !== $tenantId || $i['kind'] !== $kind || $i['id'] !== $id) {
                continue;
            }
            if ($kind === 'marker') {
                $d = json_decode((string) $i['payload']['marker_data'], true) ?: [];
                $pinned ? $d['web_permanent'] = true : null;
                if (!$pinned) {
                    unset($d['web_permanent']);
                }
                $this->live[$k]['payload']['marker_data'] = json_encode($d);
            } else {
                $m = $i['payload']['meta'] ?? [];
                $pinned ? $m['web_permanent'] = true : null;
                if (!$pinned) {
                    unset($m['web_permanent']);
                }
                $this->live[$k]['payload']['meta'] = $m;
            }

            return true;
        }

        return false;
    }

    public function listArchives(int $tenantId, array $filter, int $limit, int $offset): array
    {
        $rows = array_values(array_filter($this->archives, static function (array $a) use ($tenantId, $filter): bool {
            return $a['tenant_id'] === $tenantId
                && (empty($filter['map']) || $a['map_id'] === (int) $filter['map'])
                && (empty($filter['from']) || $a['ends_at'] >= $filter['from'])
                && (empty($filter['to']) || $a['starts_at'] <= $filter['to'])
                && (empty($filter['q']) || str_contains($a['label'], (string) $filter['q']));
        }));
        usort($rows, static fn (array $a, array $b): int => [$b['ends_at'], $b['id']] <=> [$a['ends_at'], $a['id']]);

        return array_slice($rows, $offset, $limit);
    }

    public function getArchive(int $tenantId, int $archiveId): ?array
    {
        $a = $this->archives[$archiveId] ?? null;

        return $a !== null && $a['tenant_id'] === $tenantId ? $a : null;
    }

    public function archiveItems(int $tenantId, int $archiveId, int $limit, int $offset): array
    {
        return $this->getArchive($tenantId, $archiveId) === null ? [] : array_slice($this->items[$archiveId] ?? [], $offset, $limit);
    }

    public function deleteArchive(int $tenantId, int $archiveId): bool
    {
        if ($this->getArchive($tenantId, $archiveId) === null) {
            return false;
        }
        unset($this->archives[$archiveId], $this->items[$archiveId]);

        return true;
    }

    public function purgeArchivesEndedBefore(int $tenantId, string $beforeUtc, int $limit): int
    {
        $n = 0;
        foreach ($this->archives as $id => $a) {
            if ($n < $limit && $a['tenant_id'] === $tenantId && $a['ends_at'] < $beforeUtc) {
                unset($this->archives[$id], $this->items[$id]);
                $n++;
            }
        }

        return $n;
    }

    public function tenantsWithArchives(): array
    {
        return array_values(array_unique(array_map(static fn (array $a): int => (int) $a['tenant_id'], $this->archives)));
    }

    public function lock(string $name): bool
    {
        if ($this->lockHeld) {
            return false;
        }
        $this->locks++;
        $this->lockHeld = true;

        return true;
    }

    public function unlock(string $name): void
    {
        $this->unlocks++;
        $this->lockHeld = false;
    }
}
