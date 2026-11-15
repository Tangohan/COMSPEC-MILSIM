<?php

declare(strict_types=1);

namespace App\Controllers\Api\Game;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseAlertRepository;
use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\SseAlertGameService;
use App\Services\Sse\SseAlertProposals;
use App\Services\Sse\SseAlertService;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseFichesGameService;
use App\Services\Sse\SseLoreService;
use App\Services\Sse\SseSheetAccess;
use App\Services\Sse\SseWantedService;
use App\Support\ComspecApiKeyAuth;
use App\Support\HttpJsonBody;
use App\Support\ShortCache;
use App\Support\SseIdentityPackCache;
use App\Support\SseSheetCatalog;
use App\Support\SseWantedCatalog;

/**
 * Alertes SSE pour le téléphone ATAK (ICD-SSE-ALERTES-001) : avis de recherche, notices de couleur et fiches de vigilance lus,
 * émis, renouvelés, levés, accusés et signalés depuis le jeu. Mêmes réponses par le canal « kind » de
 * POST /api/sse/identity/checks (kind « sse_alertes », pages parseSimpleArray, sans nouvelle DLL) et par les routes JSON
 * /api/game/v1/sse/alertes…
 *
 * Gardes de « sse_fiches » : la communauté est celle de la clé présentée (clé de communauté ou jeton de jeu) ou de la session du
 * portail, jamais un champ de la requête ; le niveau de lecture est celui du compte (le plus bas sans compte). Une écriture demande
 * un compte et l'étape de base 4830 ; les droits d'émettre et de lever sont ceux du site (SseAlertService::accessForUser). Une
 * alerte hors habilitation répond comme une référence inconnue, pour toutes les opérations.
 */
final class GameSseAlertesApiController
{
    public const READ_MAX = 120;
    public const READ_WINDOW = 60;
    public const WRITE_WINDOW = 600;
    /** Plafonds des écritures par compte sur WRITE_WINDOW : groupe => maximum (« renew » et « lift » partagent le leur). */
    public const WRITE_MAX = ['report' => 12, 'create' => 6, 'manage' => 12, 'ack' => 60];
    public const CACHE_TTL = 20;
    public const AUDIT_LIST_WINDOW = 600;
    public const ACTOR = 'Téléphone ATAK';

    /** @var \Closure(string, int, int): bool */
    private \Closure $tooMany;

    /** @var \Closure(int): ?string */
    private \Closure $maintenance;

    /** @var \Closure(): ?string */
    private \Closure $sessionLevel;

    /** @var \Closure(array<string, mixed>): void */
    private \Closure $audit;

    /** @var \Closure(int, int, string): SseSheetAccess */
    private \Closure $accessOf;

    /** @var \Closure(): string */
    private \Closure $bodyReader;

    /**
     * @param (\Closure(string, int, int): bool)|null $tooMany plafond de débit (clé, maximum, fenêtre en secondes)
     * @param (\Closure(int): ?string)|null $maintenance message de maintenance de la communauté, null si elle est ouverte
     * @param (\Closure(): ?string)|null $sessionLevel plafond de lecture de la session du portail (sans clé de jeu), null : refusé
     * @param (\Closure(array<string, mixed>): void)|null $audit écrit une ligne du journal d'audit SSE
     * @param (\Closure(int, int, string): SseSheetAccess)|null $accessOf droits du site d'un compte : (communauté, compte, habilitation)
     * @param (\Closure(): string)|null $bodyReader corps JSON brut des routes POST
     */
    public function __construct(
        private ?SseAlertGameService $service = null,
        ?\Closure $tooMany = null,
        ?\Closure $maintenance = null,
        ?\Closure $sessionLevel = null,
        ?\Closure $audit = null,
        ?\Closure $accessOf = null,
        private ?SseAlertProposals $proposals = null,
        private ?SseWantedService $wanted = null,
        ?\Closure $bodyReader = null,
    ) {
        $this->tooMany = $tooMany ?? static fn (string $key, int $max, int $window): bool => (new FileRateLimiter())->tooManyAttempts($key, $max, $window);
        $this->maintenance = $maintenance ?? static function (int $tenantId): ?string {
            $config = new TenantAtakConfigRepository();

            return $config->isMaintenanceEnabled($tenantId) ? $config->getMaintenanceMessage($tenantId) : null;
        };
        $this->sessionLevel = $sessionLevel ?? static fn (): ?string => (new SseClearanceService())->maxLevel();
        $this->audit = $audit ?? static function (array $row): void {
            (new SseAuditLogRepository())->write($row);
        };
        $this->accessOf = $accessOf ?? static fn (int $tenantId, int $userId, string $level): SseSheetAccess => SseAlertService::accessForUser($tenantId, $userId, $level, self::ACTOR);
        $this->bodyReader = $bodyReader ?? static fn (): string => HttpJsonBody::rawJson();
    }

    // ------------------------------------------------------------------ Routes JSON

    /** GET /api/game/v1/sse/alertes/catalog?since_rev=&format=sqf&page= */
    public function catalog(Request $request, array $params = []): Response
    {
        return $this->run(['op' => 'catalog', 'rev' => $request->query('since_rev'), 'page' => $request->query('page')], self::text($request->query('format')) !== 'sqf', $request);
    }

    /** GET /api/game/v1/sse/alertes?since_rev=&doc=&lang=&format=sqf&page= */
    public function index(Request $request, array $params = []): Response
    {
        return $this->run([
            'op' => 'list', 'rev' => $request->query('since_rev'), 'page' => $request->query('page'), 'doc' => $request->query('doc'), 'lang' => $request->query('lang'),
        ], self::text($request->query('format')) !== 'sqf', $request);
    }

    /** GET /api/game/v1/sse/alertes/propositions : état des propositions faites par le compte du jeton (PROP-n, proposal_state, avis créé, motif de refus). */
    public function proposalStates(Request $request, array $params = []): Response
    {
        return $this->run(['op' => 'proposals'], self::text($request->query('format')) !== 'sqf', $request);
    }

    /** GET /api/game/v1/sse/alertes/{ref} (la référence peut porter « / » : encodée en %2F) */
    public function detail(Request $request, array $params = []): Response
    {
        return $this->run(['op' => 'detail', 'ref' => self::refOf($params), 'lang' => $request->query('lang')], true);
    }

    /** POST /api/game/v1/sse/alertes */
    public function create(Request $request, array $params = []): Response
    {
        return $this->run(['op' => 'create'] + $this->body($request), true);
    }

    /** POST /api/game/v1/sse/alertes/{ref}/report */
    public function report(Request $request, array $params = []): Response
    {
        return $this->run(['op' => 'report', 'ref' => self::refOf($params), 'token' => ''] + $this->body($request), true);
    }

    /** POST /api/game/v1/sse/alertes/{ref}/renew */
    public function renew(Request $request, array $params = []): Response
    {
        return $this->run(['op' => 'renew', 'ref' => self::refOf($params)] + $this->body($request), true);
    }

    /** POST /api/game/v1/sse/alertes/{ref}/lift */
    public function lift(Request $request, array $params = []): Response
    {
        return $this->run(['op' => 'lift', 'ref' => self::refOf($params)] + $this->body($request), true);
    }

    /** POST /api/game/v1/sse/alertes/{ref}/ack */
    public function ack(Request $request, array $params = []): Response
    {
        return $this->run(['op' => 'ack', 'ref' => self::refOf($params)] + $this->body($request), true);
    }

    /**
     * Canal « kind » : corps {kind: "sse_alertes", op: "catalog" | "list" | "detail" | "proposals" | "create" | "report" | "renew" | "lift" | "ack", …} ;
     * réponse {id, ref, page, next, pages} où « ref » est une page parseSimpleArray.
     *
     * @param array<string, mixed> $body
     */
    public function kindResponse(array $body): Response
    {
        return $this->run($body, false);
    }

    // ------------------------------------------------------------------ Répartition

    /**
     * @param array<string, mixed> $body
     * @param bool $json true : objet JSON (routes REST) ; false : page parseSimpleArray (canal « kind »)
     */
    private function run(array $body, bool $json, ?Request $request = null): Response
    {
        $guard = $this->guard();
        if ($guard instanceof Response) {
            return $guard;
        }
        [$tenantId, $level, $who] = $guard;
        $op = self::text($body['op'] ?? 'list');
        $lang = ($body['lang'] ?? '') === 'en' ? 'en' : 'fr';
        $write = ['create' => 'create', 'report' => 'report', 'renew' => 'manage', 'lift' => 'manage', 'ack' => 'ack'][$op] ?? null;
        if ($write === null) {
            if (($this->tooMany)('sse_game_alertes|' . $tenantId . '|' . $who['key'], self::READ_MAX, self::READ_WINDOW)) {
                return self::limited('Trop de lectures des alertes : réessayez dans un moment.', self::READ_WINDOW);
            }

            return match ($op) {
                'catalog' => $this->catalogOp($tenantId, $level, $who, $body, $json, $request),
                'proposals' => $this->proposalsOp($tenantId, $who, $json),
                'detail' => $this->detailOp($tenantId, $level, $who, self::text($body['ref'] ?? ''), $lang, $json),
                default => $this->listOp($tenantId, $level, $who, $body, $lang, $json, $request),
            };
        }
        // Écritures : un compte, l'étape de base 4830, un débit borné par compte.
        if ($who['user'] < 1) {
            return self::error('account_required', 'Reliez votre compte Athena en jeu pour émettre, signaler ou accuser lecture d’une alerte.', 403);
        }
        if (!SseAlertRepository::ready()) {
            return self::error('unavailable', 'Cette action sera disponible dès que l’étape de base de données « alertes SSE » aura été passée par l’équipe technique.', 503);
        }
        if (($this->tooMany)('sse_game_alertes_' . $write . '|' . $tenantId . '|' . $who['key'], self::WRITE_MAX[$write], self::WRITE_WINDOW)) {
            return self::limited('Trop d’envois en peu de temps : réessayez dans quelques minutes.', self::WRITE_WINDOW);
        }
        $access = ($this->accessOf)($tenantId, $who['user'], $level);
        $result = match ($op) {
            'create' => $this->createOp($tenantId, $level, $who, $access, $body, $lang),
            'report' => $this->reportOp($tenantId, $level, $who, $body, $lang),
            'renew' => $this->renewOp($tenantId, $level, $who, $access, $body, $lang),
            'lift' => $this->liftOp($tenantId, $level, $who, $access, $body, $lang),
            default => $this->ackOp($tenantId, $level, $who, $body, $lang),
        };
        if ($result instanceof Response) {
            return $result;
        }
        ShortCache::bump('sse_game_alertes:' . $tenantId);

        return $json
            ? Response::json(['version' => SseAlertGameService::VERSION] + $result[1])->header('Cache-Control', 'private, no-store')
            : Response::json(['id' => 1, 'ref' => SseLoreService::sqf($result[0]), 'page' => 0, 'next' => 0, 'pages' => 1]);
    }

    // --------------------------------------------------------------------- Lectures

    /**
     * @param array{user: int, label: string, key: string} $who
     * @param array<string, mixed> $body
     */
    private function catalogOp(int $tenantId, string $level, array $who, array $body, bool $json, ?Request $request): Response
    {
        $catalog = $this->service()->catalog($tenantId);
        $revision = (string) $catalog['revision'];
        $known = self::text($body['rev'] ?? '');
        $rights = $this->rights($tenantId, $who, $level);
        if ($json) {
            $etag = 'W/"ssealertes-cat-' . $revision . '"';
            $named = array_combine(['vigilance', 'notice', 'lift', 'report'], $rights);
            if ($known !== '' && $known === $revision) {
                return Response::json(['version' => SseAlertGameService::VERSION, 'unchanged' => true, 'revision' => $revision, 'rights' => $named])
                    ->header('ETag', $etag)->header('Cache-Control', 'private, no-cache');
            }
            if ($request !== null && SseIdentityPackCache::ifNoneMatchHits($request->header('If-None-Match'), $etag)) {
                return (new Response())->setStatusCode(304)->header('ETag', $etag)->header('Cache-Control', 'private, no-cache');
            }

            return Response::json(['version' => SseAlertGameService::VERSION, 'revision' => $revision, 'rights' => $named] + SseAlertGameService::catalogSections($catalog))
                ->header('ETag', $etag)->header('Cache-Control', 'private, no-cache');
        }
        $id = SseAlertGameService::revisionId($revision);
        $page = is_numeric($body['page'] ?? null) ? (int) $body['page'] : 0;
        if ($page === 0 && $known !== '' && $known === $revision) {
            return Response::json(['id' => $id, 'ref' => SseAlertGameService::catalogUnchangedPage($revision, $rights), 'page' => 0, 'next' => 0, 'pages' => 0, 'unchanged' => true]);
        }
        $pages = SseAlertGameService::catalogPages($catalog, $rights);
        $page = max(0, min($page, count($pages) - 1));

        return Response::json(['id' => $id, 'ref' => $pages[$page], 'page' => $page, 'next' => $page + 1 < count($pages) ? $page + 1 : 0, 'pages' => count($pages)]);
    }

    /**
     * @param array{user: int, label: string, key: string} $who
     * @param array<string, mixed> $body
     */
    private function listOp(int $tenantId, string $level, array $who, array $body, string $lang, bool $json, ?Request $request): Response
    {
        $doc = in_array($body['doc'] ?? '', SseAlertRepository::DOCS, true) ? (string) $body['doc'] : '';
        // Gardé quelques secondes : les pages d'une même lecture viennent du même paquet. La clé porte la communauté, le niveau ET le
        // compte (accusés de lecture) ; toute écriture du jeu la périme.
        $pkg = ShortCache::remember(
            'sse_game_alertes:v' . SseAlertGameService::VERSION . ':' . $tenantId . ':' . ShortCache::gen('sse_game_alertes:' . $tenantId) . ':' . $level . ':' . $who['key'] . ':' . $lang . ':' . $doc,
            self::CACHE_TTL,
            fn (): array => $this->service()->build($tenantId, $level, $who['user'] > 0 ? $who['user'] : null, $lang, $doc)
        );
        $revision = (string) $pkg['revision'];
        $known = self::text($body['rev'] ?? '');
        $page = is_numeric($body['page'] ?? null) ? (int) $body['page'] : 0;
        if ($page === 0) {
            ShortCache::remember('sse_alertes_audit:' . $tenantId . ':' . $who['key'] . ':' . $level, self::AUDIT_LIST_WINDOW, function () use ($tenantId, $who, $level, $pkg): bool {
                $this->journal($tenantId, $who, 'alertes.read', '', count($pkg['alerts']) . ' alerte(s) servies', $level);

                return true;
            });
        }
        if ($json) {
            $etag = 'W/"ssealertes-' . $revision . '"';
            $head = ['version' => $pkg['version'], 'revision' => $revision, 'generated_at' => $pkg['generated_at'], 'ttl' => $pkg['ttl']];
            if ($known !== '' && $known === $revision) {
                return Response::json(['version' => $pkg['version'], 'unchanged' => true] + $head)->header('ETag', $etag)->header('Cache-Control', 'private, no-cache');
            }
            if ($request !== null && SseIdentityPackCache::ifNoneMatchHits($request->header('If-None-Match'), $etag)) {
                return (new Response())->setStatusCode(304)->header('ETag', $etag)->header('Cache-Control', 'private, no-cache');
            }

            return Response::json($head + [
                'level' => $pkg['level'], 'mark' => $pkg['mark'], 'alerts' => array_map([SseAlertGameService::class, 'publicAlert'], $pkg['alerts']),
                'total' => $pkg['total'], 'truncated' => $pkg['truncated'],
            ])->header('ETag', $etag)->header('Cache-Control', 'private, no-cache');
        }
        $id = SseAlertGameService::revisionId($revision);
        if ($page === 0 && $known !== '' && $known === $revision) {
            return Response::json(['id' => $id, 'ref' => SseAlertGameService::unchangedPage($revision), 'page' => 0, 'next' => 0, 'pages' => 0, 'unchanged' => true]);
        }
        $pages = SseAlertGameService::pages($pkg);
        $page = max(0, min($page, count($pages) - 1));

        return Response::json(['id' => $id, 'ref' => $pages[$page], 'page' => $page, 'next' => $page + 1 < count($pages) ? $page + 1 : 0, 'pages' => count($pages)]);
    }

    /** @param array{user: int, label: string, key: string} $who */
    /**
     * Opération « proposals » : propositions du compte, état pending|accepted|rejected, référence de l'avis créé si accepté, motif de refus.
     * Canal « kind » : [version, "proposals", [[PROP-n, doc, état, référence créée, motif de refus, décidée le, créée le, identifiant client]…]].
     *
     * @param array{user: int, label: string, key: string} $who
     */
    private function proposalsOp(int $tenantId, array $who, bool $json): Response
    {
        $rows = $who['user'] > 0 ? $this->service()->alerts()->alerts()->proposalsOf($tenantId, $who['user']) : [];
        $this->journal($tenantId, $who, 'alertes.proposals', '', count($rows) . ' proposition(s)', '');
        $out = [];
        foreach ($rows as $p) {
            $out[] = [
                'ref' => (string) $p['ref'], 'doc' => (string) $p['doc'], 'proposal_state' => (string) $p['state'], 'result_ref' => (string) ($p['result_ref'] ?? ''),
                'decision_note' => $p['state'] === 'rejected' ? (string) $p['decision_note'] : '', 'decided_at' => (string) ($p['decided_at'] ?? ''),
                'created_at' => (string) $p['created_at'], 'cid' => (string) ($p['client_id'] ?? ''),
            ];
        }
        if ($json) {
            return Response::json(['version' => SseAlertGameService::VERSION, 'proposals' => $out])->header('Cache-Control', 'private, no-cache');
        }
        $page = SseLoreService::sqf([SseAlertGameService::VERSION, 'proposals', array_map(static fn (array $o): array => array_values($o), $out)]);

        return Response::json(['id' => 1, 'ref' => $page, 'page' => 0, 'next' => 0, 'pages' => 1]);
    }

    private function detailOp(int $tenantId, string $level, array $who, string $ref, string $lang, bool $json): Response
    {
        $alert = $this->service()->find($tenantId, $level, $who['user'] > 0 ? $who['user'] : null, $ref, $lang);
        $this->journal($tenantId, $who, 'alertes.detail', $ref, $alert !== null ? 'lue' : 'refusée ou inconnue', $level);
        if ($alert === null) {
            return self::notFound();
        }
        if ($json) {
            return Response::json(['version' => SseAlertGameService::VERSION, 'level' => $level, 'alert' => SseAlertGameService::publicAlert($alert)] + SseAlertGameService::detail($alert))
                ->header('Cache-Control', 'private, no-cache');
        }

        return Response::json(['id' => 1, 'ref' => SseAlertGameService::detailPage($alert), 'page' => 0, 'next' => 0, 'pages' => 1]);
    }

    // -------------------------------------------------------------------- Écritures

    /**
     * Émission : alerte active si le compte en a le droit sur le site, sinon proposition à valider par le bureau.
     *
     * @param array{user: int, label: string, key: string} $who
     * @param array<string, mixed> $body
     * @return array{0: list<mixed>, 1: array<string, mixed>}|Response
     */
    private function createOp(int $tenantId, string $level, array $who, SseSheetAccess $access, array $body, string $lang): array|Response
    {
        $repo = $this->service()->alerts()->alerts();
        $cid = self::cid($body);
        $answer = static function (string $state, string $ref, string $url, string $token, bool $replay): array {
            $message = $state === 'proposal'
                ? 'Proposition transmise au bureau : l’alerte sera diffusée après validation.'
                : 'Alerte émise et diffusée.';

            return [
                [SseAlertGameService::VERSION, 'create', $state, $ref, $url, $token, $message, $replay],
                ['state' => $state, 'ref' => $ref, 'url' => $url, 'token' => $token, 'message' => $message, 'replay' => $replay],
            ];
        };
        // Renvoi du même identifiant client : la même réponse, rien de créé.
        if ($cid !== null) {
            $link = $repo->linkByClient($tenantId, $cid);
            if ($link !== null) {
                $alert = $this->service()->findByTarget($tenantId, $level, $who['user'], (string) $link['doc'], (int) $link['target_id'], $lang);

                return $alert !== null ? $answer('active', (string) $alert['ref'], (string) $alert['url'], (string) $alert['token'], true) : self::notFound();
            }
            $proposal = $repo->proposalByClient($tenantId, $cid);
            if ($proposal !== null) {
                return $answer('proposal', (string) $proposal['ref'], '', '', true);
            }
        }
        $checked = $this->proposals()->normalize($tenantId, $body);
        if ($checked['errors'] !== []) {
            return self::invalid($checked['errors']);
        }
        $payload = $checked['payload'];
        $cause = $this->proposals()->proposalCause($tenantId, $payload, $access);
        if ($payload['subject'] !== '' && $this->proposals()->subject($tenantId, (string) $payload['subject'], $access) === null) {
            $this->journal($tenantId, $who, 'alertes.refused', (string) $payload['subject'], 'create : sujet introuvable', $level);

            return self::invalid(['Sujet introuvable au registre de la communauté.']);
        }
        if ($cause !== null) {
            $proposal = $this->proposals()->propose($tenantId, $payload, $cid, $who['user'], $who['label'], $cause);
            if ($proposal === null) {
                return self::error('unavailable', 'La proposition n’a pas pu être enregistrée : réessayez.', 503);
            }
            $this->journal($tenantId, $who, 'alertes.proposal', (string) $proposal['ref'], $cause, $level, ['doc' => $payload['doc'], 'subject' => $payload['subject']]);

            return $answer('proposal', (string) $proposal['ref'], '', '', false);
        }
        $res = $this->proposals()->issue($tenantId, $payload, $access, $cid);
        if (!$res['ok']) {
            $this->journal($tenantId, $who, 'alertes.refused', (string) $payload['subject'], 'create : ' . $res['error'], $level);

            return self::failure((string) $res['error'], $res['errors']);
        }
        $alert = $this->service()->findByTarget($tenantId, $level, $who['user'], (string) $res['doc'], (int) $res['id'], $lang);
        $this->journal($tenantId, $who, 'alertes.create', (string) $res['ref'], '', $level, ['doc' => $res['doc'], 'subject' => $payload['subject']]);

        return $answer('active', (string) $res['ref'], (string) ($alert['url'] ?? ''), (string) ($alert['token'] ?? ''), false);
    }

    /**
     * @param array{user: int, label: string, key: string} $who
     * @param array<string, mixed> $body
     * @return array{0: list<mixed>, 1: array<string, mixed>}|Response
     */
    private function reportOp(int $tenantId, string $level, array $who, array $body, string $lang): array|Response
    {
        $ref = self::text($body['ref'] ?? '');
        $token = self::text($body['token'] ?? '');
        $alert = $ref !== ''
            ? $this->service()->find($tenantId, $level, $who['user'], $ref, $lang)
            : $this->service()->findByToken($tenantId, $level, $who['user'], $token, $lang);
        if ($alert === null) {
            $this->journal($tenantId, $who, 'alertes.refused', $ref, 'report : alerte inconnue ou au-dessus de l’habilitation', $level);

            return self::notFound();
        }
        $link = $this->service()->linkOf($tenantId, $alert);
        if ($link === null) {
            return self::error('unavailable', 'Le signalement n’a pas pu être enregistré : réessayez.', 503);
        }
        // Jamais de photo par ce canal : seuls les champs du contrat passent.
        $input = array_intersect_key($body, array_flip(['what', 'when', 'place', 'grid', 'pos', 'text', 'dir', 'vehicle', 'sure']));
        $input['cid'] = self::cid($body);
        $res = $this->service()->alerts()->report($tenantId, $link, $input, ['user' => $who['user'], 'label' => $who['label'], 'source' => 'game'], [
            'ref' => (string) $alert['ref'], 'closed' => $alert['state'] !== 'active', 'label' => (string) $alert['name'],
        ]);
        if (!$res['ok'] || $res['report'] === null) {
            $this->journal($tenantId, $who, 'alertes.refused', (string) $alert['ref'], 'report : ' . $res['error'], $level);

            return self::failure((string) $res['error'], $res['errors']);
        }
        $report = $res['report'];
        if (!$res['replay']) {
            $this->journal($tenantId, $who, 'alertes.report', (string) $alert['ref'], (string) $report['ref'], $level, ['doc' => $alert['doc'], 'what' => $report['what']]);
        }

        return [
            [SseAlertGameService::VERSION, 'report', (string) $report['ref'], (string) $alert['ref'], (string) $report['status'], (bool) $res['replay']],
            ['report' => (string) $report['ref'], 'ref' => (string) $alert['ref'], 'status' => (string) $report['status'], 'replay' => (bool) $res['replay']],
        ];
    }

    /**
     * Renouvellement : une NOUVELLE alerte (nouvelle référence, nouvelle échéance) ; la précédente est close et renvoie vers elle.
     *
     * @param array{user: int, label: string, key: string} $who
     * @param array<string, mixed> $body
     * @return array{0: list<mixed>, 1: array<string, mixed>}|Response
     */
    private function renewOp(int $tenantId, string $level, array $who, SseSheetAccess $access, array $body, string $lang): array|Response
    {
        $alerts = $this->service()->alerts();
        $repo = $alerts->alerts();
        $cid = self::cid($body);
        $answer = static fn (string $ref, string $until, string $url, string $token, bool $replay): array => [
            [SseAlertGameService::VERSION, 'renew', $ref, $until, $url, $token, $replay],
            ['ref' => $ref, 'until' => $until, 'url' => $url, 'token' => $token, 'replay' => $replay],
        ];
        if ($cid !== null) {
            $link = $repo->linkByClient($tenantId, $cid);
            if ($link !== null) {
                $new = $this->service()->findByTarget($tenantId, $level, $who['user'], (string) $link['doc'], (int) $link['target_id'], $lang);

                return $new !== null ? $answer((string) $new['ref'], (string) $new['until'], (string) $new['url'], (string) $new['token'], true) : self::notFound();
            }
        }
        $ref = self::text($body['ref'] ?? '');
        $alert = $this->service()->find($tenantId, $level, $who['user'], $ref, $lang);
        if ($alert === null) {
            $this->journal($tenantId, $who, 'alertes.refused', $ref, 'renew : alerte inconnue ou au-dessus de l’habilitation', $level);

            return self::notFound();
        }
        $days = is_numeric($body['days'] ?? null) && in_array((int) $body['days'], SseSheetCatalog::VALIDITY_DAYS, true) ? (int) $body['days'] : 0;
        if ($alert['doc'] === SseAlertRepository::DOC_VIGILANCE) {
            $res = $alerts->renewVigilance($tenantId, (int) $alert['_id'], $days, $access, null, null, $cid);
            if (!$res['ok']) {
                $this->journal($tenantId, $who, 'alertes.refused', $ref, 'renew : ' . $res['error'], $level);

                return self::failure((string) $res['error'], $res['errors']);
            }
            $this->journal($tenantId, $who, 'alertes.renew', (string) $res['ref'], 'remplace ' . $alert['ref'], $level, ['doc' => $alert['doc'], 'previous' => $alert['ref']]);

            return $answer((string) $res['ref'], (string) $res['until'], (string) $res['url'], (string) $res['token'], false);
        }
        $res = $this->wanted()->renew($tenantId, (int) $alert['_id'], $days > 0 ? ['expires_at' => gmdate('Y-m-d H:i:s', time() + $days * 86400)] : [], $access->wanted());
        if (!$res['ok']) {
            $this->journal($tenantId, $who, 'alertes.refused', $ref, 'renew : ' . $res['error'], $level);

            return self::failure((string) $res['error'], $res['errors']);
        }
        // L'adresse de l'avis précédent reste lisible et renvoie vers le nouveau ; le nouveau porte l'identifiant client.
        $until = '';
        $url = '';
        $token = '';
        try {
            $old = $this->service()->linkOf($tenantId, $alert);
            if ($old !== null) {
                $repo->lift($tenantId, (int) $old['id'], 'Renouvelé par l’avis ' . $res['ref'] . '.', $who['user'], $who['label'], (string) $res['ref']);
            }
            $notice = (new SseWantedNoticeRepository())->find($tenantId, (int) $res['id']);
            if ($notice !== null) {
                $until = substr((string) ($notice['expires_at'] ?? ''), 0, 10);
                $link = $repo->ensureLink($tenantId, SseAlertRepository::DOC_NOTICE, (int) $res['id'], ['report' => $alerts->noticeReport($tenantId, $notice), 'client_id' => $cid, 'user_id' => $who['user']]);
                if ($link !== null && $link['revoked_at'] === null) {
                    $token = (string) $link['token'];
                    $url = \App\Support\SseAlertReportTo::url($token);
                }
            }
        } catch (\Throwable $e) {
            error_log('Alertes SSE : adresse de signalement du renouvellement non enregistrée : ' . $e->getMessage());
        }
        $this->journal($tenantId, $who, 'alertes.renew', (string) $res['ref'], 'remplace ' . $alert['ref'], $level, ['doc' => $alert['doc'], 'previous' => $alert['ref']]);

        return $answer((string) $res['ref'], $until, $url, $token, false);
    }

    /**
     * @param array{user: int, label: string, key: string} $who
     * @param array<string, mixed> $body
     * @return array{0: list<mixed>, 1: array<string, mixed>}|Response
     */
    private function liftOp(int $tenantId, string $level, array $who, SseSheetAccess $access, array $body, string $lang): array|Response
    {
        $ref = self::text($body['ref'] ?? '');
        $answer = static fn (string $ref, string $at, bool $replay): array => [
            [SseAlertGameService::VERSION, 'lift', $ref, $at, $replay],
            ['ref' => $ref, 'lifted_at' => $at, 'replay' => $replay],
        ];
        $alert = $this->service()->find($tenantId, $level, $who['user'], $ref, $lang);
        if ($alert === null) {
            $this->journal($tenantId, $who, 'alertes.refused', $ref, 'lift : alerte inconnue ou au-dessus de l’habilitation', $level);

            return self::notFound();
        }
        if ($alert['state'] !== 'active') {
            // Renvoi (même identifiant client) par le compte qui a levé l'alerte : la même réponse.
            $by = $alert['doc'] === SseAlertRepository::DOC_NOTICE ? ($alert['_notice']['closed_by_user_id'] ?? null) : ($alert['_link']['lifted_by_user_id'] ?? null);
            if ($alert['state'] === 'lifted' && self::cid($body) !== null && $by !== null && (int) $by === $who['user']) {
                return $answer((string) $alert['ref'], SseAlertGameService::detail($alert)['lifted_at'], true);
            }

            return self::error('closed', 'Cette alerte n’est plus en cours.', 409);
        }
        $reason = SseWantedCatalog::text($body['reason'] ?? '', 2000);
        if (mb_strlen($reason) < SseAlertService::BODY_MIN || mb_strlen($reason) > 500) {
            return self::invalid(['Le motif de la levée est obligatoire (3 à 500 caractères).']);
        }
        $res = $alert['doc'] === SseAlertRepository::DOC_NOTICE
            ? $this->wanted()->lift($tenantId, (int) $alert['_id'], 'lifted', $reason, $access->wanted())
            : $this->service()->alerts()->liftVigilance($tenantId, (int) $alert['_id'], $reason, $access);
        if (!$res['ok']) {
            $this->journal($tenantId, $who, 'alertes.refused', $ref, 'lift : ' . $res['error'], $level);
            if ($res['error'] === 'forbidden' && $alert['doc'] === SseAlertRepository::DOC_NOTICE) {
                return self::error('forbidden', 'Droit manquant : écrire dans « Fichiers et sujets » (gestion des dossiers SSE).', 403);
            }

            return self::failure((string) $res['error'], $res['errors']);
        }
        $after = $this->service()->find($tenantId, $level, $who['user'], $ref, $lang);
        $at = $after !== null ? SseAlertGameService::detail($after)['lifted_at'] : '';
        $this->journal($tenantId, $who, 'alertes.lift', (string) $alert['ref'], $reason, $level, ['doc' => $alert['doc']]);

        return $answer((string) $alert['ref'], $at !== '' ? $at : gmdate('Y-m-d H:i'), false);
    }

    /**
     * @param array{user: int, label: string, key: string} $who
     * @param array<string, mixed> $body
     * @return array{0: list<mixed>, 1: array<string, mixed>}|Response
     */
    private function ackOp(int $tenantId, string $level, array $who, array $body, string $lang): array|Response
    {
        $ref = self::text($body['ref'] ?? '');
        $alert = $this->service()->find($tenantId, $level, $who['user'], $ref, $lang);
        if ($alert === null) {
            $this->journal($tenantId, $who, 'alertes.refused', $ref, 'ack : alerte inconnue ou au-dessus de l’habilitation', $level);

            return self::notFound();
        }
        $link = $this->service()->linkOf($tenantId, $alert);
        if ($link === null) {
            return self::error('unavailable', 'L’accusé de lecture n’a pas pu être enregistré : réessayez.', 503);
        }
        $first = !$alert['acked'];
        $at = substr($this->service()->alerts()->alerts()->ack($tenantId, (int) $link['id'], $who['user']), 0, 16);
        if ($first) {
            $this->journal($tenantId, $who, 'alertes.ack', (string) $alert['ref'], '', $level, ['doc' => $alert['doc']]);
        }

        return [
            [SseAlertGameService::VERSION, 'ack', (string) $alert['ref'], $at],
            ['ref' => (string) $alert['ref'], 'acked_at' => $at],
        ];
    }

    // ----------------------------------------------------------------------- Outils

    /**
     * Droits du compte sur le site : émettre une fiche de vigilance, placer un avis, lever, signaler.
     *
     * @param array{user: int, label: string, key: string} $who
     * @return array{0: bool, 1: bool, 2: bool, 3: bool}
     */
    private function rights(int $tenantId, array $who, string $level): array
    {
        if ($who['user'] < 1 || !SseAlertRepository::ready()) {
            return [false, false, false, false];
        }
        try {
            $access = ($this->accessOf)($tenantId, $who['user'], $level);

            return [$access->can('vigilance'), $access->wanted()->canManage(), $access->wanted()->canManage(), true];
        } catch (\Throwable) {
            return [false, false, false, true];
        }
    }

    private function service(): SseAlertGameService
    {
        return $this->service ??= new SseAlertGameService();
    }

    private function proposals(): SseAlertProposals
    {
        return $this->proposals ??= new SseAlertProposals($this->service()->alerts(), $this->wanted());
    }

    private function wanted(): SseWantedService
    {
        return $this->wanted ??= new SseWantedService();
    }

    /**
     * Authentification, communauté, niveau de lecture et maintenance (gardes de « sse_fiches »).
     *
     * @return array{0: int, 1: string, 2: array{user: int, label: string, key: string}}|Response communauté, niveau, auteur ; ou la réponse d'erreur
     */
    private function guard(): array|Response
    {
        $keyPresented = ComspecApiKeyAuth::extractPresentedKey() !== '';
        if ($keyPresented && !ComspecApiKeyAuth::armaInlineAuthOk()) {
            return self::error('unauthorized', 'Clé d’accès refusée.', 401);
        }
        $sessionUser = (int) (Session::get('user_id') ?? 0);
        $tenantId = $keyPresented ? (int) (ComspecApiKeyAuth::matchedTenantId() ?? 0) : 0;
        $userId = $keyPresented ? (int) (ComspecApiKeyAuth::matchedUserId() ?? 0) : $sessionUser;
        $level = SseFichesGameService::NOTICE_LEVEL;
        if (!$keyPresented) {
            if ($sessionUser < 1) {
                return self::error('unauthorized', 'Authentification terrain requise.', 401);
            }
            $tenantId = (int) (Session::get('tenant_id') ?? 0);
            $max = ($this->sessionLevel)();
            if ($tenantId > 0 && $max === null) {
                return self::error('forbidden', 'Votre habilitation ne permet pas de consulter les alertes SSE.', 403);
            }
            $level = (string) $max;
        }
        if ($tenantId < 1) {
            return self::error('forbidden', 'Communauté non identifiée. Reliez le compte Athena en jeu, ou utilisez la clé d’accès fournie par votre administrateur.', 403);
        }
        if ($keyPresented) {
            // Le niveau est celui du compte relié en jeu ; sans compte (clé de communauté seule) : le plus bas.
            $level = $this->service()->levelFor($tenantId, $userId > 0 ? $userId : null);
        }
        $message = ($this->maintenance)($tenantId);
        if ($message !== null && !($sessionUser > 0 && function_exists('can') && can('admin.access'))) {
            return self::error('maintenance', $message !== '' ? $message : 'L’accès à la carte est suspendu pour le moment. Réessayez plus tard.', 503);
        }

        return [$tenantId, $level, ['user' => $userId, 'label' => $keyPresented ? self::ACTOR : 'Session du portail', 'key' => $userId > 0 ? 'u' . $userId : 'k']];
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
                'object_type' => 'game_alert',
                'object_uuid' => $ref !== '' ? mb_substr($ref, 0, 36) : null,
                'reason' => mb_substr($reason, 0, 500),
                'after' => ['level' => $level] + $after,
            ]);
        } catch (\Throwable) {
            // Le journal ne bloque ni la lecture ni l'écriture (table pas encore migrée, base indisponible).
        }
    }

    /**
     * Refus d'un service du site traduit en erreur du contrat.
     *
     * @param list<string> $errors
     */
    private static function failure(string $code, array $errors): Response
    {
        $errors = array_values(array_map([self::class, 'plain'], array_filter($errors, 'is_string')));
        $first = $errors[0] ?? '';

        return match ($code) {
            'forbidden' => self::error('forbidden', $first !== '' ? $first : 'Action non autorisée.', 403),
            'notfound', 'notice' => self::notFound(),
            'closed' => self::error('closed', $first !== '' ? $first : 'Cette alerte n’est plus en cours.', 409),
            'account_required' => self::error('account_required', $first, 403),
            'rate', 'rate_limited' => self::limited($first !== '' ? $first : 'Trop d’envois en peu de temps.', self::WRITE_WINDOW),
            'unavailable', 'engine', 'conflict' => self::error('unavailable', $first !== '' ? $first : 'Service indisponible : réessayez.', 503),
            default => self::invalid($errors !== [] ? $errors : ['Saisie refusée.']),
        };
    }

    /** Aucun vocabulaire de hors-jeu dans un texte envoyé au téléphone. */
    private static function plain(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_ireplace([' de jeu de rôle', ' fictif', ' fictive'], '', $text)));
    }

    /** @param list<string> $errors */
    private static function invalid(array $errors): Response
    {
        $errors = array_values($errors);

        return Response::json(['error' => 'invalid', 'message' => (string) ($errors[0] ?? 'Saisie refusée.'), 'errors' => $errors], 422);
    }

    private static function notFound(): Response
    {
        return self::error('not_found', 'Alerte inconnue ou au-dessus de votre habilitation.', 404);
    }

    private static function limited(string $message, int $window): Response
    {
        return Response::json(['error' => 'rate_limited', 'message' => $message, 'retry_after' => $window], 429)->header('Retry-After', (string) $window);
    }

    private static function error(string $code, string $message, int $status): Response
    {
        return Response::json(['error' => $code, 'message' => $message], $status);
    }

    /** @param array<string, mixed> $body */
    private static function cid(array $body): ?string
    {
        $cid = is_string($body['cid'] ?? null) ? trim($body['cid']) : '';

        return SseAlertRepository::validClientId($cid) ? $cid : null;
    }

    /** @param array<string, mixed> $params */
    private static function refOf(array $params): string
    {
        return self::text(rawurldecode((string) ($params['ref'] ?? '')));
    }

    /**
     * Corps d'une route POST : objet JSON, sinon champs de formulaire. Jamais la communauté ni l'opération.
     *
     * @return array<string, mixed>
     */
    private function body(Request $request): array
    {
        $raw = '';
        try {
            $raw = (string) ($this->bodyReader)();
        } catch (\Throwable) {
            $raw = '';
        }
        $decoded = $raw !== '' ? json_decode($raw, true) : null;
        $body = is_array($decoded) ? $decoded : $request->getParsedBody();
        unset($body['op'], $body['ref'], $body['kind']);

        return $body;
    }

    private static function text(mixed $v): string
    {
        return is_scalar($v) ? mb_substr(trim((string) $v), 0, 200) : '';
    }
}
