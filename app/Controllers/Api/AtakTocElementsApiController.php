<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\Atak\MarkerCycle\AtakMarkerCycleService;
use App\Services\Atak\MarkerCycle\MarkerCycleStore;
use App\Services\Atak\MarkerCycle\PdoMarkerCycleStore;
use App\Services\AtakToc\Purge\TocDeletionService;
use App\Services\AtakToc\Purge\TocElementStore;
use App\Services\AtakToc\Purge\TocPurgeService;
use App\Services\AtakToc\TocAccess;

/**
 * Suppression, corbeille et purge de la carte du poste TOC web.
 *   GET  /atak/toc/api/elements/trash?mapId=      corbeille de 15 minutes : lots, contenu, temps restant
 *   POST /atak/toc/api/elements/delete            {mapId, items, confirmPinned, hard} (CSRF, 30 par minute)
 *   POST /atak/toc/api/elements/undo              {mapId, batch}
 *   POST /atak/toc/api/elements/pin               {mapId, items, pinned}
 *   POST /atak/toc/api/elements/archive           {mapId, items, confirmPinned} instantané puis retrait
 *   GET  /atak/toc/api/purge/status?mapId=        purge en cours (reprise) et dernières purges
 *   GET  /atak/toc/api/purge/authors?mapId=       auteurs et unités présents sur la carte (aide au périmètre)
 *   POST /atak/toc/api/purge/preview              {mapId, scope} nombre d'éléments par catégorie
 *   POST /atak/toc/api/purge/start                {mapId, scope, confirm, expected} phrase PURGER
 *   POST /atak/toc/api/purge/step                 {mapId, job} un lot de 500
 *   POST /atak/toc/api/purge/cancel               {mapId, job}
 *
 * Communauté lue dans la session uniquement, jamais dans la requête ; carte contrôlée ; jeton CSRF et limite de débit sur
 * toute écriture (plafonds par membre et par minute, comptés par le socle). Droits : supprimer ses éléments = poste TOC (atak.toc.use), ceux des autres = atak.toc.manage ;
 * purger = atak.toc.purge (ou administration de la communauté).
 */
final class AtakTocElementsApiController extends AtakTocBaseController
{
    private ?TocElementStore $storeOverride;

    private ?MarkerCycleStore $archiveOverride;

    /** @var (callable(): int)|null */
    private $clock;

    /** @var (callable(string, int, int, int, array<string, mixed>): void)|null */
    private $auditHook;

    /**
     * @param (callable(): int)|null $clock
     * @param (callable(string, int, int, int, array<string, mixed>): void)|null $auditHook
     */
    public function __construct(?TocAccess $access = null, ?TocElementStore $store = null, ?MarkerCycleStore $archives = null, ?callable $clock = null, ?callable $auditHook = null)
    {
        parent::__construct($access);
        $this->storeOverride = $store;
        $this->archiveOverride = $archives;
        $this->clock = $clock;
        $this->auditHook = $auditHook;
    }

    private function store(): TocElementStore
    {
        return $this->storeOverride ??= new TocElementStore(\App\Core\Database::getPdo());
    }

    private function archives(): MarkerCycleStore
    {
        return $this->archiveOverride ??= new PdoMarkerCycleStore();
    }

    private function deletion(): TocDeletionService
    {
        return new TocDeletionService($this->store(), $this->clock, $this->auditHook);
    }

    private function purges(): TocPurgeService
    {
        $deletion = $this->deletion();

        return new TocPurgeService($this->store(), $deletion, $this->archives(), new AtakMarkerCycleService($this->archives(), $this->clock), $this->clock, $this->auditHook);
    }

    /** @return array{userId: int, aliases: list<string>, manage: bool, purge: bool} */
    private function actor(): array
    {
        $label = $this->actorLabel();

        return [
            'userId' => $this->userId(),
            'aliases' => $label !== '' ? [$label] : [],
            'manage' => $this->access->canManage(),
            'purge' => $this->access->canPurge(),
        ];
    }

    /** @return list<array{table: string, id: int}> */
    private function refs(): array
    {
        return TocElementStore::cleanRefs($this->body()['items'] ?? [], TocDeletionService::MAX_REFS);
    }

    private function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status)->header('Cache-Control', 'private, no-store');
    }

    /** @param array<string, mixed> $res */
    private function reply(array $res): Response
    {
        if (!empty($res['ok'])) {
            return $this->json($res);
        }
        $code = match ((string) ($res['error'] ?? '')) {
            'forbidden' => 403,
            'not_found' => 404,
            'purge_running', 'preview_changed' => 409,
            'unavailable', 'archive_unavailable' => 503,
            default => 422,
        };

        return $this->json($res, $code);
    }

    public function trash(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }

        return $this->json(['ok' => true] + $this->deletion()->trash($this->tenantId(), $this->mapId($request), $this->actor()));
    }

    public function delete(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        if (($deny = $this->guardWrite($request, 'elements-delete', 60)) !== null) {
            return $deny;
        }
        $b = $this->body();
        $res = $this->deletion()->delete($this->tenantId(), $this->mapId($request), $this->actor(), $this->refs(), [
            'confirmPinned' => is_string($b['confirmPinned'] ?? null) ? $b['confirmPinned'] : '',
            'hard' => !empty($b['hard']),
            'filter' => !empty($b['byZone']) ? 'zone' : 'selection',
        ]);

        return $this->reply($res);
    }

    public function undo(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        if (($deny = $this->guardWrite($request, 'elements-undo', 60)) !== null) {
            return $deny;
        }
        $batch = is_string($this->body()['batch'] ?? null) ? (string) $this->body()['batch'] : '';

        return $this->reply($this->deletion()->undo($this->tenantId(), $this->mapId($request), $this->actor(), $batch));
    }

    public function pin(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        if (($deny = $this->guardWrite($request, 'elements-pin', 60)) !== null) {
            return $deny;
        }

        return $this->reply($this->deletion()->pin($this->tenantId(), $this->mapId($request), $this->actor(), $this->refs(), !empty($this->body()['pinned'])));
    }

    public function archive(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        if (($deny = $this->guardWrite($request, 'elements-archive', 24)) !== null) {
            return $deny;
        }
        $b = $this->body();
        $res = $this->deletion()->archive(
            $this->tenantId(),
            $this->mapId($request),
            $this->actor(),
            $this->refs(),
            $this->archives(),
            new AtakMarkerCycleService($this->archives(), $this->clock),
            ['confirmPinned' => is_string($b['confirmPinned'] ?? null) ? $b['confirmPinned'] : '']
        );

        return $this->reply($res);
    }

    /** Droit de purge exigé avant toute lecture ou écriture de la purge. */
    private function guardPurge(): ?Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        if (!$this->access->canPurge()) {
            return Response::json(['ok' => false, 'error' => 'forbidden', 'need' => TocAccess::PURGE], 403);
        }

        return null;
    }

    public function purgeStatus(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        $res = $this->purges()->status($this->tenantId(), $this->mapId($request));
        $res['canPurge'] = $this->access->canPurge();
        $res['canManage'] = $this->access->canManage();
        $res['purgeRight'] = TocAccess::PURGE;

        return $this->json($res);
    }

    public function purgeAuthors(Request $request, array $params = []): Response
    {
        if (($deny = $this->guardPurge()) !== null) {
            return $deny;
        }
        $authors = [];
        $units = [];
        foreach ($this->store()->elements($this->tenantId(), $this->mapId($request), ['marker', 'shape', 'ping'], TocElementStore::MAX_SCAN) as $el) {
            if ($el['author'] !== '') {
                $authors[$el['author']] = ($authors[$el['author']] ?? 0) + 1;
            }
            if ($el['unit'] !== '') {
                $units[$el['unit']] = ($units[$el['unit']] ?? 0) + 1;
            }
        }
        arsort($authors);
        arsort($units);
        $fmt = static fn (array $m): array => array_map(static fn (string $k, int $n): array => ['name' => $k, 'count' => $n], array_keys(array_slice($m, 0, 60, true)), array_values(array_slice($m, 0, 60, true)));

        return $this->json(['ok' => true, 'authors' => $fmt($authors), 'units' => $fmt($units)]);
    }

    public function purgePreview(Request $request, array $params = []): Response
    {
        if (($deny = $this->guardPurge()) !== null) {
            return $deny;
        }
        if (($deny = $this->guardWrite($request, 'purge-preview', 80)) !== null) {
            return $deny;
        }
        $scope = is_array($this->body()['scope'] ?? null) ? $this->body()['scope'] : [];

        return $this->reply($this->purges()->preview($this->tenantId(), $this->mapId($request), $this->actor(), $scope));
    }

    public function purgeStart(Request $request, array $params = []): Response
    {
        if (($deny = $this->guardPurge()) !== null) {
            return $deny;
        }
        if (($deny = $this->guardWrite($request, 'purge-start', 12)) !== null) {
            return $deny;
        }
        $b = $this->body();
        $scope = is_array($b['scope'] ?? null) ? $b['scope'] : [];
        $res = $this->purges()->start($this->tenantId(), $this->mapId($request), $this->actor(), $scope, is_string($b['confirm'] ?? null) ? $b['confirm'] : '', (int) ($b['expected'] ?? -1));

        return $this->reply($res);
    }

    public function purgeStep(Request $request, array $params = []): Response
    {
        if (($deny = $this->guardPurge()) !== null) {
            return $deny;
        }
        if (($deny = $this->guardWrite($request, 'purge-step', 480)) !== null) {
            return $deny;
        }

        return $this->reply($this->purges()->step($this->tenantId(), $this->mapId($request), $this->actor(), (int) ($this->body()['job'] ?? 0)));
    }

    public function purgeCancel(Request $request, array $params = []): Response
    {
        if (($deny = $this->guardPurge()) !== null) {
            return $deny;
        }
        if (($deny = $this->guardWrite($request, 'purge-cancel', 40)) !== null) {
            return $deny;
        }

        return $this->reply($this->purges()->cancel($this->tenantId(), $this->mapId($request), $this->actor(), (int) ($this->body()['job'] ?? 0)));
    }
}
