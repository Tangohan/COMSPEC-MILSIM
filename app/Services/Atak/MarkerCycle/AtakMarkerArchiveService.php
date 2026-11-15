<?php

declare(strict_types=1);

namespace App\Services\Atak\MarkerCycle;

/**
 * Archivage des missions : à chaque bascule du cycle, les marqueurs et tracés du cycle écoulé sont écrits dans une mission
 * archivée (instantané), puis retirés de la carte. Éphémère : marqueurs de carte et tracés. Durable (jamais touché) :
 * éléments épinglés, plans d'opération du poste TOC, lieux et calques SSE, ordre de bataille, comptes, positions.
 *
 * Garanties :
 *  - rien n'est retiré de la carte avant que l'instantané soit écrit (writeArchive en une transaction) ; si l'écriture
 *    échoue, aucune suppression et la communauté est laissée pour le passage suivant ;
 *  - rejouable : un élément archivé n'est jamais écrit deux fois (clé mission + type + identifiant), un passage interrompu
 *    entre l'écriture et le retrait est simplement repris ;
 *  - rattrapage prudent : au plus RUN_BUDGET éléments par passage, par lots de BATCH ; l'existant de plus de
 *    LEGACY_AFTER_DAYS jours est regroupé dans une seule mission « Missions antérieures au … » au lieu d'une par jour ;
 *  - un verrou empêche deux passages simultanés ; chaque communauté est traitée séparément (une erreur n'arrête pas les autres) ;
 *  - cycle désactivé pour une communauté = rien ne bouge (affichage cumulatif d'avant).
 */
final class AtakMarkerArchiveService
{
    public const BATCH = 500;
    public const RUN_BUDGET = 2000;
    public const LEGACY_AFTER_DAYS = 30;
    public const PURGE_LIMIT = 50;
    public const LOCK = 'atak_marker_cycle';
    public const MAX_RESTORE = 5000;
    public const MAX_VIEW = 3000;

    private AtakMarkerCycleService $cycles;

    /** @var callable(string, string): void */
    private $log;

    /**
     * @param callable(): int|null $clock
     * @param callable(string, string): void|null $log journal : niveau (info|error), message
     */
    public function __construct(private MarkerCycleStore $store, ?callable $clock = null, ?callable $log = null, ?AtakMarkerCycleService $cycles = null)
    {
        $this->cycles = $cycles ?? new AtakMarkerCycleService($store, $clock);
        $this->log = $log ?? static function (string $level, string $message): void {
            error_log('[atak_marker_cycle ' . $level . '] ' . $message);
        };
    }

    public function cycles(): AtakMarkerCycleService
    {
        return $this->cycles;
    }

    /**
     * Un passage de la tâche planifiée.
     *
     * @param array{tenant?: int, dry_run?: bool, budget?: int} $options
     * @return array{ok: bool, skipped: bool, scopes: int, archived: int, removed: int, purged: int, errors: int, missions: int}
     */
    public function runScheduled(array $options = []): array
    {
        $out = ['ok' => true, 'skipped' => false, 'scopes' => 0, 'archived' => 0, 'removed' => 0, 'purged' => 0, 'errors' => 0, 'missions' => 0];
        if (!$this->store->ready()) {
            $out['skipped'] = true;

            return $out;
        }
        if (!$this->store->lock(self::LOCK)) {
            $out['skipped'] = true;
            $this->log('info', 'passage déjà en cours, ignoré');

            return $out;
        }
        try {
            $only = (int) ($options['tenant'] ?? 0);
            $dry = !empty($options['dry_run']);
            $budget = max(1, (int) ($options['budget'] ?? self::RUN_BUDGET));
            $tenants = [];
            foreach ($this->store->liveScopes() as $scope) {
                if ($only > 0 && $scope['tenant'] !== $only) {
                    continue;
                }
                if ($budget <= 0) {
                    break;
                }
                $tenants[$scope['tenant']] = true;
                $out['scopes']++;
                try {
                    $r = $this->archiveScope($scope['tenant'], $scope['map'], $budget, $dry);
                    $budget -= $r['seen'];
                    $out['archived'] += $r['archived'];
                    $out['removed'] += $r['removed'];
                    $out['missions'] += $r['missions'];
                    $out['errors'] += $r['errors'];
                } catch (\Throwable $e) {
                    $out['errors']++;
                    $this->log('error', 'communauté ' . $scope['tenant'] . ' carte ' . $scope['map'] . ' : ' . $e->getMessage());
                }
            }
            if (!$dry) {
                foreach (array_unique(array_merge(array_keys($tenants), $this->store->tenantsWithArchives())) as $tenantId) {
                    if ($only > 0 && $tenantId !== $only) {
                        continue;
                    }
                    try {
                        $out['purged'] += $this->purgeExpired((int) $tenantId);
                    } catch (\Throwable $e) {
                        $out['errors']++;
                        $this->log('error', 'rétention communauté ' . $tenantId . ' : ' . $e->getMessage());
                    }
                }
            }
        } finally {
            $this->store->unlock(self::LOCK);
        }
        $out['ok'] = $out['errors'] === 0;
        if ($out['archived'] > 0 || $out['purged'] > 0 || $out['errors'] > 0) {
            $this->log($out['ok'] ? 'info' : 'error', sprintf(
                '%d élément(s) archivé(s) dans %d mission(s), %d retiré(s) de la carte, %d mission(s) purgée(s), %d erreur(s)',
                $out['archived'],
                $out['missions'],
                $out['removed'],
                $out['purged'],
                $out['errors']
            ));
        }

        return $out;
    }

    /**
     * Archive puis retire les éléments d'une carte qui ne sont plus du cycle en cours.
     *
     * @return array{seen: int, archived: int, removed: int, missions: int, errors: int}
     */
    public function archiveScope(int $tenantId, int $mapId, int $budget, bool $dry = false): array
    {
        $r = ['seen' => 0, 'archived' => 0, 'removed' => 0, 'missions' => 0, 'errors' => 0];
        $settings = $this->cycles->settings($tenantId);
        if (!$settings['enabled'] || $tenantId < 1) {
            return $r;
        }
        $now = $this->cycles->now();
        $current = AtakMarkerCycleService::windowAt($settings, $now);
        $horizon = $current['start'] - self::LEGACY_AFTER_DAYS * 86400;
        $touched = [];
        while ($budget - $r['seen'] > 0) {
            $items = $this->store->staleItems($tenantId, $mapId, $current['start_utc'], min(self::BATCH, $budget - $r['seen']));
            if ($items === []) {
                break;
            }
            $r['seen'] += count($items);
            if ($dry) {
                $r['archived'] += count($items);
                break;
            }
            $removedInBatch = 0;
            foreach ($this->group($items, $settings, $horizon) as $group) {
                try {
                    $archiveId = $this->store->writeArchive($tenantId, $mapId, $group['window'], $group['source'], null, $group['items']);
                } catch (\Throwable $e) {
                    // L'instantané n'est pas écrit : on ne retire RIEN, la carte reste telle quelle.
                    $r['errors']++;
                    $this->log('error', 'archivage impossible (communauté ' . $tenantId . ', carte ' . $mapId . ', ' . $group['window']['key'] . ') : ' . $e->getMessage());

                    return $r;
                }
                $touched[$archiveId] = true;
                $r['archived'] += count($group['items']);
                $refs = array_map(static fn (array $i): array => ['kind' => $i['kind'], 'id' => $i['id']], $group['items']);
                $removedInBatch += $this->store->deleteLive($tenantId, $mapId, $refs);
            }
            $r['removed'] += $removedInBatch;
            if ($removedInBatch === 0) {
                break;
            }
        }
        $r['missions'] = count($touched);

        return $r;
    }

    /**
     * @param list<array{kind: string, id: int, at: string, payload: array<string, mixed>}> $items
     * @param array{enabled: bool, switch_time: string, duration_days: int, timezone: string, retention_days: int} $settings
     * @return list<array{window: array{key: string, label: string, start_utc: string, end_utc: string}, source: string, items: list<array{kind: string, id: int, at: string, payload: array<string, mixed>}>}>
     */
    private function group(array $items, array $settings, int $horizon): array
    {
        $groups = [];
        foreach ($items as $item) {
            $ts = (int) strtotime($item['at'] . ' UTC');
            if ($ts < $horizon) {
                $key = 'legacy';
                $local = (new \DateTimeImmutable('@' . $horizon))->setTimezone(new \DateTimeZone($settings['timezone']));
                $window = [
                    'key' => 'legacy',
                    'label' => 'Missions antérieures au ' . $local->format('d/m/Y'),
                    'start_utc' => $item['at'],
                    'end_utc' => gmdate('Y-m-d H:i:s', $horizon),
                ];
                $source = 'legacy';
            } else {
                $w = AtakMarkerCycleService::windowAt($settings, $ts);
                $key = $w['key'];
                $window = ['key' => $w['key'], 'label' => $w['label'], 'start_utc' => $w['start_utc'], 'end_utc' => $w['end_utc']];
                $source = 'auto';
            }
            if (!isset($groups[$key])) {
                $groups[$key] = ['window' => $window, 'source' => $source, 'items' => []];
            } elseif ($key === 'legacy' && $item['at'] < $groups[$key]['window']['start_utc']) {
                $groups[$key]['window']['start_utc'] = $item['at'];
            }
            $groups[$key]['items'][] = $item;
        }

        return array_values($groups);
    }

    /** Supprime les missions archivées plus vieilles que la rétention de la communauté (0 = jamais). */
    public function purgeExpired(int $tenantId): int
    {
        $s = $this->cycles->settings($tenantId);
        if ($s['retention_days'] <= 0) {
            return 0;
        }
        $before = gmdate('Y-m-d H:i:s', $this->cycles->now() - $s['retention_days'] * 86400);

        return $this->store->purgeArchivesEndedBefore($tenantId, $before, self::PURGE_LIMIT);
    }

    /**
     * @param array{map?: int, from?: string, to?: string, q?: string} $filter
     * @return list<array<string, mixed>>
     */
    public function listArchives(int $tenantId, array $filter = [], int $limit = 60, int $offset = 0): array
    {
        return $this->store->listArchives($tenantId, $filter, max(1, min(200, $limit)), max(0, $offset));
    }

    /**
     * Mission archivée en lecture seule (aucune écriture, aucune action sur la carte).
     *
     * @return array{archive: array<string, mixed>, items: list<array{kind: string, id: int, at: string, payload: array<string, mixed>}>, truncated: bool}|null
     */
    public function view(int $tenantId, int $archiveId, int $limit = self::MAX_VIEW): ?array
    {
        $archive = $this->store->getArchive($tenantId, $archiveId);
        if ($archive === null) {
            return null;
        }
        $limit = max(1, min(self::MAX_VIEW * 2, $limit));
        $items = $this->store->archiveItems($tenantId, $archiveId, $limit + 1, 0);
        $truncated = count($items) > $limit;

        return ['archive' => $archive, 'items' => array_slice($items, 0, $limit), 'truncated' => $truncated];
    }

    /**
     * Fichier d'export complet (JSON) d'une mission archivée.
     *
     * @return array<string, mixed>|null
     */
    public function export(int $tenantId, int $archiveId): ?array
    {
        $archive = $this->store->getArchive($tenantId, $archiveId);
        if ($archive === null) {
            return null;
        }
        $items = [];
        $offset = 0;
        while (true) {
            $page = $this->store->archiveItems($tenantId, $archiveId, 1000, $offset);
            foreach ($page as $it) {
                $items[] = $it;
            }
            if (count($page) < 1000 || count($items) >= 100000) {
                break;
            }
            $offset += 1000;
        }

        return ['format' => 'comspec-atak-mission-archive', 'version' => 1, 'archive' => $archive, 'items' => $items];
    }

    /**
     * Remet les éléments d'une mission archivée sur la carte du cycle courant (copies : l'archive reste intacte).
     *
     * @return array{ok: bool, error?: string, restored: int, skipped: int}
     */
    public function restore(int $tenantId, int $archiveId): array
    {
        $archive = $this->store->getArchive($tenantId, $archiveId);
        if ($archive === null) {
            return ['ok' => false, 'error' => 'not_found', 'restored' => 0, 'skipped' => 0];
        }
        $mapId = max(1, (int) ($archive['map_id'] ?? 1));
        $restored = 0;
        $skipped = 0;
        $offset = 0;
        while ($restored + $skipped < self::MAX_RESTORE) {
            $page = $this->store->archiveItems($tenantId, $archiveId, 500, $offset);
            foreach ($page as $it) {
                $payload = self::restorable($it['kind'], $it['payload'], $archiveId, $it['id']);
                $id = $payload === null ? null : $this->store->insertLive($tenantId, $mapId, $it['kind'], $payload);
                if ($id === null) {
                    $skipped++;
                } else {
                    $restored++;
                }
            }
            if (count($page) < 500) {
                break;
            }
            $offset += 500;
        }

        return ['ok' => true, 'restored' => $restored, 'skipped' => $skipped];
    }

    /**
     * Copie prête à remettre sur la carte : nouveau tracé (identifiant neuf), jamais masqué ni épinglé, jamais relié
     * au marqueur du jeu d'origine (un doublon du jeu ne doit pas écraser l'original).
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    public static function restorable(string $kind, array $payload, int $archiveId, int $sourceId): ?array
    {
        if ($kind === 'marker') {
            $data = $payload['marker_data'] ?? null;
            $decoded = is_string($data) ? json_decode($data, true) : (is_array($data) ? $data : null);
            if (!is_array($decoded)) {
                return null;
            }
            unset($decoded['suppressed'], $decoded['web_permanent'], $decoded['web_locked']);
            $decoded['source'] = 'archive';
            $decoded['restored_from_archive'] = $archiveId;
            $payload['marker_data'] = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $payload['arma_name'] = null;
            unset($payload['id']);

            return $payload;
        }
        if ($kind === 'shape') {
            $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
            unset($meta['web_permanent'], $meta['suppressed']);
            $meta['restored_from_archive'] = $archiveId;
            $payload['meta'] = $meta;
            $payload['shape_uid'] = substr('arc' . $archiveId . '-' . $sourceId . '-' . bin2hex(random_bytes(4)), 0, 64);
            unset($payload['id']);

            return $payload;
        }

        return null;
    }

    /** Purge manuelle d'une mission archivée. */
    public function deleteArchive(int $tenantId, int $archiveId): bool
    {
        return $this->store->deleteArchive($tenantId, $archiveId);
    }

    public function setPinned(int $tenantId, string $kind, int $id, bool $pinned): bool
    {
        if (!in_array($kind, ['marker', 'shape'], true) || $id < 1) {
            return false;
        }

        return $this->store->setPinned($tenantId, $kind, $id, $pinned);
    }

    private function log(string $level, string $message): void
    {
        ($this->log)($level, $message);
    }
}
