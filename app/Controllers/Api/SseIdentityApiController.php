<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseCaseRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Repositories\TenantRepository;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseEditorLibraryService;
use App\Services\Sse\SseIdCheckService;
use App\Services\Sse\SseIdDocumentLookupService;
use App\Services\Sse\SseIdentityPackService;
use App\Services\Sse\SseIdGameCoverService;
use App\Services\Sse\SseIdWatchFromGameService;
use App\Support\ComspecApiKeyAuth;
use App\Support\HttpJsonBody;

/**
 * API COMSPEC IDENTITY (Lot 1) — lue par COMSPEC Link pour le mod SSE.
 *
 * - GET  /api/sse/identity/pack               → pack du serveur de jeu (vérité de jeu comprise)
 * - GET  /api/sse/identity/documents/lookup   → contrôle N3 en ligne d'un papier présenté
 * - POST /api/sse/identity/checks             → contrôle remonté du terrain (idempotent) ; avec kind = bolo | hvt :
 *                                                 avis né en jeu (BOLO, HVT désignée), créé ou mis à jour (lots 4 et 5) ;
 *                                                 avec kind = editor_… : bibliothèque de l'éditeur SSE (SseEditorLibraryService) ;
 *                                                 avec kind = cover_report : couverture née en jeu (SseIdGameCoverService)
 *
 * Accès : clé de jeu valable (communauté ou plateforme) — le serveur de jeu garde la vérité pour lui —, ou session du
 * portail avec l'habilitation SSE voulue (Confidentiel pour le pack, Encadrement pour le reste). Sans clé ni session :
 * seulement en développement local ouvert (même règle que les autres envois du jeu).
 */
final class SseIdentityApiController
{
    private const DEFAULT_MAP_ID = 1;

    /** @var array<string, mixed>|null */
    private ?array $jsonBodyCache = null;

    public function __construct(
        private ?SseIdentityPackService $pack = null,
        private ?SseIdDocumentLookupService $lookup = null,
        private ?SseIdCheckService $checks = null,
        private ?SseClearanceService $clearance = null,
        private ?TenantAtakConfigRepository $tenantAtakConfig = null,
        private ?TenantRepository $tenants = null,
        private ?\Closure $bodyReader = null,
        private ?SseIdWatchFromGameService $gameWatch = null,
        private ?SseEditorLibraryService $editorLibrary = null,
        private ?SseIdGameCoverService $gameCovers = null,
    ) {
        $this->gameWatch ??= new SseIdWatchFromGameService();
        $this->pack ??= new SseIdentityPackService();
        $this->lookup ??= new SseIdDocumentLookupService();
        $this->checks ??= new SseIdCheckService();
        $this->clearance ??= new SseClearanceService();
        $this->tenantAtakConfig ??= new TenantAtakConfigRepository();
        $this->tenants ??= new TenantRepository();
    }

    /** GET /api/sse/identity/pack?map=&op=&since=&offset=&limit= */
    public function pack(Request $request, array $params = []): Response
    {
        $tenantId = $this->guard($request, SseCaseRepository::CLASS_CONFIDENTIAL);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $offset = (int) ($request->query('offset') ?? 0);
        $limit = (int) ($request->query('limit') ?? SseIdentityPackService::DEFAULT_LIMIT);

        return Response::json($this->pack->build(
            $tenantId,
            $this->mapId($request),
            self::queryText($request, 'op'),
            self::queryText($request, 'since'),
            $offset,
            $limit > 0 ? $limit : SseIdentityPackService::DEFAULT_LIMIT
        ));
    }

    /** GET /api/sse/identity/documents/lookup?country=&type=&number=&last=&first=&dob= */
    public function lookup(Request $request, array $params = []): Response
    {
        $tenantId = $this->guard($request, SseCaseRepository::CLASS_COMMAND);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }

        return Response::json($this->lookup->lookup($tenantId, [
            'country' => self::queryText($request, 'country'),
            'type' => self::queryText($request, 'type'),
            'number' => self::queryText($request, 'number'),
            'last' => self::queryText($request, 'last'),
            'first' => self::queryText($request, 'first'),
            'dob' => self::queryText($request, 'dob'),
        ]));
    }

    /** POST /api/sse/identity/checks */
    public function checksStore(Request $request, array $params = []): Response
    {
        $tenantId = $this->guard($request, SseCaseRepository::CLASS_COMMAND);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $body = $this->jsonBody();
        if ($body === []) {
            return Response::json(['error' => 'payload_empty', 'message' => 'Contrôle vide.'], 422);
        }
        $map = $body['map'] ?? $body['mapId'] ?? $request->query('map') ?? $request->query('mapId');
        $mapId = is_numeric($map) && (int) $map > 0 ? (int) $map : self::DEFAULT_MAP_ID;

        if (in_array($body['kind'] ?? null, SseEditorLibraryService::KINDS, true)) {
            return $this->editorLibraryResponse($tenantId, $body);
        }

        if (($body['kind'] ?? null) === SseIdGameCoverService::KIND) {
            return $this->gameCoverResponse($tenantId, $body);
        }

        // BOLO / HVT nés en jeu (même canal COMSPEC Link SubmitIdCheck, réponse id + ref « WL-… »).
        if (in_array($body['kind'] ?? null, SseIdWatchFromGameService::KINDS, true)) {
            $watch = $this->gameWatch->store($tenantId, $mapId, $body);
            if (isset($watch['error'])) {
                return Response::json(['error' => $watch['error'], 'message' => 'Avis du jeu incomplet : rien n’a été enregistré.'], 422);
            }

            return Response::json(['id' => $watch['id'], 'ref' => $watch['ref'], 'duplicate' => $watch['duplicate']], $watch['duplicate'] ? 200 : 201);
        }

        $result = $this->checks->store($tenantId, $mapId, $body);
        if (isset($result['error'])) {
            return Response::json([
                'error' => $result['error'],
                'message' => 'Clé d’envoi manquante ou invalide : le contrôle n’a pas été enregistré.',
            ], 422);
        }

        return Response::json([
            'id' => $result['id'],
            'ref' => $result['ref'],
            'duplicate' => $result['duplicate'],
        ], $result['duplicate'] ? 200 : 201);
    }

    /**
     * Bibliothèque de l'éditeur SSE. Lire ou écrire demande un compte identifié (jeton de jeu ou session du portail) et
     * son habilitation : une clé de communauté seule ne dit pas qui appelle, et tout poste relié la reçoit. Elle suffit
     * pour signaler une découverte, qui ne révèle rien (le serveur de jeu n'a qu'elle).
     *
     * @param array<string, mixed> $body
     */
    private function editorLibraryResponse(int $tenantId, array $body): Response
    {
        $gameUser = ComspecApiKeyAuth::matchedUserId();
        $sessionUser = (int) (Session::get('user_id') ?? 0);
        $keyPresented = ComspecApiKeyAuth::extractPresentedKey() !== '';
        try {
            $library = $this->editorLibrary ??= new SseEditorLibraryService();
            if (($body['kind'] ?? '') !== 'editor_found') {
                $allowed = match (true) {
                    $gameUser !== null => $library->userAllowed($tenantId, $gameUser),
                    $keyPresented => false,
                    default => $sessionUser > 0 && $this->clearance->allows(SseCaseRepository::CLASS_CONFIDENTIAL),
                };
                if (!$allowed) {
                    return Response::json([
                        'error' => 'clearance_required',
                        'message' => 'Compte relié et habilitation Confidentiel requis pour la bibliothèque de l’éditeur.',
                    ], 403);
                }
            }
            $result = $library->handle($tenantId, $gameUser ?? ($sessionUser > 0 ? $sessionUser : null), $body);
        } catch (\PDOException) {
            // Étape de migration pas encore passée, base indisponible : le mod reste sur la bibliothèque du poste.
            return Response::json(['error' => 'unavailable', 'message' => 'Bibliothèque indisponible pour le moment.'], 503);
        }
        if (isset($result['error'])) {
            return Response::json(['error' => $result['error'], 'message' => 'Contenu refusé : rien n’a été enregistré.'], 422);
        }

        return Response::json($result);
    }

    /**
     * Couverture née en jeu : le compte est celui du jeton de jeu, jamais une donnée du corps. Sans jeton, rien n'est
     * écrit et le mod l'indique au joueur (« unlinked »).
     *
     * @param array<string, mixed> $body
     */
    private function gameCoverResponse(int $tenantId, array $body): Response
    {
        try {
            $result = ($this->gameCovers ??= new SseIdGameCoverService())->store($tenantId, ComspecApiKeyAuth::matchedUserId(), $body);
        } catch (\PDOException) {
            // Étape de migration 4110 pas encore passée, base indisponible : le mod garde la couverture sur le poste.
            return Response::json(['error' => 'unavailable', 'message' => 'Couverture non enregistrée pour le moment.'], 503);
        }
        if (isset($result['error'])) {
            return Response::json(['error' => $result['error'], 'message' => 'Couverture illisible : rien n’a été enregistré.'], 422);
        }

        return Response::json($result);
    }

    /**
     * Authentification, communauté et habilitation. Renvoie la communauté ou la réponse d'erreur.
     */
    private function guard(Request $request, string $level): int|Response
    {
        $keyPresented = ComspecApiKeyAuth::extractPresentedKey() !== '';
        $authOk = ComspecApiKeyAuth::armaInlineAuthOk();
        if ($keyPresented && !$authOk) {
            return Response::json(['error' => 'unauthorized', 'message' => 'Clé d’accès refusée.'], 401);
        }
        $userId = (int) (Session::get('user_id') ?? 0);
        if (!$keyPresented && $userId < 1 && !$authOk) {
            return Response::json(['error' => 'unauthorized', 'message' => 'Authentification terrain requise.'], 401);
        }

        $tenantId = $this->resolveTenantId($request);
        if ($tenantId === null) {
            return Response::json([
                'error' => 'tenant_context_required',
                'message' => 'Communauté non identifiée. Reliez le compte Athena en jeu, ou utilisez la clé d’accès fournie par votre administrateur.',
            ], 403);
        }
        if ($this->tenantAtakConfig->isMaintenanceEnabled($tenantId)) {
            $bypass = $userId > 0 && function_exists('can') && can('admin.access');
            if (!$bypass) {
                $message = $this->tenantAtakConfig->getMaintenanceMessage($tenantId);

                return Response::json([
                    'error' => 'maintenance',
                    'message' => $message !== '' ? $message : 'L’accès à la carte est suspendu pour le moment. Réessayez plus tard.',
                ], 503);
            }
        }

        // Session du portail sans clé de jeu : l'habilitation SSE décide.
        if (!$keyPresented && $userId > 0 && !$this->clearance->allows($level)) {
            return Response::json([
                'error' => 'clearance_required',
                'message' => 'Votre habilitation ne permet pas de consulter le registre d’identité.',
            ], 403);
        }

        return $tenantId;
    }

    private function resolveTenantId(Request $request): ?int
    {
        $matched = ComspecApiKeyAuth::matchedTenantId();
        if ($matched !== null && $matched > 0) {
            return $matched;
        }
        $sid = Session::get('tenant_id');
        if ($sid !== null && $sid !== '' && (int) $sid > 0) {
            return (int) $sid;
        }
        $q = $request->query('tenant_id');
        if ($q !== null && $q !== '' && (int) $q > 0) {
            return (int) $q;
        }
        $body = $request->isPost() ? $this->jsonBody() : [];
        if (!empty($body['tenant_id']) && (int) $body['tenant_id'] > 0) {
            return (int) $body['tenant_id'];
        }
        $slug = $request->query('tenant_slug');
        if (is_string($slug) && trim($slug) !== '') {
            $t = $this->tenants->findBySlug(trim($slug));

            return $t ? (int) $t['id'] : null;
        }

        return null;
    }

    private function mapId(Request $request): int
    {
        $map = $request->query('map') ?? $request->query('mapId');

        return is_numeric($map) && (int) $map > 0 ? (int) $map : self::DEFAULT_MAP_ID;
    }

    private static function queryText(Request $request, string $key): string
    {
        $v = $request->query($key);

        return is_scalar($v) ? mb_substr(trim((string) $v), 0, 120) : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonBody(): array
    {
        if ($this->jsonBodyCache !== null) {
            return $this->jsonBodyCache;
        }
        $raw = $this->bodyReader !== null ? (string) ($this->bodyReader)() : HttpJsonBody::rawJson();
        $decoded = $raw !== '' ? json_decode($raw, true) : null;
        $this->jsonBodyCache = is_array($decoded) ? $decoded : [];

        return $this->jsonBodyCache;
    }
}
