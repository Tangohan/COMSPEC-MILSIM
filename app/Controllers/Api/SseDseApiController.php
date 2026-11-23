<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Sse\SseIdentifierAccess;
use App\Services\Sse\SseIdentifierGenerator;
use App\Services\Sse\SseIdentifierService;
use App\Support\ComspecApiKeyAuth;

/**
 * GET /api/sse/v1/dse/{dse} : résout un numéro DSE vers la donnée et l'éventuelle IA reliée (contrat : docs/technical/ICD-SSE-DSE.md).
 * Donnée inexistante, DSE mal formé ou donnée illisible pour l'appelant : même réponse 404. Chaque appel est journalisé.
 */
final class SseDseApiController
{
    public function resolve(Request $request, array $params = []): Response
    {
        $service = new SseIdentifierService();
        $game = false;
        $userId = null;
        if (ComspecApiKeyAuth::extractPresentedKey() !== '') {
            if (!ComspecApiKeyAuth::requestPresentsValidKey()) {
                return Response::json(['error' => 'unauthorized'], 401);
            }
            $game = true;
            $tenantId = (int) (ComspecApiKeyAuth::matchedTenantId() ?? 0);
        } else {
            $userId = (int) Session::get('user_id') ?: null;
            if ($userId === null) {
                return Response::json(['error' => 'unauthorized'], 401);
            }
            $tenantId = SseIdentifierAccess::tenantId();
        }
        if ($tenantId < 1) {
            return Response::json(['error' => 'tenant_context_required'], 403);
        }

        $raw = (string) ($params['dse'] ?? '');
        $normalized = SseIdentifierGenerator::normalizeDse($raw);
        $row = $normalized !== null ? $service->findByDse($tenantId, $raw) : null;
        $entity = null;
        if ($row !== null) {
            $entity = SseIdentifierAccess::entityRow($tenantId, (string) $row['entity_type'], (int) $row['entity_id']);
            if ($entity !== null && !$game && !SseIdentifierAccess::canRead((string) $row['entity_type'], $entity)) {
                $entity = null;
            }
        }
        $found = $row !== null && $entity !== null;
        $service->journal([
            'tenant_id' => $tenantId,
            'actor_user_id' => $userId,
            'actor_label' => $game ? 'jeu (clé API)' : (string) (Session::get('display_name') ?? 'Utilisateur'),
            'action' => 'dse.resolve',
            'object_type' => $found ? (string) $row['entity_type'] : 'dse',
            'object_id' => $found ? (int) $row['entity_id'] : 0,
            'after' => ['found' => $found, 'dse' => $normalized ?? 'invalid'],
        ]);
        if (!$found) {
            return Response::json(['error' => 'not_found'], 404);
        }

        $type = (string) $row['entity_type'];
        $ai = !empty($row['ai_profile_id']) ? $service->aiProfile($tenantId, (int) $row['ai_profile_id']) : null;

        return Response::json([
            'dse' => $row['dse'],
            'entity' => [
                'type' => $type,
                'id' => (int) $row['entity_id'],
                'label' => $row['sheet_name'],
                'url' => url(sprintf(SseIdentifierService::TYPES[$type]['href'], (int) $row['entity_id'])),
            ],
            'identifiers' => ['id_number' => $row['ident_no'], 'codename' => $row['codename'], 'sheet_name' => $row['sheet_name']],
            'ai_profile' => $ai === null ? null : [
                'id' => (int) $ai['id'],
                'label' => $ai['label'],
                'side' => $ai['side'],
                'category' => $ai['category'],
                'identity_id' => $ai['identity_id'] !== null ? (int) $ai['identity_id'] : null,
            ],
        ]);
    }
}
