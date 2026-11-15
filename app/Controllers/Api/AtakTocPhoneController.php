<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\AtakToc\TocAccess;
use App\Services\AtakToc\TocPhoneService;

/**
 * Actions du poste TOC web sur les téléphones ATAK en jeu (docs/TOC-WEB.md, « Téléphones »).
 *   GET  /atak/toc/api/phones?mapId=&side=              téléphones connus du poste, état, dernière position et actions permises
 *   GET  /atak/toc/api/phones/events?ref=               journal des demandes (demandes ouvertes mises à jour au passage)
 *   GET  /atak/toc/api/phones/{ref}                     fiche d'un téléphone : demandes et positions obtenues
 *   POST /atak/toc/api/phones/settings                  {enabled, hide_hostile_journal} : réglage de la communauté (atak.toc.manage)
 *   POST /atak/toc/api/phones/{ref}/notify              {message, level, ack, confirm}
 *   POST /atak/toc/api/phones/{ref}/ring                {seconds, mode, confirm}
 *   POST /atak/toc/api/phones/{ref}/ring-stop
 *   POST /atak/toc/api/phones/{ref}/locate
 *   POST /atak/toc/api/phones/{ref}/triangulate
 * Lecture : droit du poste (atak.toc.use). Actions : droit dédié atak.toc.phone + fonction activée par la communauté +
 * jeton CSRF + limites de débit ; chaque demande est journalisée. Un téléphone d'une autre communauté, ou hors de la vue
 * de l'opérateur, répond « introuvable ». Aucun compte Steam ne sort : les téléphones sont des références opaques.
 */
final class AtakTocPhoneController extends AtakTocBaseController
{
    private TocPhoneService $phones;

    public function __construct(?TocAccess $access = null, ?TocPhoneService $phones = null)
    {
        parent::__construct($access);
        $this->phones = $phones ?? new TocPhoneService(
            null,
            null,
            null,
            fn (int $tenantId, int $mapId, int $userId): ?array => $this->visibleUnits($tenantId, $mapId, $userId),
            static function (int $tenantId, int $mapId): array {
                try {
                    return (new \App\Repositories\AtakRelayRepository())->listForMap($tenantId, $mapId);
                } catch (\Throwable) {
                    return [];
                }
            }
        );
    }

    /**
     * Unités vues par l'opérateur (mêmes lignes que /api/atak/units?toc=1). Vue brouillée ou chiffrée : null, le poste
     * n'agit pas sur des positions qu'il ne lit pas clairement.
     *
     * @return list<array<string, mixed>>|null
     */
    private function visibleUnits(int $tenantId, int $mapId, int $userId): ?array
    {
        try {
            $atak = new \App\Repositories\AtakDataRepository();
            $intel = new \App\Services\Tactical\AtakIntelViewService(null, null, $atak);
            $view = $intel->resolveViewerMode($tenantId, $mapId, $intel->identityFromRequest(null, $userId > 0 ? $userId : null));
            if (($view['mode'] ?? '') !== \App\Services\Tactical\AtakIntelViewService::MODE_CLEAR) {
                return null;
            }
            $units = $atak->getUnits($tenantId, $mapId);
            if ($mapId !== 1) {
                // Les téléphones suivis par le mod sont sur la carte 1 (comme /api/atak/units) : le plus vivant gagne.
                $rank = ['linked' => 3, 'delayed' => 2, 'offline' => 1];
                $by = [];
                foreach (array_merge($atak->getUnits($tenantId, 1), $units) as $u) {
                    if (!is_array($u)) {
                        continue;
                    }
                    $k = strtolower(trim((string) ($u['call_sign'] ?? ''))) ?: ('id:' . (string) ($u['id'] ?? count($by)));
                    if (!isset($by[$k]) || ($rank[(string) ($u['status'] ?? '')] ?? 0) > ($rank[(string) ($by[$k]['status'] ?? '')] ?? 0)) {
                        $by[$k] = $u;
                    }
                }
                $units = array_values($by);
            }

            return array_values(array_filter($units, 'is_array'));
        } catch (\Throwable) {
            return [];
        }
    }

    private function world(int $mapId): string
    {
        try {
            $m = (new \App\Repositories\AtakMapRepository())->getById($mapId);

            return (string) ($m['world_name'] ?? $m['slug'] ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    /** Refus du droit dédié (la lecture reste ouverte à qui tient le poste). */
    private function guardPhone(): ?Response
    {
        if (!$this->access->canPhone()) {
            return Response::json(['ok' => false, 'error' => 'forbidden_phone'], 403);
        }

        return null;
    }

    /** @param array<string, mixed> $res */
    private function result(array $res, string $action): Response
    {
        if (!empty($res['ok'])) {
            return Response::json($res + ['now' => time()]);
        }
        $code = (string) ($res['error'] ?? 'error');
        $status = match ($code) {
            'not_ready' => 503,
            'disabled' => 403,
            'target_unknown' => 404,
            'view_degraded' => 409,
            'rate_limited', 'too_many_pending', 'too_many_triangulations' => 429,
            'send_failed' => 502,
            default => 422,
        };
        $r = Response::json(['ok' => false, 'action' => $action] + $res, $status);
        if (isset($res['retry_after'])) {
            $r->header('Retry-After', (string) (int) $res['retry_after']);
        }

        return $r;
    }

    public function index(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        $tenantId = $this->tenantId();
        $mapId = $this->mapId($request);
        $status = $this->phones->status($tenantId);
        $base = [
            'ok' => true, 'mapId' => $mapId, 'now' => time(),
            'ready' => $status['ready'], 'enabled' => $status['enabled'], 'hide_hostile_journal' => $status['hide_hostile_journal'],
            'canPhone' => $this->access->canPhone(), 'canManage' => $this->access->canManage(),
            'limits' => [
                'message_max' => TocPhoneService::MESSAGE_MAX, 'locate_gap_s' => TocPhoneService::LOCATE_GAP_S,
                'notify_per_min' => TocPhoneService::NOTIFY_PER_MIN, 'ring_min_s' => TocPhoneService::RING_MIN_S, 'ring_max_s' => TocPhoneService::RING_MAX_S,
                'tri_running' => TocPhoneService::TRI_RUNNING_COMMUNITY,
            ],
        ];
        $dir = $this->phones->directory($tenantId, $mapId, $this->userId(), $this->side($request));
        if (!$dir['ok']) {
            return Response::json($base + ['phones' => [], 'warning' => $dir['error'] ?? 'view_degraded']);
        }

        return Response::json($base + ['phones' => $dir['phones']])->header('Cache-Control', 'private, no-store');
    }

    public function events(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        $ref = $request->query('ref');
        $ref = is_string($ref) && preg_match('/^p[0-9a-f]{12}$/', $ref) === 1 ? $ref : null;

        return Response::json(['ok' => true, 'now' => time(), 'events' => $this->phones->events($this->tenantId(), $ref, 60)])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        $res = $this->phones->sheet($this->tenantId(), $this->mapId($request), $this->userId(), $this->side($request), (string) ($params['ref'] ?? ''));

        return $res['ok'] ? Response::json($res + ['now' => time()])->header('Cache-Control', 'private, no-store') : $this->result($res, 'sheet');
    }

    public function settings(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        if (!$this->access->canManage()) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        if (($deny = $this->guardWrite($request, 'phone-settings', 20)) !== null) {
            return $deny;
        }
        $b = $this->body();
        $res = $this->phones->saveSettings($this->tenantId(), $this->userId(), $this->actorLabel(), !empty($b['enabled']), !empty($b['hide_hostile_journal']));

        return $res['ok'] ? Response::json(['ok' => true] + $this->phones->status($this->tenantId())) : $this->result($res, 'settings');
    }

    /** Écriture sur un téléphone : droit dédié, CSRF, débit général de l'opérateur (les limites propres sont dans le service). */
    private function writeGuard(Request $request): ?Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        if (($deny = $this->guardPhone()) !== null) {
            return $deny;
        }

        return $this->guardWrite($request, 'phone', 40);
    }

    public function notify(Request $request, array $params = []): Response
    {
        if (($deny = $this->writeGuard($request)) !== null) {
            return $deny;
        }
        $b = $this->body();
        $res = $this->phones->notify(
            $this->tenantId(), $this->mapId($request), $this->userId(), $this->actorLabel(), $this->side($request), (string) ($params['ref'] ?? ''),
            is_string($b['message'] ?? null) ? $b['message'] : '', is_string($b['level'] ?? null) ? $b['level'] : 'info', !empty($b['ack']), !empty($b['confirm'])
        );

        return $this->result($res, 'notify');
    }

    public function ring(Request $request, array $params = []): Response
    {
        if (($deny = $this->writeGuard($request)) !== null) {
            return $deny;
        }
        $b = $this->body();
        $res = $this->phones->ring(
            $this->tenantId(), $this->mapId($request), $this->userId(), $this->actorLabel(), $this->side($request), (string) ($params['ref'] ?? ''),
            (int) ($b['seconds'] ?? 0), is_string($b['mode'] ?? null) ? $b['mode'] : 'ring', !empty($b['confirm'])
        );

        return $this->result($res, 'ring');
    }

    public function ringStop(Request $request, array $params = []): Response
    {
        if (($deny = $this->writeGuard($request)) !== null) {
            return $deny;
        }

        return $this->result($this->phones->ringStop($this->tenantId(), $this->mapId($request), $this->userId(), $this->actorLabel(), $this->side($request), (string) ($params['ref'] ?? '')), 'ring_stop');
    }

    public function locate(Request $request, array $params = []): Response
    {
        if (($deny = $this->writeGuard($request)) !== null) {
            return $deny;
        }

        return $this->result($this->phones->locate($this->tenantId(), $this->mapId($request), $this->userId(), $this->actorLabel(), $this->side($request), (string) ($params['ref'] ?? '')), 'locate');
    }

    public function triangulate(Request $request, array $params = []): Response
    {
        if (($deny = $this->writeGuard($request)) !== null) {
            return $deny;
        }
        $mapId = $this->mapId($request);

        return $this->result($this->phones->triangulate($this->tenantId(), $mapId, $this->userId(), $this->actorLabel(), $this->side($request), (string) ($params['ref'] ?? ''), $this->world($mapId)), 'triangulate');
    }
}
