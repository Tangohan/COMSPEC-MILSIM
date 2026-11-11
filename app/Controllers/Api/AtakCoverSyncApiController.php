<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\SseCoverSyncService;
use App\Support\ComspecApiKeyAuth;

/**
 * Couvertures d'Athena récupérées depuis le téléphone en jeu (app Ma couverture › Synchroniser avec Athena).
 *
 *  GET /api/atak/covers?v=1                       → {covers_api, covers: [...]}
 *  GET /api/atak/covers/files?refs=CVR-…,CVR-…&v=1 → {covers_api, files: [fichiers comspec-cover v1], errors: [...]}
 * Même service, relayé par COMSPEC Link sans nouvelle commande : GET /api/sse/identity/pack?op=~covers (liste) ou
 * op=~cover:CVR-…,CVR-… (fichiers), réponse au format du paquet (champ « settings »), voir packResponse().
 *
 * Accès : jeton de jeu d'un compte lié (session COMSPEC Link, vérifié par le garde des routes /api/atak et du paquet) ;
 * une clé de communauté seule ne dit pas qui appelle : 403 « unlinked » (comme le paquet « ~self »).
 * Le compte et la communauté viennent du jeton, jamais de la requête. Limites par compte : 12 listes par minute,
 * 10 téléchargements (5 couvertures chacun au plus) par 10 minutes.
 */
final class AtakCoverSyncApiController
{
    public const LIST_MAX = 12;
    public const LIST_WINDOW = 60;
    public const FILES_MAX = 10;
    public const FILES_WINDOW = 600;

    /** @var \Closure(): array{0: int, 1: int} */
    private \Closure $who;
    /** @var \Closure(string, int, int): bool */
    private \Closure $tooMany;

    /**
     * @param (\Closure(): array{0: int, 1: int})|null $who      [communauté, compte] du jeton de jeu (0 : absent)
     * @param (\Closure(string, int, int): bool)|null  $tooMany  limiteur (clé, plafond, fenêtre en secondes)
     */
    public function __construct(private ?SseCoverSyncService $sync = null, ?\Closure $who = null, ?\Closure $tooMany = null)
    {
        $this->who = $who ?? static fn (): array => [(int) (ComspecApiKeyAuth::matchedTenantId() ?? 0), (int) (ComspecApiKeyAuth::matchedUserId() ?? 0)];
        $this->tooMany = $tooMany ?? static fn (string $key, int $max, int $window): bool => (new FileRateLimiter())->tooManyAttempts($key, $max, $window);
    }

    /** GET /api/atak/covers */
    public function index(Request $request, array $params = []): Response
    {
        $ctx = $this->context('list');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        try {
            $covers = $this->sync()->listFor($ctx[0], $ctx[1]);
        } catch (\PDOException) {
            return self::error('unavailable', 'Couvertures indisponibles pour le moment.', 503);
        }

        return Response::json(['covers_api' => SseCoverSyncService::API_VERSION, 'covers' => $covers])->header('Cache-Control', 'private, no-store');
    }

    /** GET /api/atak/covers/files?refs= */
    public function files(Request $request, array $params = []): Response
    {
        $ctx = $this->context('files');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $raw = $request->query('refs');
        $refs = is_string($raw) && strlen($raw) <= 120 ? SseCoverSyncService::refsFrom($raw) : null;
        if ($refs === null) {
            return self::error('invalid', 'Couvertures demandées illisibles (1 à 5 références CVR-…).', 422);
        }
        try {
            $out = $this->sync()->filesFor($ctx[0], $ctx[1], $refs);
        } catch (\PDOException) {
            return self::error('unavailable', 'Couvertures indisponibles pour le moment.', 503);
        }

        return Response::json(['covers_api' => SseCoverSyncService::API_VERSION] + $out)->header('Cache-Control', 'private, no-store');
    }

    /**
     * Relais par le paquet d'identité (COMSPEC Link GetIdentityPack, inchangé) : la DLL ne garde que version,
     * generated_at, map, op, since et settings ; la réponse tient donc dans « settings ». $since porte la version du mod.
     */
    public function packResponse(string $op, string $since, int $mapId): Response
    {
        $files = str_starts_with($op, SseCoverSyncService::OP_FILES);
        if (!$files && $op !== SseCoverSyncService::OP_LIST) {
            return self::error('invalid', 'Opération inconnue.', 422);
        }
        $ctx = $this->context($files ? 'files' : 'list');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $settings = ['covers_api' => SseCoverSyncService::API_VERSION, 'client' => SseCoverSyncService::clientVersion($since)];
        try {
            if ($files) {
                $refs = SseCoverSyncService::refsFrom(substr($op, strlen(SseCoverSyncService::OP_FILES)));
                if ($refs === null) {
                    return self::error('invalid', 'Couvertures demandées illisibles (1 à 5 références CVR-…).', 422);
                }
                $settings += $this->sync()->filesFor($ctx[0], $ctx[1], $refs);
            } else {
                $settings['covers'] = $this->sync()->listFor($ctx[0], $ctx[1]);
            }
        } catch (\PDOException) {
            return self::error('unavailable', 'Couvertures indisponibles pour le moment.', 503);
        }

        return Response::json([
            'version' => \App\Services\Sse\SseIdentityPackService::VERSION,
            'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'map' => $mapId,
            'op' => $op,
            'settings' => $settings,
        ])->header('Cache-Control', 'private, no-store');
    }

    /** @return array{0: int, 1: int}|Response */
    private function context(string $bucket): array|Response
    {
        [$tenantId, $userId] = ($this->who)();
        if ($userId < 1) {
            return self::error('unlinked', 'Compte Athena non relié en jeu : vos couvertures ne peuvent pas être lues.', 403);
        }
        if ($tenantId < 1) {
            return self::error('tenant_context_required', 'Communauté non identifiée.', 403);
        }
        [$max, $window] = $bucket === 'files' ? [self::FILES_MAX, self::FILES_WINDOW] : [self::LIST_MAX, self::LIST_WINDOW];
        if (($this->tooMany)('atak-covers:' . $bucket . ':' . $tenantId . ':' . $userId, $max, $window)) {
            return self::error('rate_limited', 'Trop de demandes : réessayez dans quelques minutes.', 429)->header('Retry-After', (string) $window);
        }

        return [$tenantId, $userId];
    }

    private function sync(): SseCoverSyncService
    {
        return $this->sync ??= new SseCoverSyncService();
    }

    private static function error(string $code, string $message, int $status): Response
    {
        return Response::json(['error' => $code, 'message' => $message], $status);
    }
}
