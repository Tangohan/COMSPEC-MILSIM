<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\TenantApiKeyRepository;

/**
 * Authentification Bearer pour /integrations/v1/* (clé par communauté, quotas journaliers).
 */
final class IntegrationsApiAuthMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        $repo = Container::get(TenantApiKeyRepository::class);
        if (!$repo->tableExists()) {
            return Response::json(['error' => 'Fonction indisponible.'], 503);
        }
        $hdr = (string) ($request->server['HTTP_AUTHORIZATION'] ?? $request->server['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (!preg_match('/Bearer\s+(\S+)/i', $hdr, $m)) {
            return Response::json(['error' => 'Authentification requise.'], 401);
        }
        $plain = trim($m[1]);
        $row = $repo->findActiveByPlainKey($plain);
        if ($row === null) {
            return Response::json(['error' => 'Clé non reconnue.'], 401);
        }
        $quota = (int) ($row['quota_per_day'] ?? 10000);
        if ($quota < 1) {
            $quota = 10000;
        }
        $used = $repo->countToday((int) $row['id']);
        if ($used >= $quota) {
            return Response::json(['error' => 'Quota journalier atteint.'], 429);
        }
        $scopesRaw = (string) ($row['scopes_json'] ?? '');
        $scopes = [];
        if ($scopesRaw !== '') {
            $dec = json_decode($scopesRaw, true);
            $scopes = is_array($dec) ? $dec : [];
        }
        if ($scopes !== [] && !in_array('events:read', $scopes, true)) {
            return Response::json(['error' => 'Cette clé ne permet pas l’accès à cette ressource.'], 403);
        }
        $repo->incrementDailyUsage((int) $row['id']);
        $repo->touchUsed((int) $row['id']);
        $request->setAttribute('integration_tenant_id', (int) $row['tenant_id']);
        $request->setAttribute('integration_api_key_id', (int) $row['id']);

        return $next($request);
    }
}
