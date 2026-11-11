<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AtakToc\TocAccess;

/**
 * Socle des routes JSON du poste TOC web et des plans (/atak/toc/api/…) : membre connecté de la communauté,
 * droit du poste, jeton CSRF sur toute écriture, limite de débit par membre, journal d'audit des actions.
 */
abstract class AtakTocBaseController
{
    /** Corps JSON imposé par les tests (sinon php://input). */
    public static ?string $testBody = null;

    /** @var array<string, mixed>|null */
    private ?array $bodyCache = null;

    protected TocAccess $access;

    public function __construct(?TocAccess $access = null)
    {
        $this->access = $access ?? new TocAccess();
    }

    protected function tenantId(): int
    {
        return (int) Session::get('tenant_id');
    }

    protected function userId(): int
    {
        return (int) Session::get('user_id');
    }

    /** Libellé de l'opérateur : indicatif, sinon nom affiché. */
    protected function actorLabel(): string
    {
        $label = trim((string) (Session::get('callsign') ?? ''));
        if ($label === '') {
            try {
                $u = (new \App\Repositories\UserRepository())->findById($this->userId(), $this->tenantId());
                $label = trim((string) (($u['callsign'] ?? '') ?: ($u['display_name'] ?? '') ?: ($u['username'] ?? '')));
            } catch (\Throwable) {
                $label = '';
            }
        }

        return $label !== '' ? mb_substr($label, 0, 60) : 'Opérateur web';
    }

    /** Refus si pas de communauté, pas de membre ou pas le droit du poste. */
    protected function guard(): ?Response
    {
        if ($this->tenantId() < 1 || $this->userId() < 1) {
            return Response::json(['ok' => false, 'error' => 'unauthenticated'], 401);
        }
        if (!$this->access->canUse()) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        return null;
    }

    /** Écriture : CSRF et limite de débit (par membre et par famille d'actions). */
    protected function guardWrite(Request $request, string $bucket, int $max = 90, int $seconds = 60): ?Response
    {
        if (!Csrf::verify($request)) {
            return Response::json(['ok' => false, 'error' => 'csrf'], 419);
        }
        try {
            $limiter = new \App\Services\Security\FileRateLimiter();
            $key = 'atak-toc|' . $bucket . '|' . $this->tenantId() . '|' . $this->userId();
            if ($limiter->tooManyAttempts($key, $max, $seconds)) {
                return Response::json(['ok' => false, 'error' => 'rate_limited'], 429);
            }
            $limiter->hit($key, $seconds);
        } catch (\Throwable) {
            // Limiteur indisponible (disque) : l'action passe.
        }

        return null;
    }

    /** @return array<string, mixed> */
    protected function body(): array
    {
        if ($this->bodyCache !== null) {
            return $this->bodyCache;
        }
        $raw = self::$testBody ?? \App\Support\HttpJsonBody::rawJson();
        $dec = $raw !== '' ? json_decode($raw, true, 64) : null;
        if (!is_array($dec)) {
            $dec = \App\Support\HttpJsonBody::isMultipart() ? \App\Support\HttpJsonBody::postFields() : (is_array($_POST) ? $_POST : []);
        }

        return $this->bodyCache = $dec;
    }

    /** @param array<string, mixed> $details */
    protected function audit(string $action, ?int $entityId, array $details): void
    {
        try {
            (new \App\Services\Audit\AuditService())->logChange($action, $this->tenantId(), $this->userId() ?: null, 'atak_toc', $entityId, [], $details);
        } catch (\Throwable $e) {
            error_log('[atak-toc audit] ' . $action . ' : ' . $e->getMessage());
        }
    }

    /**
     * Réponse JSON avec ETag : 304 si le client a déjà cette version.
     *
     * @param array<string, mixed> $data
     */
    protected function jsonWithEtag(array $data, string $version): Response
    {
        $etag = '"toc-' . $version . '"';
        $inm = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        if ($inm !== '' && $inm === $etag) {
            $r = new Response();

            return $r->setStatusCode(304)->header('ETag', $etag)->header('Cache-Control', 'private, no-cache');
        }

        return Response::json($data)->header('ETag', $etag)->header('Cache-Control', 'private, no-cache');
    }

    /** Carte demandée (?mapId=), bornée aux cartes connues ; carte par défaut de la communauté sinon. */
    protected function mapId(Request $request): int
    {
        $id = (int) ($request->query('mapId') ?? $this->body()['mapId'] ?? 0);
        try {
            $repo = new \App\Repositories\AtakMapRepository();
            if ($id > 0 && $repo->getById($id) !== null) {
                return $id;
            }
            $def = $repo->getDefaultForTenant($this->tenantId());

            return (int) ($def['id'] ?? 1) ?: 1;
        } catch (\Throwable) {
            return max(1, $id);
        }
    }

    protected function side(Request $request): string
    {
        $s = strtoupper((string) ($request->query('side') ?? $this->body()['side'] ?? 'WEST'));

        return in_array($s, ['WEST', 'EAST', 'GUER', 'CIV'], true) ? $s : 'WEST';
    }
}
