<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ArsenalWardrobeRepository;
use App\Repositories\UserRepository;
use App\Support\AtakArmaWriteGuard;
use App\Support\ComspecApiKeyAuth;
use App\Support\SteamId;

/**
 * Sync ACE Arsenal wardrobes + collections d’équipement (jeu + web).
 */
final class AtakWardrobeApiController
{
    /** @var array<string, mixed>|null */
    private ?array $jsonBodyCache = null;

    public function __construct(
        private ?ArsenalWardrobeRepository $repo = null,
        private ?UserRepository $users = null,
        private ?AtakArmaWriteGuard $armaGuard = null,
    ) {
        $this->repo ??= new ArsenalWardrobeRepository();
        $this->users ??= new UserRepository();
        $this->armaGuard ??= new AtakArmaWriteGuard($this->users);
    }

    public function index(Request $request, array $params = []): Response
    {
        $ctx = $this->resolveContext($request, false);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        if (!$this->repo->tablesReady()) {
            return Response::json(['ok' => false, 'error' => 'migration_required'], 503);
        }

        $collectionId = (int) ($request->query('collection_id') ?? 0);
        $includePayload = filter_var($request->query('include_payload', '0'), FILTER_VALIDATE_BOOLEAN);
        $wardrobes = $this->repo->listAccessibleWardrobes($ctx['tenant_id'], $ctx['user_id']);
        if ($collectionId > 0) {
            $wardrobes = array_values(array_filter(
                $wardrobes,
                static fn (array $w): bool => (int) ($w['collection_id'] ?? 0) === $collectionId
            ));
        }

        $out = [];
        foreach ($wardrobes as $w) {
            $item = $this->publicWardrobe($w, $includePayload);
            $out[] = $item;
        }

        return Response::json([
            'ok' => true,
            'wardrobes' => $out,
            'count' => count($out),
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $ctx = $this->resolveContext($request, false);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        if (!$this->repo->tablesReady()) {
            return Response::json(['ok' => false, 'error' => 'migration_required'], 503);
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            return Response::json(['ok' => false, 'error' => 'invalid_id'], 422);
        }
        $row = $this->repo->findWardrobe($ctx['tenant_id'], $id);
        if ($row === null) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        // Accessible: own or shared via collection visibility
        $accessible = $this->repo->listAccessibleWardrobes($ctx['tenant_id'], $ctx['user_id']);
        $ok = false;
        foreach ($accessible as $w) {
            if ((int) ($w['id'] ?? 0) === $id) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        return Response::json([
            'ok' => true,
            'wardrobe' => $this->publicWardrobe($row, true),
        ]);
    }

    public function sync(Request $request, array $params = []): Response
    {
        $ctx = $this->resolveContext($request, true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        if (!$this->repo->tablesReady()) {
            return Response::json(['ok' => false, 'error' => 'migration_required'], 503);
        }
        if (!$this->writeAllowed($request)) {
            return Response::json(['ok' => false, 'error' => 'csrf_invalid'], 419);
        }

        $body = $this->body($request);
        $items = $body['wardrobes'] ?? null;
        if (!is_array($items)) {
            // Single wardrobe upsert
            if (isset($body['name']) || isset($body['payload']) || isset($body['payload_text'])) {
                $items = [$body];
            } else {
                return Response::json(['ok' => false, 'error' => 'wardrobes_required'], 422);
            }
        }
        if (count($items) > 80) {
            return Response::json(['ok' => false, 'error' => 'too_many'], 422);
        }

        foreach ($items as &$item) {
            if (!is_array($item)) {
                continue;
            }
            if ($ctx['steam_uid'] !== null && $ctx['steam_uid'] !== '') {
                $item['steam_uid'] = $ctx['steam_uid'];
            }
            $collSlug = trim((string) ($item['collection_slug'] ?? ''));
            if ($collSlug !== '' && empty($item['collection_id'])) {
                $coll = $this->repo->findCollectionBySlug($ctx['tenant_id'], ArsenalWardrobeRepository::slugify($collSlug));
                if ($coll !== null) {
                    $item['collection_id'] = (int) $coll['id'];
                }
            }
        }
        unset($item);

        $result = $this->repo->upsertMany($ctx['tenant_id'], $ctx['user_id'], $items);
        $public = array_map(fn (array $w): array => $this->publicWardrobe($w, false), $result['wardrobes']);

        return Response::json([
            'ok' => true,
            'saved' => $result['saved'],
            'wardrobes' => $public,
        ]);
    }

    public function destroy(Request $request, array $params = []): Response
    {
        $ctx = $this->resolveContext($request, true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        if (!$this->writeAllowed($request)) {
            return Response::json(['ok' => false, 'error' => 'csrf_invalid'], 419);
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            return Response::json(['ok' => false, 'error' => 'invalid_id'], 422);
        }
        $ok = $this->repo->deleteWardrobe($ctx['tenant_id'], $ctx['user_id'], $id);

        return Response::json(['ok' => $ok]);
    }

    public function collections(Request $request, array $params = []): Response
    {
        $ctx = $this->resolveContext($request, $request->method() !== 'GET');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        if (!$this->repo->tablesReady()) {
            return Response::json(['ok' => false, 'error' => 'migration_required'], 503);
        }

        if ($request->method() === 'GET') {
            return Response::json([
                'ok' => true,
                'collections' => $this->repo->listCollections($ctx['tenant_id'], $ctx['user_id']),
            ]);
        }

        if (!$this->writeAllowed($request)) {
            return Response::json(['ok' => false, 'error' => 'csrf_invalid'], 419);
        }
        $body = $this->body($request);
        try {
            $collection = $this->repo->upsertCollection($ctx['tenant_id'], $ctx['user_id'], $body);
        } catch (\InvalidArgumentException $e) {
            return Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return Response::json(['ok' => true, 'collection' => $collection]);
    }

    public function destroyCollection(Request $request, array $params = []): Response
    {
        $ctx = $this->resolveContext($request, true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        if (!$this->writeAllowed($request)) {
            return Response::json(['ok' => false, 'error' => 'csrf_invalid'], 419);
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            return Response::json(['ok' => false, 'error' => 'invalid_id'], 422);
        }
        $ok = $this->repo->deleteCollection($ctx['tenant_id'], $ctx['user_id'], $id);

        return Response::json(['ok' => $ok]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function publicWardrobe(array $row, bool $includePayload): array
    {
        $out = [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'source' => (string) ($row['source'] ?? 'ace_arsenal'),
            'payload_format' => (string) ($row['payload_format'] ?? 'arma_loadout_str'),
            'payload_sha256' => (string) ($row['payload_sha256'] ?? ''),
            'payload_bytes' => (int) ($row['payload_bytes'] ?? strlen((string) ($row['payload_text'] ?? ''))),
            'collection_id' => $row['collection_id'] ?? null,
            'collection_name' => $row['collection_name'] ?? null,
            'collection_slug' => $row['collection_slug'] ?? null,
            'owner_label' => (string) ($row['owner_label'] ?? ''),
            'mine' => !empty($row['mine']),
            'notes' => $row['notes'] ?? null,
            'is_favorite' => !empty($row['is_favorite']),
            'last_synced_at' => $row['last_synced_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
        if ($includePayload) {
            $out['payload_text'] = (string) ($row['payload_text'] ?? '');
        }

        return $out;
    }

    /**
     * @return array{tenant_id:int, user_id:int, steam_uid:?string}|Response
     */
    private function resolveContext(Request $request, bool $requireWriteIdentity): array|Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'tenant_required'], 400);
        }

        if ($this->isGameClient()) {
            $actor = $this->armaGuard->assertActor(
                $request,
                $tenantId,
                $this->body($request),
                $requireWriteIdentity,
                $requireWriteIdentity ? 'wardrobe_write' : 'wardrobe_read'
            );
            if ($actor instanceof Response) {
                return $actor;
            }
            $steam = $actor['steam_uid'] ?? null;
            if (!is_string($steam) || $steam === '') {
                $steamRaw = trim((string) ($this->body($request)['steam_uid'] ?? $request->query('steam_uid') ?? ''));
                $steam = $steamRaw !== '' ? SteamId::normalize($steamRaw) : null;
            }
            if ($steam === null) {
                return Response::json(['ok' => false, 'error' => 'steam_uid_required'], 400);
            }
            $user = $this->users->findBySteamIdForTenant($tenantId, $steam);
            if ($user === null) {
                return Response::json(['ok' => false, 'error' => 'steam_not_linked'], 403);
            }

            return [
                'tenant_id' => $tenantId,
                'user_id' => (int) $user['id'],
                'steam_uid' => $steam,
            ];
        }

        $userId = (int) Session::get('user_id');
        if ($userId < 1) {
            return Response::json(['ok' => false, 'error' => 'unauthorized'], 401);
        }

        return [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'steam_uid' => null,
        ];
    }

    private function resolveTenantId(Request $request): int
    {
        $matched = ComspecApiKeyAuth::matchedTenantId();
        if ($matched !== null && $matched > 0) {
            return $matched;
        }
        $sessionTenant = (int) Session::get('tenant_id');
        if ($sessionTenant > 0) {
            return $sessionTenant;
        }
        $body = $this->body($request);
        if (!empty($body['tenant_id'])) {
            $n = (int) $body['tenant_id'];

            return $n > 0 ? $n : 0;
        }
        $q = $request->query('tenant_id');
        if ($q !== null && $q !== '') {
            $n = (int) $q;

            return $n > 0 ? $n : 0;
        }

        return 0;
    }

    private function writeAllowed(Request $request): bool
    {
        if ($this->isGameClient()) {
            return true;
        }
        $body = $this->body($request);
        $token = $request->input('_csrf_token')
            ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)
            ?? ($body['_csrf_token'] ?? null);

        return is_string($token) && Csrf::validate($token);
    }

    private function isGameClient(): bool
    {
        return ComspecApiKeyAuth::extractPresentedKey() !== '';
    }

    /** @return array<string, mixed> */
    private function body(Request $request): array
    {
        if ($this->jsonBodyCache !== null) {
            return $this->jsonBodyCache;
        }
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (str_contains((string) $contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw ?: '[]', true);
            $this->jsonBodyCache = is_array($decoded) ? $decoded : [];
        } else {
            $this->jsonBodyCache = array_merge($request->all(), $_POST);
        }

        return $this->jsonBodyCache;
    }
}
