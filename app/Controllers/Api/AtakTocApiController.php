<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\AtakToc\TocAccess;
use App\Services\AtakToc\TocRoomService;

/**
 * Salle TOC du poste web : état partagé avec le jeu (chronos, main courante, EXCHECK, pertes, demandes, FLASH,
 * satellites, écrans) et actions de l'opérateur web.
 *   GET  /atak/toc/api/state?mapId=&side=   état (ETag : 304 si rien n'a bougé)
 *   POST /atak/toc/api/action               {mapId, side, act, args} (CSRF, 60 par minute)
 *   GET  /atak/toc/api/journal?mapId=&side= actions du web et leur passage en jeu
 *   POST /atak/toc/api/reset                remise à zéro de la salle web (configuration du poste)
 */
final class AtakTocApiController extends AtakTocBaseController
{
    private TocRoomService $rooms;

    public function __construct(?TocAccess $access = null, ?TocRoomService $rooms = null)
    {
        parent::__construct($access);
        $this->rooms = $rooms ?? new TocRoomService();
    }

    public function state(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        $mapId = $this->mapId($request);
        $side = $this->side($request);
        try {
            $view = $this->rooms->view($this->tenantId(), $mapId, $side);
        } catch (\Throwable) {
            return Response::json(['ok' => false, 'error' => 'schema_not_ready'], 503);
        }

        return $this->jsonWithEtag([
            'ok' => true,
            'mapId' => $mapId,
            'side' => $side,
            'now' => time(),
            'state' => $view['state'],
            'game' => $view['game'],
        ], $view['version'] . '-' . intdiv(time(), 5));
    }

    public function action(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        if (($deny = $this->guardWrite($request, 'room', 60)) !== null) {
            return $deny;
        }
        $b = $this->body();
        $act = is_string($b['act'] ?? null) ? $b['act'] : '';
        $mapId = $this->mapId($request);
        $side = $this->side($request);
        try {
            $res = $this->rooms->act($this->tenantId(), $mapId, $side, $this->userId(), $this->actorLabel(), $act, $b['args'] ?? []);
        } catch (\Throwable) {
            return Response::json(['ok' => false, 'error' => 'schema_not_ready'], 503);
        }
        if (!$res['ok']) {
            return Response::json(['ok' => false, 'error' => $res['reason']], 422);
        }
        $this->audit('atak_toc.room.' . $act, $res['action_id'] ?? null, ['map_id' => $mapId, 'side' => $side, 'args' => $b['args'] ?? []]);
        $view = $this->rooms->view($this->tenantId(), $mapId, $side);

        return Response::json(['ok' => true, 'action_id' => $res['action_id'] ?? null, 'state' => $view['state'], 'game' => $view['game'], 'now' => time()]);
    }

    public function journal(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        try {
            $rows = $this->rooms->recentActions($this->tenantId(), $this->mapId($request), $this->side($request));
        } catch (\Throwable) {
            $rows = [];
        }

        return Response::json(['ok' => true, 'actions' => $rows]);
    }

    public function reset(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        if (!$this->access->canManage()) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        if (!Csrf::verify($request)) {
            return Response::json(['ok' => false, 'error' => 'csrf'], 419);
        }
        $mapId = $this->mapId($request);
        $side = $this->side($request);
        $this->rooms->reset($this->tenantId(), $mapId, $side);
        $this->audit('atak_toc.room.reset', null, ['map_id' => $mapId, 'side' => $side]);

        return Response::json(['ok' => true]);
    }
}
