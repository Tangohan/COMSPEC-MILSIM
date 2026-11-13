<?php

declare(strict_types=1);

namespace App\Services\Atak;

use App\Repositories\AtakDataRepository;
use App\Repositories\MapShapeRepository;
use App\Services\Tactical\AtakActivityLogService;

/**
 * Effacer la carte : suppression en masse des marqueurs, formes, routes et zones d'une carte (communauté + carte),
 * avec portée (tout / les miens), types, corbeille de quelques minutes et journal.
 *
 * Ne touche que atak_markers et atak_map_shapes de (communauté, carte) : jamais ORBAT, comptes, documents, positions.
 * Un marqueur du jeu est seulement masqué (suppressed) : le relevé « game_del » le retire de la carte native en jeu,
 * comme la suppression à l'unité. La corbeille est un instantané JSON (storage/cache/atak-map-trash), 10 minutes.
 */
final class AtakMapClearService
{
    public const CONFIRM_PHRASE = 'SUPPRIMER';
    public const TYPES = ['marker', 'form', 'route', 'zone'];
    public const SCOPE_ALL = 'all';
    public const SCOPE_MINE = 'mine';
    public const TRASH_TTL = 600;

    /** @var callable|null fn(string $action, int $tenantId, ?int $userId, int $mapId, array $details): void */
    private $audit;

    public function __construct(
        private AtakDataRepository $atak,
        private MapShapeRepository $shapes,
        private ?AtakActivityLogService $activityLog = null,
        private ?string $trashDir = null,
        ?callable $audit = null,
    ) {
        $this->audit = $audit;
    }

    /**
     * @param list<mixed> $types
     * @return list<string> types valides, dans l'ordre du catalogue ; tous si vide
     */
    public static function normalizeTypes(array $types): array
    {
        $want = [];
        foreach ($types as $t) {
            if (is_string($t) && in_array(strtolower($t), self::TYPES, true)) {
                $want[strtolower($t)] = true;
            }
        }

        return $want === [] ? self::TYPES : array_values(array_filter(self::TYPES, static fn (string $t): bool => isset($want[$t])));
    }

    /** Droits qui ouvrent « tout effacer » (pour le message d'aide). */
    public const MANAGE_RIGHTS = ['admin.system', 'admin.organization', 'admin.access', 'atak.mission_cycle.manage'];

    /**
     * @param string|null $role "platform" / "manager" (AtakGameAdminService::role) ou null
     * @param bool $missionManage droit atak.mission_cycle.manage dans la communauté
     */
    public static function managerFromRole(?string $role, bool $missionManage): bool
    {
        return $role === 'platform' || $role === 'manager' || $missionManage;
    }

    /** @param array<string, mixed> $shape ligne de MapShapeRepository::list() */
    public static function shapeKind(array $shape): string
    {
        $geometry = is_array($shape['geometry'] ?? null) ? $shape['geometry'] : [];
        $gt = strtolower((string) ($geometry['type'] ?? ''));
        $type = strtoupper((string) ($shape['type'] ?? ''));
        if ($gt === 'polygon' || $gt === 'multipolygon' || in_array($type, ['ZONE', 'POLYGON', 'AO', 'DANGER', 'EXCLUSION', 'AREA', 'CIRCLE', 'ELLIPSE', 'RECTANGLE', 'SECTOR'], true)) {
            return 'zone';
        }
        if ($gt === 'linestring' || $gt === 'multilinestring' || in_array($type, ['ROUTE', 'LINE', 'POLYLINE', 'MSR', 'ASR', 'AXIS'], true)) {
            return 'route';
        }

        return 'form';
    }

    /**
     * @param array{userId:int, aliases:list<string>, canManage:bool} $actor
     * @param array<string, mixed> $data marker_data décodé
     */
    public static function markerIsMine(array $actor, array $data): bool
    {
        $uid = $actor['userId'];
        if ($uid > 0 && ((int) ($data['owner_user_id'] ?? 0) === $uid || (int) ($data['mobile_user'] ?? 0) === $uid)) {
            return true;
        }

        return self::aliasMatches($actor['aliases'], (string) ($data['author'] ?? ''));
    }

    /**
     * @param array{userId:int, aliases:list<string>, canManage:bool} $actor
     * @param array<string, mixed> $shape
     */
    public static function shapeIsMine(array $actor, array $shape): bool
    {
        $uid = $actor['userId'];
        $meta = is_array($shape['meta'] ?? null) ? $shape['meta'] : [];
        if ($uid > 0 && (int) ($meta['owner_user_id'] ?? 0) === $uid) {
            return true;
        }

        return self::aliasMatches($actor['aliases'], (string) ($shape['created_by'] ?? ''));
    }

    /** @param list<string> $aliases */
    private static function aliasMatches(array $aliases, string $author): bool
    {
        $author = trim($author);
        if ($author === '') {
            return false;
        }
        foreach ($aliases as $a) {
            if (trim($a) !== '' && strcasecmp(trim($a), $author) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ce qui est sur la carte, par type, au total et pour moi.
     *
     * @param array{userId:int, aliases:list<string>, canManage:bool} $actor
     * @return array{counts: array<string, int>, mine: array<string, int>, total: int, totalMine: int, canAll: bool, needs: list<string>}
     */
    public function preview(int $tenantId, int $mapId, array $actor): array
    {
        $counts = array_fill_keys(self::TYPES, 0);
        $mine = $counts;
        foreach ($this->collect($tenantId, $mapId) as $item) {
            $counts[$item['kind']]++;
            if ($item['mine']($actor)) {
                $mine[$item['kind']]++;
            }
        }

        return [
            'counts' => $counts,
            'mine' => $mine,
            'total' => array_sum($counts),
            'totalMine' => array_sum($mine),
            'canAll' => $actor['canManage'],
            'needs' => $actor['canManage'] ? [] : self::MANAGE_RIGHTS,
        ];
    }

    /**
     * @param array{userId:int, aliases:list<string>, canManage:bool} $actor
     * @param list<string> $types
     * @return array{ok:bool, error?:string, batch?:string, counts?:array<string,int>, total?:int, undoUntil?:int}
     */
    public function clear(int $tenantId, int $mapId, array $actor, string $scope, array $types, string $confirm): array
    {
        if ($scope !== self::SCOPE_ALL && $scope !== self::SCOPE_MINE) {
            return ['ok' => false, 'error' => 'scope'];
        }
        if ($scope === self::SCOPE_ALL && !$actor['canManage']) {
            return ['ok' => false, 'error' => 'forbidden'];
        }
        if (strcasecmp(trim($confirm), self::CONFIRM_PHRASE) !== 0) {
            return ['ok' => false, 'error' => 'confirm_required'];
        }
        $types = self::normalizeTypes($types);
        $counts = array_fill_keys(self::TYPES, 0);
        $snapshot = ['markers' => [], 'shapes' => []];
        foreach ($this->collect($tenantId, $mapId) as $item) {
            if (!in_array($item['kind'], $types, true)) {
                continue;
            }
            if ($scope === self::SCOPE_MINE && !$item['mine']($actor)) {
                continue;
            }
            if ($item['table'] === 'marker') {
                $row = $item['row'];
                if ($this->atak->deleteMarker($tenantId, (int) $row['id'])) {
                    $snapshot['markers'][] = $row;
                    $counts[$item['kind']]++;
                }
            } elseif ($this->shapes->delete($tenantId, (int) $item['row']['id'])) {
                $snapshot['shapes'][] = $item['row'];
                $counts[$item['kind']]++;
            }
        }
        $total = array_sum($counts);
        $batch = bin2hex(random_bytes(8));
        if ($total > 0) {
            $this->writeTrash($tenantId, $mapId, $batch, $actor['userId'], $snapshot);
        }
        $this->record($tenantId, $mapId, $actor, 'atak.map_clear', [
            'scope' => $scope, 'types' => $types, 'counts' => $counts, 'total' => $total, 'batch' => $total > 0 ? $batch : '',
        ]);

        return ['ok' => true, 'batch' => $total > 0 ? $batch : '', 'counts' => $counts, 'total' => $total, 'undoUntil' => time() + self::TRASH_TTL];
    }

    /**
     * Annule un effacement récent. Seul son auteur (ou la gestion de la carte) peut le faire.
     *
     * @param array{userId:int, aliases:list<string>, canManage:bool} $actor
     * @return array{ok:bool, error?:string, restored?:int}
     */
    public function undo(int $tenantId, int $mapId, array $actor, string $batch): array
    {
        $trash = $this->readTrash($tenantId, $mapId, $batch);
        if ($trash === null) {
            return ['ok' => false, 'error' => 'expired'];
        }
        if ($trash['user'] !== $actor['userId'] && !$actor['canManage']) {
            return ['ok' => false, 'error' => 'forbidden'];
        }
        $restored = 0;
        foreach ($trash['markers'] as $row) {
            if ($this->atak->restoreMarkerRow($tenantId, $mapId, $row)) {
                $restored++;
            }
        }
        foreach ($trash['shapes'] as $row) {
            if ($this->shapes->restoreRow($tenantId, $mapId, $row)) {
                $restored++;
            }
        }
        $this->dropTrash($tenantId, $mapId, $batch);
        $this->record($tenantId, $mapId, $actor, 'atak.map_clear_undo', ['batch' => $batch, 'restored' => $restored]);

        return ['ok' => true, 'restored' => $restored];
    }

    /**
     * @return list<array{table:string, kind:string, row:array<string,mixed>, mine:callable}>
     */
    private function collect(int $tenantId, int $mapId): array
    {
        $out = [];
        foreach ($this->atak->listLiveMarkerRows($tenantId, $mapId) as $row) {
            $data = json_decode($row['marker_data'], true);
            $data = is_array($data) ? $data : [];
            $out[] = [
                'table' => 'marker',
                'kind' => 'marker',
                'row' => $row,
                'mine' => static fn (array $actor): bool => self::markerIsMine($actor, $data),
            ];
        }
        foreach ($this->shapes->list($tenantId, $mapId) as $shape) {
            $out[] = [
                'table' => 'shape',
                'kind' => self::shapeKind($shape),
                'row' => $shape,
                'mine' => static fn (array $actor): bool => self::shapeIsMine($actor, $shape),
            ];
        }

        return $out;
    }

    /**
     * @param array{userId:int, aliases:list<string>, canManage:bool} $actor
     * @param array<string, mixed> $details
     */
    private function record(int $tenantId, int $mapId, array $actor, string $action, array $details): void
    {
        $label = $actor['aliases'][0] ?? '';
        try {
            if ($this->activityLog !== null) {
                $summary = $action === 'atak.map_clear'
                    ? sprintf('Carte effacée — %d élément(s), portée %s', (int) $details['total'], $details['scope'] === self::SCOPE_ALL ? 'tout' : 'les siens')
                    : sprintf('Effacement de la carte annulé — %d élément(s) remis', (int) $details['restored']);
                $this->activityLog->record($tenantId, $mapId, AtakActivityLogService::TYPE_MARKER, $summary, $label !== '' ? $label : null, $details);
            }
        } catch (\Throwable) {
            // Le journal ne bloque jamais l'action.
        }
        try {
            $details['map_id'] = $mapId;
            if ($this->audit !== null) {
                ($this->audit)($action, $tenantId, $actor['userId'] > 0 ? $actor['userId'] : null, $mapId, $details);
            } else {
                (new \App\Services\Audit\AuditService())->logChange(
                    $action,
                    $tenantId,
                    $actor['userId'] > 0 ? $actor['userId'] : null,
                    'atak_map',
                    $mapId,
                    [],
                    $details,
                );
            }
        } catch (\Throwable $e) {
            error_log('[audit] ' . $action . ' : ' . $e->getMessage());
        }
    }

    private function dir(): string
    {
        return rtrim($this->trashDir ?? base_path('storage/cache/atak-map-trash'), '/\\');
    }

    private function file(int $tenantId, int $mapId, string $batch): ?string
    {
        if (!preg_match('/^[0-9a-f]{16}$/', $batch)) {
            return null;
        }

        return $this->dir() . '/t' . $tenantId . '_m' . $mapId . '_' . $batch . '.json';
    }

    /** @param array{markers:list<array<string,mixed>>, shapes:list<array<string,mixed>>} $snapshot */
    private function writeTrash(int $tenantId, int $mapId, string $batch, int $userId, array $snapshot): void
    {
        $dir = $this->dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        foreach (glob($dir . '/*.json') ?: [] as $old) {
            if (is_file($old) && filemtime($old) < time() - self::TRASH_TTL) {
                @unlink($old);
            }
        }
        $file = $this->file($tenantId, $mapId, $batch);
        if ($file !== null) {
            @file_put_contents($file, json_encode(['user' => $userId, 'at' => time()] + $snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    /** @return array{user:int, markers:list<array<string,mixed>>, shapes:list<array<string,mixed>>}|null */
    private function readTrash(int $tenantId, int $mapId, string $batch): ?array
    {
        $file = $this->file($tenantId, $mapId, $batch);
        if ($file === null || !is_file($file)) {
            return null;
        }
        $raw = json_decode((string) @file_get_contents($file), true);
        if (!is_array($raw) || (int) ($raw['at'] ?? 0) < time() - self::TRASH_TTL) {
            @unlink($file);

            return null;
        }

        return [
            'user' => (int) ($raw['user'] ?? 0),
            'markers' => is_array($raw['markers'] ?? null) ? $raw['markers'] : [],
            'shapes' => is_array($raw['shapes'] ?? null) ? $raw['shapes'] : [],
        ];
    }

    private function dropTrash(int $tenantId, int $mapId, string $batch): void
    {
        $file = $this->file($tenantId, $mapId, $batch);
        if ($file !== null) {
            @unlink($file);
        }
    }
}
