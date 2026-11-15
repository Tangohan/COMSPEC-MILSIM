<?php

declare(strict_types=1);

namespace App\Services\AtakToc\Purge;

use App\Services\Atak\MarkerCycle\AtakMarkerCycleService;
use App\Services\Atak\MarkerCycle\MarkerCycleStore;

/**
 * Purge de la carte du poste TOC web : suppression en masse par périmètre (types, auteurs ou unités, âge, zone, cycle),
 * avec aperçu exact avant confirmation, archivage de la mission AVANT toute suppression, exécution par lots de 500 et
 * reprise possible.
 *
 * Déroulé : preview() compte ce qui partirait, par catégorie ; start() vérifie le droit, la phrase PURGER et que l'aperçu
 * confirmé est toujours exact, retient la liste des éléments et crée la purge ; step() traite un lot (d'abord tout
 * l'archivage, puis les suppressions) et peut être rappelé jusqu'à la fin, même après une coupure.
 *
 * Garanties : instantané immuable écrit d'abord (un échec d'archive arrête tout, rien n'est supprimé) ; marqueurs et tracés
 * vont dans la corbeille (annulables 15 minutes, par le lot de la purge), pings et pistes sont retirés pour de bon ;
 * éléments épinglés conservés par défaut (revérifiés au moment de supprimer) ; idempotent (un lot rejoué ne supprime rien
 * de plus) ; une seule purge à la fois par carte, un seul lot à la fois par purge ; journalisé.
 *
 * @phpstan-import-type Actor from TocDeletionService
 * @phpstan-import-type Scope from TocScope
 */
final class TocPurgeService
{
    public const BATCH = 500;
    public const MAX_ITEMS = 20000;
    public const PHRASE = 'PURGER';
    public const LOCK_SECONDS = 45;

    private const LETTER = ['marker' => 'm', 'shape' => 's', 'ping' => 'p', 'track' => 't'];
    private const TABLE = ['m' => 'marker', 's' => 'shape', 'p' => 'ping', 't' => 'track'];

    /** @var callable(): int */
    private $clock;

    /** @var callable(string, int, int, int, array<string, mixed>): void */
    private $audit;

    /**
     * @param callable(): int|null $clock
     * @param callable(string, int, int, int, array<string, mixed>): void|null $audit
     */
    public function __construct(
        private TocElementStore $store,
        private TocDeletionService $deletion,
        private MarkerCycleStore $archives,
        private AtakMarkerCycleService $cycles,
        ?callable $clock = null,
        ?callable $audit = null,
    ) {
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

    /**
     * Éléments du périmètre (épinglés exclus si on les garde), plus anciens d'abord.
     *
     * @param Scope $scope
     * @return array{items: list<array<string, mixed>>, pinnedKept: int, capped: bool}
     */
    public function select(int $tenantId, int $mapId, array $scope, int $now): array
    {
        $tables = [];
        foreach ($scope['types'] as $t) {
            $tables[match ($t) {
                'marker' => 'marker',
                'ping' => 'ping',
                'track' => 'track',
                default => 'shape',
            }] = true;
        }
        $cycleStart = $scope['cycleOnly'] ? AtakMarkerCycleService::windowAt($this->cycles->settings($tenantId), $now)['start'] : 0;
        $items = [];
        $kept = 0;
        $capped = false;
        foreach ($this->store->elements($tenantId, $mapId, array_keys($tables), TocElementStore::MAX_SCAN + 1) as $el) {
            if (!TocScope::matches($scope, $el, $now, $cycleStart)) {
                continue;
            }
            if ($el['pinned'] && $scope['keepPinned']) {
                $kept++;
                continue;
            }
            if (count($items) >= self::MAX_ITEMS) {
                $capped = true;
                break;
            }
            $items[] = $el;
        }
        usort($items, static fn (array $a, array $b): int => [$a['table'] === 'marker' || $a['table'] === 'shape' ? 0 : 1, $a['at'], $a['id']] <=> [$b['table'] === 'marker' || $b['table'] === 'shape' ? 0 : 1, $b['at'], $b['id']]);

        return ['items' => $items, 'pinnedKept' => $kept, 'capped' => $capped];
    }

    /**
     * Ce qui partirait : nombre par catégorie, épinglés conservés ou inclus, éléments sans retour.
     *
     * @param Actor $actor
     * @param array<string, mixed> $rawScope
     * @return array<string, mixed>
     */
    public function preview(int $tenantId, int $mapId, array $actor, array $rawScope): array
    {
        if (!$actor['purge']) {
            return ['ok' => false, 'error' => 'forbidden'];
        }
        if (!$this->store->softReady()) {
            return ['ok' => false, 'error' => 'unavailable'];
        }
        $scope = TocScope::normalize($rawScope);
        if ($scope['types'] === []) {
            return ['ok' => false, 'error' => 'types_required'];
        }
        $now = $this->now();
        $sel = $this->select($tenantId, $mapId, $scope, $now);
        $by = array_fill_keys($scope['types'], 0);
        $pinnedIn = 0;
        $archivable = 0;
        $irreversible = 0;
        foreach ($sel['items'] as $el) {
            $by[$el['kind']] = ($by[$el['kind']] ?? 0) + 1;
            $pinnedIn += $el['pinned'] ? 1 : 0;
            if ($el['table'] === 'marker' || $el['table'] === 'shape') {
                $archivable++;
            } else {
                $irreversible++;
            }
        }

        return [
            'ok' => true, 'scope' => $scope, 'total' => count($sel['items']), 'byKind' => $by, 'pinnedKept' => $sel['pinnedKept'],
            'pinnedIncluded' => $pinnedIn, 'archivable' => $archivable, 'irreversible' => $irreversible, 'capped' => $sel['capped'],
            'max' => self::MAX_ITEMS, 'batch' => self::BATCH, 'wholeMap' => TocScope::isWholeMap($scope), 'phrase' => self::PHRASE,
            'archiveReady' => $this->archives->ready(), 'running' => $this->view($this->store->activeJob($tenantId, $mapId, $now)),
        ];
    }

    /**
     * Lance la purge : le périmètre confirmé doit être celui de l'aperçu (même total), la phrase PURGER tapée.
     *
     * @param Actor $actor
     * @param array<string, mixed> $rawScope
     * @return array<string, mixed>
     */
    public function start(int $tenantId, int $mapId, array $actor, array $rawScope, string $confirm, int $expectedTotal): array
    {
        if (!$actor['purge']) {
            return ['ok' => false, 'error' => 'forbidden'];
        }
        if (strcasecmp(trim($confirm), self::PHRASE) !== 0) {
            return ['ok' => false, 'error' => 'confirm_required', 'phrase' => self::PHRASE];
        }
        if (!$this->store->softReady()) {
            return ['ok' => false, 'error' => 'unavailable'];
        }
        $scope = TocScope::normalize($rawScope);
        if ($scope['types'] === []) {
            return ['ok' => false, 'error' => 'types_required'];
        }
        $now = $this->now();
        $running = $this->store->activeJob($tenantId, $mapId, $now);
        if ($running !== null) {
            return ['ok' => false, 'error' => 'purge_running', 'job' => $this->view($running)];
        }
        if ($scope['archiveFirst'] && !$this->archives->ready()) {
            return ['ok' => false, 'error' => 'archive_unavailable'];
        }
        $sel = $this->select($tenantId, $mapId, $scope, $now);
        if ($sel['capped']) {
            return ['ok' => false, 'error' => 'too_many', 'max' => self::MAX_ITEMS];
        }
        $total = count($sel['items']);
        if ($total === 0) {
            return ['ok' => false, 'error' => 'nothing'];
        }
        if ($total !== $expectedTotal) {
            return ['ok' => false, 'error' => 'preview_changed', 'total' => $total, 'expected' => $expectedTotal];
        }
        $refs = array_map(static fn (array $e): array => [self::LETTER[$e['table']], $e['id']], $sel['items']);
        $archivable = count(array_filter($sel['items'], static fn (array $e): bool => $e['table'] === 'marker' || $e['table'] === 'shape'));
        $phase = $scope['archiveFirst'] && $archivable > 0 ? 'archiving' : 'deleting';
        $stamp = TocElementStore::stamp($now);
        $batch = TocDeletionService::newBatch();
        $id = $this->store->insertJob([
            'tenant_id' => $tenantId, 'map_id' => $mapId, 'user_id' => $actor['userId'] > 0 ? $actor['userId'] : null, 'batch' => $batch,
            'status' => $phase, 'scope_json' => (string) json_encode($scope, JSON_UNESCAPED_UNICODE), 'archive_first' => $scope['archiveFirst'] ? 1 : 0,
            'keep_pinned' => $scope['keepPinned'] ? 1 : 0, 'total' => $total, 'archived' => 0, 'deleted' => 0, 'kept_pinned' => $sel['pinnedKept'],
            'cursor_pos' => 0, 'archive_id' => null, 'refs' => (string) json_encode($refs), 'error' => null, 'locked_until' => null,
            'created_at' => $stamp, 'updated_at' => $stamp, 'finished_at' => null,
        ]);
        $this->audit('atak.toc.purge_start', $tenantId, $actor['userId'], $mapId, [
            'job' => $id, 'total' => $total, 'scope' => $scope, 'archive_first' => $scope['archiveFirst'], 'keep_pinned' => $scope['keepPinned'], 'batch' => $batch,
        ]);

        return ['ok' => true, 'job' => $this->view($this->store->job($tenantId, $id))];
    }

    /**
     * Traite un lot de la purge. Rappelable jusqu'à status « done » ; un lot déjà pris par une autre exécution renvoie busy.
     *
     * @param Actor $actor
     * @return array<string, mixed>
     */
    public function step(int $tenantId, int $mapId, array $actor, int $jobId): array
    {
        if (!$actor['purge']) {
            return ['ok' => false, 'error' => 'forbidden'];
        }
        $job = $this->store->job($tenantId, $jobId);
        if ($job === null || (int) $job['map_id'] !== $mapId) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        if (!in_array($job['status'], ['archiving', 'deleting'], true)) {
            return ['ok' => true, 'job' => $this->view($job)];
        }
        $now = $this->now();
        if (!$this->store->claimJob($tenantId, $jobId, $now, self::LOCK_SECONDS)) {
            return ['ok' => true, 'busy' => true, 'job' => $this->view($job)];
        }
        try {
            $refs = json_decode((string) $job['refs'], true);
            $refs = is_array($refs) ? $refs : [];
            $job = $job['status'] === 'archiving'
                ? $this->archiveBatch($tenantId, $mapId, $job, $refs, $now)
                : $this->deleteBatch($tenantId, $mapId, $actor, $job, $refs, $now);
        } catch (\Throwable $e) {
            error_log('[atak-toc-purge] lot ' . $jobId . ' : ' . $e->getMessage());
            $this->store->updateJob($tenantId, $jobId, ['status' => 'failed', 'error' => 'batch_failed', 'updated_at' => TocElementStore::stamp($now), 'finished_at' => TocElementStore::stamp($now)]);
            $job = $this->store->job($tenantId, $jobId) ?? $job;
        }
        $this->store->updateJob($tenantId, $jobId, ['locked_until' => TocElementStore::stamp($now - 1)]);

        return ['ok' => true, 'job' => $this->view($this->store->job($tenantId, $jobId) ?? $job)];
    }

    /**
     * @param array<string, mixed> $job
     * @param list<mixed> $refs
     * @return array<string, mixed>
     */
    private function archiveBatch(int $tenantId, int $mapId, array $job, array $refs, int $now): array
    {
        $jobId = (int) $job['id'];
        $archivable = [];
        foreach ($refs as $r) {
            if (is_array($r) && ($r[0] === 'm' || $r[0] === 's')) {
                $archivable[] = ['table' => self::TABLE[$r[0]], 'id' => (int) $r[1]];
            }
        }
        $cursor = (int) $job['cursor_pos'];
        $slice = array_slice($archivable, $cursor, self::BATCH);
        $archiveId = $job['archive_id'] !== null ? (int) $job['archive_id'] : null;
        if ($slice !== []) {
            $w = AtakMarkerCycleService::windowAt($this->cycles->settings($tenantId), $now);
            $items = $this->store->snapshot($tenantId, $mapId, $slice);
            try {
                $archiveId = $this->archives->writeArchive(
                    $tenantId,
                    $mapId,
                    ['key' => $w['key'], 'label' => $w['label'], 'start_utc' => $w['start_utc'], 'end_utc' => $w['end_utc']],
                    'purge',
                    $job['user_id'] !== null ? (int) $job['user_id'] : null,
                    $items
                );
            } catch (\Throwable) {
                // L'instantané n'est pas écrit : on ne supprime RIEN.
                $this->store->updateJob($tenantId, $jobId, ['status' => 'failed', 'error' => 'archive_failed', 'updated_at' => TocElementStore::stamp($now), 'finished_at' => TocElementStore::stamp($now)]);
                $this->audit('atak.toc.purge_failed', $tenantId, (int) ($job['user_id'] ?? 0), $mapId, ['job' => $jobId, 'error' => 'archive_failed', 'archived' => (int) $job['archived']]);

                return $this->store->job($tenantId, $jobId) ?? $job;
            }
        }
        $cursor += count($slice);
        $done = $cursor >= count($archivable);
        $this->store->updateJob($tenantId, $jobId, [
            'cursor_pos' => $done ? 0 : $cursor, 'archived' => min(count($archivable), $cursor), 'archive_id' => $archiveId,
            'status' => $done ? 'deleting' : 'archiving', 'updated_at' => TocElementStore::stamp($now),
        ]);

        return $this->store->job($tenantId, $jobId) ?? $job;
    }

    /**
     * @param Actor $actor
     * @param array<string, mixed> $job
     * @param list<mixed> $refs
     * @return array<string, mixed>
     */
    private function deleteBatch(int $tenantId, int $mapId, array $actor, array $job, array $refs, int $now): array
    {
        $jobId = (int) $job['id'];
        $cursor = (int) $job['cursor_pos'];
        $slice = [];
        foreach (array_slice($refs, $cursor, self::BATCH) as $r) {
            if (is_array($r) && isset(self::TABLE[(string) $r[0]])) {
                $slice[] = ['table' => self::TABLE[(string) $r[0]], 'id' => (int) $r[1]];
            }
        }
        $live = $this->store->find($tenantId, $mapId, $slice);
        $victims = [];
        $kept = (int) $job['kept_pinned'];
        foreach ($live as $el) {
            if ((int) $job['keep_pinned'] === 1 && $el['pinned']) {
                $kept++;
                continue;
            }
            $victims[] = $el;
        }
        $deleted = $this->deletion->remove($tenantId, $mapId, $victims, (int) ($job['user_id'] ?? 0), (string) $job['batch'], $now, true);
        $cursor += count($slice);
        $done = $cursor >= count($refs);
        $fields = [
            'cursor_pos' => $cursor, 'deleted' => (int) $job['deleted'] + $deleted, 'kept_pinned' => $kept, 'updated_at' => TocElementStore::stamp($now),
        ];
        if ($done) {
            $fields['status'] = 'done';
            $fields['finished_at'] = TocElementStore::stamp($now);
        }
        $this->store->updateJob($tenantId, $jobId, $fields);
        if ($done) {
            $this->audit('atak.toc.purge', $tenantId, (int) ($job['user_id'] ?? 0), $mapId, [
                'job' => $jobId, 'total' => (int) $job['total'], 'deleted' => $fields['deleted'], 'kept_pinned' => $kept,
                'archive_id' => $job['archive_id'] !== null ? (int) $job['archive_id'] : null, 'batch' => (string) $job['batch'],
                'scope' => json_decode((string) $job['scope_json'], true),
            ]);
            $this->deletion->announce($tenantId, $mapId, $actor, 'purge', (int) $fields['deleted'], (string) $job['batch'], $now);
        } else {
            $this->deletion->bumpRev($tenantId, $mapId);
        }

        return $this->store->job($tenantId, $jobId) ?? $job;
    }

    /**
     * Arrête une purge en cours : ce qui est déjà dans la corbeille reste annulable pendant 15 minutes.
     *
     * @param Actor $actor
     * @return array<string, mixed>
     */
    public function cancel(int $tenantId, int $mapId, array $actor, int $jobId): array
    {
        if (!$actor['purge']) {
            return ['ok' => false, 'error' => 'forbidden'];
        }
        $job = $this->store->job($tenantId, $jobId);
        if ($job === null || (int) $job['map_id'] !== $mapId) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        if (in_array($job['status'], ['archiving', 'deleting'], true)) {
            $now = $this->now();
            $this->store->updateJob($tenantId, $jobId, ['status' => 'cancelled', 'updated_at' => TocElementStore::stamp($now), 'finished_at' => TocElementStore::stamp($now)]);
            $this->audit('atak.toc.purge_cancel', $tenantId, $actor['userId'], $mapId, ['job' => $jobId, 'deleted' => (int) $job['deleted']]);
            $job = $this->store->job($tenantId, $jobId) ?? $job;
        }

        return ['ok' => true, 'job' => $this->view($job)];
    }

    /**
     * Purge en cours de la carte (pour la reprendre après un rechargement) et dernières purges.
     *
     * @return array<string, mixed>
     */
    public function status(int $tenantId, int $mapId): array
    {
        $now = $this->now();

        return [
            'ok' => true, 'available' => $this->store->softReady(),
            'running' => $this->view($this->store->activeJob($tenantId, $mapId, $now)),
            'recent' => $this->store->softReady() ? array_map(fn (array $j): array => $this->view($j) ?? [], $this->store->recentJobs($tenantId, $mapId)) : [],
        ];
    }

    /**
     * @param array<string, mixed>|null $job
     * @return array<string, mixed>|null
     */
    public function view(?array $job): ?array
    {
        if ($job === null) {
            return null;
        }
        $total = max(1, (int) $job['total']);
        $archivable = 0;
        $archiveFirst = (int) ($job['archive_first'] ?? 0) === 1;
        if (isset($job['refs']) && is_string($job['refs']) && $archiveFirst) {
            $arr = json_decode($job['refs'], true);
            foreach (is_array($arr) ? $arr : [] as $r) {
                $archivable += is_array($r) && ($r[0] === 'm' || $r[0] === 's') ? 1 : 0;
            }
        }
        $status = (string) $job['status'];
        if ($status === 'done') {
            $progress = 100;
        } elseif ($status === 'archiving') {
            $progress = (int) floor(50 * min(1, (int) $job['archived'] / max(1, $archivable)));
        } else {
            $base = $archiveFirst && $archivable > 0 ? 50 : 0;
            $progress = $base + (int) floor((100 - $base) * min(1, (int) ($job['cursor_pos'] ?? 0) / $total));
        }
        $finished = TocElementStore::ts($job['finished_at'] ?? null);

        return [
            'id' => (int) $job['id'], 'status' => $status, 'total' => (int) $job['total'], 'archived' => (int) $job['archived'],
            'deleted' => (int) $job['deleted'], 'keptPinned' => (int) $job['kept_pinned'], 'archiveId' => $job['archive_id'] !== null ? (int) $job['archive_id'] : null,
            'batch' => (string) $job['batch'], 'error' => (string) ($job['error'] ?? ''), 'progress' => $progress, 'archiveFirst' => $archiveFirst,
            'keepPinned' => (int) ($job['keep_pinned'] ?? 1) === 1, 'by' => (int) ($job['user_id'] ?? 0),
            'createdAt' => TocElementStore::ts($job['created_at'] ?? null), 'finishedAt' => $finished,
            'undoUntil' => $status === 'done' || $status === 'cancelled' ? ($finished > 0 ? $finished + TocSoftDelete::TRASH_TTL : 0) : 0,
        ];
    }

    private function now(): int
    {
        return ($this->clock)();
    }

    /** @param array<string, mixed> $details */
    private function audit(string $action, int $tenantId, int $userId, int $mapId, array $details): void
    {
        ($this->audit)($action, $tenantId, $userId, $mapId, $details);
    }
}
