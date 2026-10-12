<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Sse\SseCampaignService;
use App\Support\ComspecApiKeyAuth;
use App\Support\HttpJsonBody;

/**
 * Campagnes SSE ⇄ jeu (COMSPEC Link 2.0.67 : SseCampaignPush, SseCampaignList, SseCampaignPull).
 *
 * POST /api/sse/campaigns/sync          {key, text}  texte exporté par le jeu → Athena
 * GET  /api/sse/campaigns               campagnes actives de la communauté
 * GET  /api/sse/campaigns/{key}/text    ?offset=&length=  texte en morceaux (sortie DLL limitée à 8 Ko)
 */
final class SseCampaignApiController
{
    private const CHUNK_MAX = 6000;

    public function __construct(private ?SseCampaignService $service = null)
    {
        $this->service ??= new SseCampaignService();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->noTenant();
        }
        $out = [];
        foreach ($this->service->repository()->listForTenant($tenantId, false) as $r) {
            $out[] = [
                'key' => (string) $r['campaign_key'],
                'name' => (string) $r['name'],
                'type' => (string) $r['type'],
                'missions' => (int) $r['missions'],
                'persons' => (int) $r['persons_count'],
                'orgs' => (int) $r['orgs_count'],
                'updated' => (string) ($r['updated_at'] ?? $r['created_at']),
                'hash' => substr((string) $r['payload_hash'], 0, 16),
            ];
        }

        return Response::json(['campaigns' => $out, 'count' => count($out)]);
    }

    public function sync(Request $request, array $params = []): Response
    {
        if (!ComspecApiKeyAuth::armaInlineAuthOk()) {
            return Response::json(['error' => 'Unauthorized', 'message' => 'Authentification terrain requise.'], 401);
        }
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->noTenant();
        }
        $raw = HttpJsonBody::rawJson();
        $body = $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($body)) {
            return Response::json(['error' => 'bad_json'], 422);
        }
        try {
            $r = $this->service->ingest(
                $tenantId,
                (string) ($body['text'] ?? ''),
                (string) ($body['key'] ?? ''),
                'jeu',
                mb_substr(trim((string) ($body['actor'] ?? 'Serveur de jeu')), 0, 120) ?: 'Serveur de jeu'
            );
        } catch (\InvalidArgumentException $e) {
            return Response::json(['error' => 'invalid_campaign', 'message' => $e->getMessage()], 422);
        }
        $row = $this->service->repository()->find($tenantId, $r['id']);
        $collisions = $row !== null ? count($this->service->repository()->seedCollisions($row)) : 0;

        return Response::json(['ok' => true, 'id' => $r['id'], 'key' => $r['key'], 'created' => $r['created'], 'seed_collisions' => $collisions]);
    }

    public function text(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->noTenant();
        }
        $key = SseCampaignService::slug((string) ($params['key'] ?? ''));
        $row = $this->service->repository()->findByKey($tenantId, $key);
        if ($row === null || ($row['status'] ?? '') === 'archived') {
            return Response::json(['error' => 'not_found', 'message' => 'Campagne ' . $key . ' absente d’Athena.'], 404);
        }
        $text = (string) $row['payload'];
        $total = strlen($text);
        $offset = max(0, (int) $request->query('offset', 0));
        $length = (int) $request->query('length', self::CHUNK_MAX);
        $length = max(256, min(self::CHUNK_MAX, $length));
        // Morceau coupé sur une frontière de caractère UTF-8.
        $chunk = $offset < $total ? mb_strcut($text, $offset, $length, 'UTF-8') : '';
        $next = $offset + strlen($chunk);

        return Response::json([
            'key' => $key,
            'total' => $total,
            'offset' => $offset,
            'next' => $next,
            'done' => $next >= $total,
            'chunk' => $chunk,
        ]);
    }

    private function tenantId(Request $request): ?int
    {
        $matched = ComspecApiKeyAuth::matchedTenantId();
        if ($matched !== null && $matched > 0) {
            return $matched;
        }
        // Pas d'identifiant de communauté en paramètre : une campagne n'est lue qu'avec la clé
        // de la communauté (serveur de jeu) ou une session Athena ouverte.
        $sid = (int) (Session::get('tenant_id') ?? 0);
        if ($sid > 0 && (int) (Session::get('user_id') ?? 0) > 0) {
            return $sid;
        }

        return null;
    }

    private function noTenant(): Response
    {
        return Response::json([
            'error' => 'tenant_context_required',
            'message' => 'Communauté non identifiée. Reliez le compte Athena en jeu, ou utilisez la clé d’accès fournie par votre administrateur.',
        ], 403);
    }
}
