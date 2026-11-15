<?php

declare(strict_types=1);

namespace App\Controllers\Api\Game;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseCaseRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseGameCatalogueService;
use App\Support\ComspecApiKeyAuth;
use App\Support\ShortCache;
use App\Support\SseIdentityPackCache;

/**
 * Catalogue du mod SSE (ICD-SSE-CATALOGUE-001) : GET /api/game/v1/sse/catalogue, et la même réponse par le canal
 * « kind » de POST /api/sse/identity/checks (SubmitIdCheck de COMSPEC Link, sans nouvelle DLL).
 *
 * La communauté est celle de la clé présentée (clé de communauté ou jeton de jeu), ou celle de la session du portail :
 * jamais un paramètre de la requête. Lecture seule, débit borné par communauté et par compte.
 */
final class GameSseCatalogueApiController
{
    public const CACHE_TTL = 30;
    public const RATE_MAX = 90;
    public const RATE_WINDOW = 60;

    /** @var \Closure(string, int, int): bool */
    private \Closure $tooMany;

    /** @var \Closure(int): ?string */
    private \Closure $maintenance;

    /** @var \Closure(): bool */
    private \Closure $sessionAllowed;

    /**
     * @param (\Closure(string, int, int): bool)|null $tooMany plafond de débit (clé, maximum, fenêtre en secondes)
     * @param (\Closure(int): ?string)|null $maintenance message de maintenance de la communauté, null si elle est ouverte
     * @param (\Closure(): bool)|null $sessionAllowed la session du portail a-t-elle l'habilitation SSE (sans clé de jeu) ?
     */
    public function __construct(
        private ?SseGameCatalogueService $catalogue = null,
        ?\Closure $tooMany = null,
        ?\Closure $maintenance = null,
        ?\Closure $sessionAllowed = null,
    ) {
        $this->tooMany = $tooMany ?? static fn (string $key, int $max, int $window): bool => (new FileRateLimiter())->tooManyAttempts($key, $max, $window);
        $this->maintenance = $maintenance ?? static function (int $tenantId): ?string {
            $config = new TenantAtakConfigRepository();

            return $config->isMaintenanceEnabled($tenantId) ? $config->getMaintenanceMessage($tenantId) : null;
        };
        $this->sessionAllowed = $sessionAllowed ?? static fn (): bool => (new SseClearanceService())->allows(SseCaseRepository::CLASS_INTERNAL);
    }

    /** GET /api/game/v1/sse/catalogue?map=&op=&sections=&since_rev=&format=sqf&page= */
    public function show(Request $request, array $params = []): Response
    {
        $tenantId = $this->guard();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $map = $request->query('map') ?? $request->query('mapId');
        $mapId = is_numeric($map) && (int) $map > 0 ? (int) $map : 1;
        $op = self::text($request->query('op'));
        $known = self::text($request->query('since_rev'));

        if (self::text($request->query('format')) === 'sqf') {
            return $this->paged($tenantId, $mapId, $op, $known, (int) ($request->query('page') ?? 0));
        }

        $catalogue = $this->load($tenantId, $mapId, $op);
        $revision = (string) $catalogue['revision'];
        $etag = 'W/"ssecat-' . $revision . '"';
        if ($known !== '' && $known === $revision) {
            return Response::json([
                'version' => $catalogue['version'], 'unchanged' => true, 'revision' => $revision,
                'generated_at' => $catalogue['generated_at'], 'ttl' => $catalogue['ttl'],
            ])->header('ETag', $etag)->header('Cache-Control', 'private, no-cache');
        }
        if (SseIdentityPackCache::ifNoneMatchHits($request->header('If-None-Match'), $etag)) {
            return (new Response())->setStatusCode(304)->header('ETag', $etag)->header('Cache-Control', 'private, no-cache');
        }
        $sections = array_values(array_filter(array_map('trim', explode(',', self::text($request->query('sections'))))));

        return Response::json(SseGameCatalogueService::only($catalogue, $sections))
            ->header('ETag', $etag)->header('Cache-Control', 'private, no-cache');
    }

    /**
     * Canal « kind » : corps {kind: "sse_catalogue", map, op, rev, page} ; réponse {id, ref, page, next, pages} où
     * « ref » est une page parseSimpleArray (voir SseGameCatalogueService::pages()).
     *
     * @param array<string, mixed> $body
     */
    public function kindResponse(array $body): Response
    {
        $tenantId = $this->guard();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $map = $body['map'] ?? $body['mapId'] ?? null;

        return $this->paged(
            $tenantId,
            is_numeric($map) && (int) $map > 0 ? (int) $map : 1,
            self::text($body['op'] ?? ''),
            self::text($body['rev'] ?? ''),
            is_numeric($body['page'] ?? null) ? (int) $body['page'] : 0
        );
    }

    private function paged(int $tenantId, int $mapId, string $op, string $known, int $page): Response
    {
        $catalogue = $this->load($tenantId, $mapId, $op);
        $revision = (string) $catalogue['revision'];
        $id = SseGameCatalogueService::revisionId($revision);
        if ($page === 0 && $known !== '' && $known === $revision) {
            return Response::json(['id' => $id, 'ref' => SseGameCatalogueService::unchangedPage($revision), 'page' => 0, 'next' => 0, 'pages' => 0, 'unchanged' => true]);
        }
        $pages = SseGameCatalogueService::pages($catalogue);
        $page = max(0, min($page, count($pages) - 1));

        return Response::json([
            'id' => $id,
            'ref' => $pages[$page],
            'page' => $page,
            'next' => $page + 1 < count($pages) ? $page + 1 : 0,
            'pages' => count($pages),
        ]);
    }

    /** @return array<string, mixed> */
    private function load(int $tenantId, int $mapId, string $op): array
    {
        $user = (int) (ComspecApiKeyAuth::matchedUserId() ?? 0);

        // Gardé quelques secondes : les pages d'une même lecture viennent du même paquet, et dix postes qui ouvrent le
        // même module ne refont pas dix fois les requêtes.
        return ShortCache::remember(
            'sse_game_catalogue:v' . SseGameCatalogueService::VERSION . ':' . $tenantId . ':' . $mapId . ':' . sha1($op) . ':' . $user,
            self::CACHE_TTL,
            fn (): array => ($this->catalogue ??= new SseGameCatalogueService())->build($tenantId, $mapId, $op, $user > 0 ? $user : null)
        );
    }

    /**
     * Authentification, communauté, maintenance et débit. Renvoie la communauté ou la réponse d'erreur.
     */
    private function guard(): int|Response
    {
        $keyPresented = ComspecApiKeyAuth::extractPresentedKey() !== '';
        if ($keyPresented && !ComspecApiKeyAuth::armaInlineAuthOk()) {
            return Response::json(['error' => 'unauthorized', 'message' => 'Clé d’accès refusée.'], 401);
        }
        $userId = (int) (Session::get('user_id') ?? 0);
        $tenantId = $keyPresented ? (int) (ComspecApiKeyAuth::matchedTenantId() ?? 0) : 0;
        if (!$keyPresented) {
            if ($userId < 1) {
                return Response::json(['error' => 'unauthorized', 'message' => 'Authentification terrain requise.'], 401);
            }
            $tenantId = (int) (Session::get('tenant_id') ?? 0);
            if ($tenantId > 0 && !($this->sessionAllowed)()) {
                return Response::json(['error' => 'clearance_required', 'message' => 'Votre habilitation ne permet pas de consulter le catalogue SSE.'], 403);
            }
        }
        if ($tenantId < 1) {
            return Response::json([
                'error' => 'tenant_context_required',
                'message' => 'Communauté non identifiée. Reliez le compte Athena en jeu, ou utilisez la clé d’accès fournie par votre administrateur.',
            ], 403);
        }

        $message = ($this->maintenance)($tenantId);
        if ($message !== null && !($userId > 0 && function_exists('can') && can('admin.access'))) {
            return Response::json([
                'error' => 'maintenance',
                'message' => $message !== '' ? $message : 'L’accès à la carte est suspendu pour le moment. Réessayez plus tard.',
            ], 503);
        }

        $who = $keyPresented ? (int) (ComspecApiKeyAuth::matchedUserId() ?? 0) : $userId;
        if (($this->tooMany)('sse_game_catalogue|' . $tenantId . '|' . $who, self::RATE_MAX, self::RATE_WINDOW)) {
            return Response::json(['error' => 'too_many_requests', 'message' => 'Trop de lectures du catalogue : réessayez dans un moment.', 'retry_after' => self::RATE_WINDOW], 429)
                ->header('Retry-After', (string) self::RATE_WINDOW);
        }

        return $tenantId;
    }

    private static function text(mixed $v): string
    {
        return is_scalar($v) ? mb_substr(trim((string) $v), 0, 200) : '';
    }
}
