<?php

declare(strict_types=1);

namespace App\Services\AtakToc\Purge;

use App\Services\Atak\MarkerCycle\AtakMarkerCycleService;
use App\Services\Atak\MarkerCycle\MarkerCycleStore;
use App\Support\ShortCache;

/**
 * Suppression, annulation, épinglage et archivage de la sélection sur la carte du poste TOC web.
 *
 *  - suppression douce : les marqueurs et tracés vont dans la corbeille 15 minutes (annulable), puis sont retirés pour
 *    de bon ; un marqueur du jeu reste ensuite 48 h en pierre tombale pour que le jeu le retire de sa carte native ;
 *  - un élément épinglé (« Permanent ») ne part qu'avec une confirmation renforcée, distincte (phrase PERMANENT) ;
 *  - chacun supprime les siens (propriétaire, auteur) ; supprimer ceux des autres demande atak.toc.manage ;
 *  - sans la migration 4690 (mode dégradé), la suppression est définitive et demande une confirmation explicite ;
 *  - tout est borné par la communauté et la carte reçues (jamais lues dans une requête) et journalisé.
 *
 * @phpstan-type Actor array{userId: int, aliases: list<string>, manage: bool, purge: bool}
 */
final class TocDeletionService
{
    public const MAX_REFS = 1000;
    public const PINNED_PHRASE = 'PERMANENT';

    /** @var callable(): int */
    private $clock;

    /** @var callable(string, int, int, int, array<string, mixed>): void */
    private $audit;

    /**
     * @param callable(): int|null $clock
     * @param callable(string, int, int, int, array<string, mixed>): void|null $audit action, communauté, membre, carte, détails
     */
    public function __construct(private TocElementStore $store, ?callable $clock = null, ?callable $audit = null)
    {
        $this->clock = $clock ?? static fn (): int => time();
        $this->audit = $audit ?? static function (string $action, int $tenantId, int $userId, int $mapId, array $details): void {
            try {
                $details['map_id'] = $mapId;
                (new \App\Services\Audit\AuditService())->logChange($action, $tenantId, $userId ?: null, 'atak_map', $mapId, [], $details);
            } catch (\Throwable $e) {
                error_log('[atak-toc-purge audit] ' . $action . ' : ' . $e->getMessage());
            }
        };
    }

    public function store(): TocElementStore
    {
        return $this->store;
    }

    public function now(): int
    {
        return ($this->clock)();
    }

    /** @param Actor $actor @param array<string, mixed> $el */
    public static function mayDelete(array $actor, array $el): bool
    {
        if ($actor['manage']) {
            return true;
        }
        if ($el['table'] === 'track') {
            return false;
        }
        if ($actor['userId'] > 0 && (int) $el['owner'] === $actor['userId']) {
            return true;
        }
        $author = mb_strtolower(trim((string) $el['author']));
        if ($author === '') {
            return false;
        }
        foreach ($actor['aliases'] as $a) {
            if (mb_strtolower(trim($a)) === $author) {
                return true;
            }
        }

        return false;
    }

    private static function samePhrase(string $given, string $want): bool
    {
        return strcasecmp(trim($given), $want) === 0;
    }

    public static function newBatch(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * Aperçu d'une sélection : ce qui partirait, ce qui est refusé, ce qui est épinglé.
     *
     * @param Actor $actor
     * @param list<array{table: string, id: int}> $refs
     * @return array{found: int, deletable: int, pinned: int, forbidden: int, missing: int, byKind: array<string, int>}
     */
    public function preview(int $tenantId, int $mapId, array $actor, array $refs): array
    {
        $found = $this->store->find($tenantId, $mapId, $refs);
        $out = ['found' => count($found), 'deletable' => 0, 'pinned' => 0, 'forbidden' => 0, 'missing' => count($refs) - count($found), 'byKind' => []];
        foreach ($found as $el) {
            if (!self::mayDelete($actor, $el)) {
                $out['forbidden']++;
                continue;
            }
            $out['deletable']++;
            $out['pinned'] += $el['pinned'] ? 1 : 0;
            $out['byKind'][$el['kind']] = ($out['byKind'][$el['kind']] ?? 0) + 1;
        }

        return $out;
    }

    /**
     * @param Actor $actor
     * @param list<array{table: string, id: int}> $refs
     * @param array{confirmPinned?: string, hard?: bool, filter?: string} $opts
     * @return array<string, mixed>
     */
    public function delete(int $tenantId, int $mapId, array $actor, array $refs, array $opts = []): array
    {
        if ($tenantId < 1 || $mapId < 1) {
            return ['ok' => false, 'error' => 'scope'];
        }
        $refs = array_slice($refs, 0, self::MAX_REFS);
        if ($refs === []) {
            return ['ok' => false, 'error' => 'nothing'];
        }
        $now = $this->now();
        $this->store->expire($tenantId, $now);
        $found = $this->store->find($tenantId, $mapId, $refs);
        $allowed = [];
        $forbidden = 0;
        $pinned = 0;
        foreach ($found as $el) {
            if (!self::mayDelete($actor, $el)) {
                $forbidden++;
                continue;
            }
            $allowed[] = $el;
            $pinned += $el['pinned'] ? 1 : 0;
        }
        if ($allowed === []) {
            return ['ok' => false, 'error' => $forbidden > 0 ? 'forbidden' : 'nothing', 'forbidden' => $forbidden];
        }
        if ($pinned > 0 && !self::samePhrase((string) ($opts['confirmPinned'] ?? ''), self::PINNED_PHRASE)) {
            return ['ok' => false, 'error' => 'pinned_confirm', 'pinned' => $pinned, 'phrase' => self::PINNED_PHRASE];
        }
        $soft = $this->store->softReady();
        if (!$soft && empty($opts['hard'])) {
            return ['ok' => false, 'error' => 'degraded_confirm', 'count' => count($allowed)];
        }
        $batch = self::newBatch();
        $count = $this->remove($tenantId, $mapId, $allowed, (int) $actor['userId'], $batch, $now, $soft);
        $kinds = [];
        foreach ($allowed as $el) {
            $kinds[$el['kind']] = ($kinds[$el['kind']] ?? 0) + 1;
        }
        $this->audit('atak.toc.delete', $tenantId, $actor['userId'], $mapId, [
            'count' => $count, 'kinds' => $kinds, 'pinned' => $pinned, 'filter' => (string) ($opts['filter'] ?? 'selection'),
            'batch' => $soft ? $batch : '', 'soft' => $soft, 'skipped_forbidden' => $forbidden,
        ]);
        $this->announce($tenantId, $mapId, $actor, 'delete', $count, $soft ? $batch : '', $now);

        return [
            'ok' => true, 'batch' => $soft ? $batch : '', 'count' => $count, 'pinned' => $pinned, 'forbidden' => $forbidden,
            'missing' => count($refs) - count($found), 'soft' => $soft, 'undoUntil' => $soft ? $now + TocSoftDelete::TRASH_TTL : 0,
            'irreversible' => count(array_filter($allowed, static fn (array $e): bool => !in_array($e['kind'], TocScope::RECOVERABLE, true))),
        ];
    }

    /**
     * Retire des éléments déjà contrôlés : corbeille pour les marqueurs et tracés, suppression définitive pour le reste.
     *
     * @param list<array<string, mixed>> $elements
     */
    public function remove(int $tenantId, int $mapId, array $elements, int $userId, string $batch, int $now, bool $soft): int
    {
        $recoverable = [];
        $hard = [];
        foreach ($elements as $el) {
            $ref = ['table' => (string) $el['table'], 'id' => (int) $el['id']];
            if ($soft && ($ref['table'] === 'marker' || $ref['table'] === 'shape')) {
                $recoverable[] = $ref;
            } else {
                $hard[] = $ref;
            }
        }
        $n = 0;
        foreach (array_chunk($recoverable, 500) as $chunk) {
            $n += $this->store->softDelete($tenantId, $mapId, $chunk, $userId > 0 ? $userId : null, $batch, $now);
        }
        foreach (array_chunk($hard, 500) as $chunk) {
            $n += $this->store->hardDelete($tenantId, $mapId, $chunk, $now);
        }

        return $n;
    }

    /**
     * Annule un lot encore dans le délai. Seul son auteur, ou la gestion du poste, peut le faire.
     *
     * @param Actor $actor
     * @return array{ok: bool, error?: string, restored?: int}
     */
    public function undo(int $tenantId, int $mapId, array $actor, string $batch): array
    {
        if (preg_match(TocSoftDelete::BATCH_PATTERN, $batch) !== 1) {
            return ['ok' => false, 'error' => 'batch'];
        }
        $now = $this->now();
        $this->store->expire($tenantId, $now);
        $mine = false;
        $any = false;
        foreach ($this->store->trashed($tenantId, $mapId, $now) as $el) {
            if ($el['batch'] === $batch) {
                $any = true;
                $mine = $mine || ($el['deletedBy'] > 0 && $el['deletedBy'] === $actor['userId']);
            }
        }
        if (!$any) {
            return ['ok' => false, 'error' => 'expired'];
        }
        if (!$mine && !$actor['manage']) {
            return ['ok' => false, 'error' => 'forbidden'];
        }
        $restored = $this->store->restoreBatch($tenantId, $mapId, $batch, $now);
        $this->audit('atak.toc.delete_undo', $tenantId, $actor['userId'], $mapId, ['batch' => $batch, 'restored' => $restored]);
        $this->announce($tenantId, $mapId, $actor, 'undo', $restored, $batch, $now);

        return ['ok' => true, 'restored' => $restored];
    }

    /**
     * Corbeille de la carte : lots récents, leur contenu et le temps qu'il reste pour les annuler.
     *
     * @param Actor $actor
     * @return array{batches: list<array<string, mixed>>, ttl: int, soft: bool}
     */
    public function trash(int $tenantId, int $mapId, array $actor): array
    {
        $now = $this->now();
        $this->store->expire($tenantId, $now);
        $batches = [];
        foreach ($this->store->trashed($tenantId, $mapId, $now) as $el) {
            $b = $el['batch'];
            if (!isset($batches[$b])) {
                $batches[$b] = [
                    'batch' => $b, 'at' => $el['deletedAt'], 'by' => $el['deletedBy'], 'count' => 0, 'byKind' => [], 'pinned' => 0,
                    'until' => $el['deletedAt'] + TocSoftDelete::TRASH_TTL, 'mine' => $el['deletedBy'] === $actor['userId'],
                    'canUndo' => $actor['manage'] || $el['deletedBy'] === $actor['userId'], 'items' => [],
                ];
            }
            $batches[$b]['count']++;
            $batches[$b]['pinned'] += $el['pinned'] ? 1 : 0;
            $batches[$b]['byKind'][$el['kind']] = ($batches[$b]['byKind'][$el['kind']] ?? 0) + 1;
            if (count($batches[$b]['items']) < 40) {
                $batches[$b]['items'][] = ['ref' => $el['ref'], 'kind' => $el['kind'], 'label' => $el['label'], 'type' => $el['type'], 'author' => $el['author'], 'pinned' => $el['pinned']];
            }
        }
        usort($batches, static fn (array $a, array $b): int => $b['at'] <=> $a['at']);

        return ['batches' => array_values($batches), 'ttl' => TocSoftDelete::TRASH_TTL, 'soft' => $this->store->softReady()];
    }

    /**
     * Épingle ou désépingle une sélection (marqueurs et tracés).
     *
     * @param Actor $actor
     * @param list<array{table: string, id: int}> $refs
     * @return array<string, mixed>
     */
    public function pin(int $tenantId, int $mapId, array $actor, array $refs, bool $pinned): array
    {
        $refs = array_slice(array_values(array_filter($refs, static fn (array $r): bool => $r['table'] === 'marker' || $r['table'] === 'shape')), 0, self::MAX_REFS);
        if ($refs === []) {
            return ['ok' => false, 'error' => 'nothing'];
        }
        $found = $this->store->find($tenantId, $mapId, $refs);
        $n = 0;
        $now = $this->now();
        foreach ($found as $el) {
            if (!self::mayDelete($actor, $el) || $el['pinned'] === $pinned) {
                continue;
            }
            if ($this->store->setPinned($tenantId, $mapId, $el['table'], $el['id'], $pinned, $now)) {
                $n++;
            }
        }
        $this->audit($pinned ? 'atak.toc.pin' : 'atak.toc.unpin', $tenantId, $actor['userId'], $mapId, ['count' => $n]);
        $this->bumpRev($tenantId, $mapId);

        return ['ok' => true, 'count' => $n, 'pinned' => $pinned];
    }

    /**
     * Archive la sélection dans la mission du cycle courant (instantané écrit d'abord), puis la retire de la carte.
     * Un échec d'écriture de l'instantané ne retire rien.
     *
     * @param Actor $actor
     * @param list<array{table: string, id: int}> $refs
     * @param array{confirmPinned?: string} $opts
     * @return array<string, mixed>
     */
    public function archive(int $tenantId, int $mapId, array $actor, array $refs, MarkerCycleStore $archives, AtakMarkerCycleService $cycles, array $opts = []): array
    {
        $refs = array_slice(array_values(array_filter($refs, static fn (array $r): bool => $r['table'] === 'marker' || $r['table'] === 'shape')), 0, self::MAX_REFS);
        if ($refs === [] || !$archives->ready()) {
            return ['ok' => false, 'error' => $refs === [] ? 'nothing' : 'archive_unavailable'];
        }
        $found = $this->store->find($tenantId, $mapId, $refs);
        $allowed = array_values(array_filter($found, fn (array $el): bool => self::mayDelete($actor, $el)));
        if ($allowed === []) {
            return ['ok' => false, 'error' => $found === [] ? 'nothing' : 'forbidden'];
        }
        $pinned = count(array_filter($allowed, static fn (array $e): bool => $e['pinned']));
        if ($pinned > 0 && !self::samePhrase((string) ($opts['confirmPinned'] ?? ''), self::PINNED_PHRASE)) {
            return ['ok' => false, 'error' => 'pinned_confirm', 'pinned' => $pinned, 'phrase' => self::PINNED_PHRASE];
        }
        $now = $this->now();
        $w = AtakMarkerCycleService::windowAt($cycles->settings($tenantId), $now);
        $items = $this->store->snapshot($tenantId, $mapId, array_map(static fn (array $e): array => ['table' => $e['table'], 'id' => $e['id']], $allowed));
        try {
            $archiveId = $archives->writeArchive($tenantId, $mapId, ['key' => $w['key'], 'label' => $w['label'], 'start_utc' => $w['start_utc'], 'end_utc' => $w['end_utc']], 'manual', $actor['userId'] > 0 ? $actor['userId'] : null, $items);
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'archive_failed'];
        }
        $soft = $this->store->softReady();
        // « archive » : retiré de la carte et du jeu, sans corbeille (l'instantané est dans les missions archivées).
        $n = $this->remove($tenantId, $mapId, $allowed, $actor['userId'], $soft ? 'archive' : '', $now, $soft);
        $this->audit('atak.toc.archive', $tenantId, $actor['userId'], $mapId, ['count' => $n, 'archive_id' => $archiveId, 'pinned' => $pinned]);
        $this->announce($tenantId, $mapId, $actor, 'archive', $n, '', $now);

        return ['ok' => true, 'count' => $n, 'archiveId' => $archiveId];
    }

    /**
     * Notifie les opérateurs connectés par le canal de synchro du poste (révision de la carte et dernier évènement lus
     * par /atak/toc/api/state) : la carte se met à jour sans rechargement. Aucune notification dans le jeu.
     *
     * @param Actor $actor
     */
    public function announce(int $tenantId, int $mapId, array $actor, string $what, int $count, string $batch, int $now): void
    {
        try {
            ShortCache::put('toc_evt:' . $tenantId . ':' . $mapId, [
                'what' => $what, 'count' => $count, 'batch' => $batch, 'by' => $actor['aliases'][0] ?? '', 'byId' => $actor['userId'], 'at' => $now,
            ], 3600);
            $this->bumpRev($tenantId, $mapId);
        } catch (\Throwable) {
            // Sans cache partagé, les postes relisent la carte à leur prochain sondage.
        }
    }

    public function bumpRev(int $tenantId, int $mapId): void
    {
        try {
            ShortCache::bump('toc_map:' . $tenantId . ':' . $mapId);
        } catch (\Throwable) {
        }
    }

    /** @param array<string, mixed> $details */
    private function audit(string $action, int $tenantId, int $userId, int $mapId, array $details): void
    {
        ($this->audit)($action, $tenantId, $userId, $mapId, $details);
    }
}
