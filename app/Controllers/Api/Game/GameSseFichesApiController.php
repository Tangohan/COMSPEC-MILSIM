<?php

declare(strict_types=1);

namespace App\Controllers\Api\Game;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseAuditLogRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseFichesGameService;
use App\Services\Sse\SseIdCheckService;
use App\Services\Sse\SseLoreService;
use App\Support\ComspecApiKeyAuth;
use App\Support\ShortCache;
use App\Support\SseIdentityPackCache;

/**
 * Fiches du portail SSE pour le téléphone ATAK (ICD-SSE-FICHES-001) : GET /api/game/v1/sse/fiches (liste, ETag, since_rev,
 * format=sqf&page=), GET /api/game/v1/sse/fiches/{ref} (une fiche en entier), et les mêmes réponses par le canal « kind » de
 * POST /api/sse/identity/checks (SubmitIdCheck de COMSPEC Link, sans nouvelle DLL) : sse_fiches (op list, detail, wanted) et
 * sse_fiche_share (journal d'un partage de téléphone à téléphone).
 *
 * La communauté est celle de la clé présentée (clé de communauté ou jeton de jeu), ou celle de la session du portail : jamais
 * un paramètre de la requête. Le niveau de lecture est celui du compte du jeton de jeu (le plus bas sans compte). Lecture
 * seule, débit borné par communauté et par compte, journal d'accès et de partage.
 */
final class GameSseFichesApiController
{
    public const RATE_MAX = 120;
    public const RATE_WINDOW = 60;
    public const SHARE_MAX = 12;
    public const SHARE_WINDOW = 600;
    public const CACHE_TTL = 20;
    /** Une lecture de la liste n'est inscrite au journal qu'une fois par fenêtre (le détail et le partage, toujours). */
    public const AUDIT_LIST_WINDOW = 600;

    /** @var \Closure(string, int, int): bool */
    private \Closure $tooMany;

    /** @var \Closure(int): ?string */
    private \Closure $maintenance;

    /** @var \Closure(): ?string */
    private \Closure $sessionLevel;

    /** @var \Closure(array<string, mixed>): void */
    private \Closure $audit;

    /** @var \Closure(int, int): ?int */
    private \Closure $identityOf;

    /**
     * @param (\Closure(string, int, int): bool)|null $tooMany plafond de débit (clé, maximum, fenêtre en secondes)
     * @param (\Closure(int): ?string)|null $maintenance message de maintenance de la communauté, null si elle est ouverte
     * @param (\Closure(): ?string)|null $sessionLevel plafond de lecture de la session du portail (sans clé de jeu), null : refusé
     * @param (\Closure(array<string, mixed>): void)|null $audit écrit une ligne du journal d'audit SSE
     * @param (\Closure(int, int): ?int)|null $identityOf identité reconnue par un contrôle d'identité : (communauté, contrôle)
     */
    public function __construct(
        private ?SseFichesGameService $fiches = null,
        ?\Closure $tooMany = null,
        ?\Closure $maintenance = null,
        ?\Closure $sessionLevel = null,
        ?\Closure $audit = null,
        ?\Closure $identityOf = null,
    ) {
        $this->identityOf = $identityOf ?? static fn (int $tenantId, int $checkId): ?int => (new SseIdCheckService())->identityOf($tenantId, $checkId);
        $this->tooMany = $tooMany ?? static fn (string $key, int $max, int $window): bool => (new FileRateLimiter())->tooManyAttempts($key, $max, $window);
        $this->maintenance = $maintenance ?? static function (int $tenantId): ?string {
            $config = new TenantAtakConfigRepository();

            return $config->isMaintenanceEnabled($tenantId) ? $config->getMaintenanceMessage($tenantId) : null;
        };
        $this->sessionLevel = $sessionLevel ?? static fn (): ?string => (new SseClearanceService())->maxLevel();
        $this->audit = $audit ?? static function (array $row): void {
            (new SseAuditLogRepository())->write($row);
        };
    }

    /** GET /api/game/v1/sse/fiches?since_rev=&format=sqf&page=&ref= */
    public function show(Request $request, array $params = []): Response
    {
        $guard = $this->guard();
        if ($guard instanceof Response) {
            return $guard;
        }
        [$tenantId, $level, $who] = $guard;
        $ref = self::text($request->query('ref'));
        if ($ref !== '') {
            return $this->detailJson($tenantId, $level, $who, $ref);
        }
        $known = self::text($request->query('since_rev'));

        if (self::text($request->query('format')) === 'sqf') {
            return $this->paged($tenantId, $level, $who, $known, (int) ($request->query('page') ?? 0));
        }

        $pkg = $this->load($tenantId, $level);
        $revision = (string) $pkg['revision'];
        $etag = 'W/"ssefiches-' . $revision . '"';
        $this->journalList($tenantId, $level, $who, count($pkg['fiches']));
        if ($known !== '' && $known === $revision) {
            return Response::json([
                'version' => $pkg['version'], 'unchanged' => true, 'revision' => $revision,
                'generated_at' => $pkg['generated_at'], 'ttl' => $pkg['ttl'],
            ])->header('ETag', $etag)->header('Cache-Control', 'private, no-cache');
        }
        if (SseIdentityPackCache::ifNoneMatchHits($request->header('If-None-Match'), $etag)) {
            return (new Response())->setStatusCode(304)->header('ETag', $etag)->header('Cache-Control', 'private, no-cache');
        }

        return Response::json($pkg)->header('ETag', $etag)->header('Cache-Control', 'private, no-cache');
    }

    /** GET /api/game/v1/sse/fiches/{ref} (la référence peut porter « / » : encodée en %2F) */
    public function detail(Request $request, array $params = []): Response
    {
        $guard = $this->guard();
        if ($guard instanceof Response) {
            return $guard;
        }
        [$tenantId, $level, $who] = $guard;

        return $this->detailJson($tenantId, $level, $who, self::text(rawurldecode((string) ($params['ref'] ?? ''))));
    }

    /**
     * Canal « kind » : corps {kind: "sse_fiches", op: "list" | "detail" | "wanted", map, rev, page, ref, check} ou
     * {kind: "sse_fiche_share", ref, to, via} ; réponse {id, ref, page, next, pages} où « ref » est une page parseSimpleArray.
     *
     * @param array<string, mixed> $body
     */
    public function kindResponse(array $body): Response
    {
        $guard = $this->guard();
        if ($guard instanceof Response) {
            return $guard;
        }
        [$tenantId, $level, $who] = $guard;
        if (($body['kind'] ?? '') === SseFichesGameService::KIND_SHARE) {
            return $this->share($tenantId, $level, $who, $body);
        }
        $op = self::text($body['op'] ?? 'list');
        if ($op === 'detail') {
            $fiche = $this->service()->find($tenantId, $level, self::text($body['ref'] ?? ''));
            $this->journal($tenantId, $who, 'fiches.detail', self::text($body['ref'] ?? ''), $fiche !== null ? 'lue' : 'refusée ou inconnue', $level);
            if ($fiche === null) {
                return Response::json(['error' => 'not_found', 'message' => 'Fiche inconnue ou au-dessus de votre habilitation.'], 404);
            }
            $pkg = $this->load($tenantId, $level);

            return Response::json([
                'id' => SseFichesGameService::revisionId((string) $pkg['revision']),
                'ref' => SseFichesGameService::detailPage($pkg, $fiche),
                'page' => 0, 'next' => 0, 'pages' => 1,
            ]);
        }
        if ($op === 'wanted') {
            return $this->wantedResponse($tenantId, $who, (int) ($body['check'] ?? 0));
        }

        return $this->paged($tenantId, $level, $who, self::text($body['rev'] ?? ''), is_numeric($body['page'] ?? null) ? (int) $body['page'] : 0);
    }

    private function paged(int $tenantId, string $level, array $who, string $known, int $page): Response
    {
        $pkg = $this->load($tenantId, $level);
        $revision = (string) $pkg['revision'];
        $id = SseFichesGameService::revisionId($revision);
        if ($page === 0) {
            $this->journalList($tenantId, $level, $who, count($pkg['fiches']));
        }
        if ($page === 0 && $known !== '' && $known === $revision) {
            return Response::json(['id' => $id, 'ref' => SseFichesGameService::unchangedPage($revision), 'page' => 0, 'next' => 0, 'pages' => 0, 'unchanged' => true]);
        }
        $pages = SseFichesGameService::pages($pkg);
        $page = max(0, min($page, count($pages) - 1));

        return Response::json([
            'id' => $id,
            'ref' => $pages[$page],
            'page' => $page,
            'next' => $page + 1 < count($pages) ? $page + 1 : 0,
            'pages' => count($pages),
        ]);
    }

    private function detailJson(int $tenantId, string $level, array $who, string $ref): Response
    {
        $fiche = $this->service()->find($tenantId, $level, $ref);
        $this->journal($tenantId, $who, 'fiches.detail', $ref, $fiche !== null ? 'lue' : 'refusée ou inconnue', $level);
        if ($fiche === null) {
            return Response::json(['error' => 'not_found', 'message' => 'Fiche inconnue ou au-dessus de votre habilitation.'], 404);
        }

        return Response::json(['version' => SseFichesGameService::VERSION, 'level' => $level, 'fiche' => $fiche])->header('Cache-Control', 'private, no-cache');
    }

    /**
     * Partage d'une fiche de téléphone à téléphone : le serveur ne transporte rien, il vérifie que l'expéditeur peut lire la fiche
     * (sinon refus), borne le débit et inscrit le partage au journal. Le destinataire résout la fiche selon SES droits.
     *
     * @param array{user: int, label: string, key: string} $who
     * @param array<string, mixed> $body
     */
    private function share(int $tenantId, string $level, array $who, array $body): Response
    {
        $ref = self::text($body['ref'] ?? '');
        $to = self::text($body['to'] ?? '');
        $via = in_array($body['via'] ?? '', ['bt', 'nfc', 'sms'], true) ? (string) $body['via'] : 'bt';
        if ($ref === '' || $to === '') {
            return Response::json(['error' => 'invalid', 'message' => 'Fiche ou destinataire manquant.'], 422);
        }
        if (($this->tooMany)('sse_fiche_share|' . $tenantId . '|' . $who['key'], self::SHARE_MAX, self::SHARE_WINDOW)) {
            return Response::json(['error' => 'rate_limited', 'message' => 'Trop de partages de fiches : réessayez dans un moment.', 'retry_after' => self::SHARE_WINDOW], 429)
                ->header('Retry-After', (string) self::SHARE_WINDOW);
        }
        $fiche = $this->service()->find($tenantId, $level, $ref);
        if ($fiche === null) {
            $this->journal($tenantId, $who, 'fiches.share_refused', $ref, 'vers ' . $to . ' (' . $via . ')', $level);

            return Response::json(['error' => 'forbidden', 'message' => 'Cette fiche n’est pas lisible avec votre habilitation : elle ne se partage pas.'], 403);
        }
        $this->journal($tenantId, $who, 'fiches.share', $ref, 'vers ' . $to . ' (' . $via . ')', $level, ['classification' => $fiche['level']]);

        return Response::json([
            'id' => 1,
            'ref' => SseLoreService::sqf([SseFichesGameService::VERSION, 'share', (string) $fiche['ref'], (string) $fiche['level'], true]),
            'page' => 0, 'next' => 0, 'pages' => 1,
        ]);
    }

    /**
     * Avis de recherche de l'identité reconnue par un contrôle d'identité, pour la marque « RECHERCHÉ » du SEEK II / NCIC.
     *
     * @param array{user: int, label: string, key: string} $who
     */
    private function wantedResponse(int $tenantId, array $who, int $checkId): Response
    {
        if ($checkId < 1) {
            return Response::json(['error' => 'invalid', 'message' => 'Contrôle manquant.'], 422);
        }
        $identity = null;
        try {
            $identity = ($this->identityOf)($tenantId, $checkId);
        } catch (\Throwable) {
            $identity = null;
        }
        $w = $this->service()->wantedFor($tenantId, $identity);
        if ($w['wanted']) {
            $this->journal($tenantId, $who, 'fiches.wanted_hit', (string) ($w['ref'] ?? ''), 'contrôle ' . $checkId, SseFichesGameService::NOTICE_LEVEL);
        }

        return Response::json([
            'id' => $checkId,
            'ref' => SseLoreService::sqf([SseFichesGameService::VERSION, 'wanted', $w['wanted'], $w['ref'] ?? '', $w['type'] ?? '', $w['threat'] ?? '', $w['armed'] ?? '', $w['instruction'] ?? '']),
            'page' => 0, 'next' => 0, 'pages' => 1,
        ]);
    }

    private function service(): SseFichesGameService
    {
        return $this->fiches ??= new SseFichesGameService();
    }

    /** @return array<string, mixed> */
    private function load(int $tenantId, string $level): array
    {
        // Gardé quelques secondes : les pages d'une même lecture viennent du même paquet. La clé porte la communauté ET le niveau :
        // deux porteurs de niveaux différents ne partagent jamais un paquet.
        return ShortCache::remember(
            'sse_game_fiches:v' . SseFichesGameService::VERSION . ':' . $tenantId . ':' . $level,
            self::CACHE_TTL,
            fn (): array => $this->service()->build($tenantId, $level)
        );
    }

    /**
     * Authentification, communauté, niveau de lecture, maintenance et débit.
     *
     * @return array{0: int, 1: string, 2: array{user: int, label: string, key: string}}|Response communauté, niveau, auteur ; ou la réponse d'erreur
     */
    private function guard(): array|Response
    {
        $keyPresented = ComspecApiKeyAuth::extractPresentedKey() !== '';
        if ($keyPresented && !ComspecApiKeyAuth::armaInlineAuthOk()) {
            return Response::json(['error' => 'unauthorized', 'message' => 'Clé d’accès refusée.'], 401);
        }
        $sessionUser = (int) (Session::get('user_id') ?? 0);
        $tenantId = $keyPresented ? (int) (ComspecApiKeyAuth::matchedTenantId() ?? 0) : 0;
        $userId = $keyPresented ? (int) (ComspecApiKeyAuth::matchedUserId() ?? 0) : $sessionUser;
        $level = SseFichesGameService::NOTICE_LEVEL;
        if (!$keyPresented) {
            if ($sessionUser < 1) {
                return Response::json(['error' => 'unauthorized', 'message' => 'Authentification terrain requise.'], 401);
            }
            $tenantId = (int) (Session::get('tenant_id') ?? 0);
            $max = ($this->sessionLevel)();
            if ($tenantId > 0 && $max === null) {
                return Response::json(['error' => 'clearance_required', 'message' => 'Votre habilitation ne permet pas de consulter les fiches SSE.'], 403);
            }
            $level = (string) $max;
        }
        if ($tenantId < 1) {
            return Response::json([
                'error' => 'tenant_context_required',
                'message' => 'Communauté non identifiée. Reliez le compte Athena en jeu, ou utilisez la clé d’accès fournie par votre administrateur.',
            ], 403);
        }
        if ($keyPresented) {
            // Le niveau est celui du compte relié en jeu ; sans compte (clé de communauté seule) : le plus bas.
            $level = $this->service()->levelFor($tenantId, $userId > 0 ? $userId : null);
        }

        $message = ($this->maintenance)($tenantId);
        if ($message !== null && !($sessionUser > 0 && function_exists('can') && can('admin.access'))) {
            return Response::json([
                'error' => 'maintenance',
                'message' => $message !== '' ? $message : 'L’accès à la carte est suspendu pour le moment. Réessayez plus tard.',
            ], 503);
        }

        $key = $userId > 0 ? 'u' . $userId : 'k';
        if (($this->tooMany)('sse_game_fiches|' . $tenantId . '|' . $key, self::RATE_MAX, self::RATE_WINDOW)) {
            return Response::json(['error' => 'too_many_requests', 'message' => 'Trop de lectures des fiches : réessayez dans un moment.', 'retry_after' => self::RATE_WINDOW], 429)
                ->header('Retry-After', (string) self::RATE_WINDOW);
        }

        return [$tenantId, $level, ['user' => $userId, 'label' => $keyPresented ? 'Téléphone ATAK' : 'Session du portail', 'key' => $key]];
    }

    /** @param array{user: int, label: string, key: string} $who */
    private function journalList(int $tenantId, string $level, array $who, int $count): void
    {
        // Une inscription par fenêtre et par porteur : la liste est relue à chaque ouverture de l'app.
        ShortCache::remember('sse_fiches_audit:' . $tenantId . ':' . $who['key'] . ':' . $level, self::AUDIT_LIST_WINDOW, function () use ($tenantId, $who, $level, $count): bool {
            $this->journal($tenantId, $who, 'fiches.read', '', $count . ' fiche(s) servies', $level);

            return true;
        });
    }

    /**
     * @param array{user: int, label: string, key: string} $who
     * @param array<string, mixed> $after
     */
    private function journal(int $tenantId, array $who, string $action, string $ref, string $reason, string $level, array $after = []): void
    {
        try {
            ($this->audit)([
                'tenant_id' => $tenantId,
                'actor_user_id' => $who['user'] > 0 ? $who['user'] : null,
                'actor_label' => $who['label'],
                'action' => $action,
                'object_type' => 'game_fiche',
                'object_uuid' => $ref !== '' ? mb_substr($ref, 0, 36) : null,
                'reason' => $reason,
                'after' => ['level' => $level] + $after,
            ]);
        } catch (\Throwable) {
            // Le journal ne bloque pas la lecture d'une fiche (table pas encore migrée, base indisponible).
        }
    }

    private static function text(mixed $v): string
    {
        return is_scalar($v) ? mb_substr(trim((string) $v), 0, 200) : '';
    }
}
