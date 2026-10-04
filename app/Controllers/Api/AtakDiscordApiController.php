<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Atak\AtakDiscordService;
use App\Support\AtakArmaWriteGuard;
use App\Support\ComspecApiKeyAuth;
use App\Support\SteamId;

/**
 * App Discord du téléphone ATAK : le jeu envoie un texte court, Athena le publie dans le salon
 * relié par la communauté. Le lien du salon ne sort jamais du serveur.
 */
final class AtakDiscordApiController
{
    /** @var array<string, mixed>|null */
    private ?array $jsonBodyCache = null;

    public function __construct(
        private ?AtakDiscordService $service = null,
        private ?AtakArmaWriteGuard $armaGuard = null,
    ) {
        $this->service ??= new AtakDiscordService();
        $this->armaGuard ??= new AtakArmaWriteGuard();
    }

    /** POST /api/atak/discord/send — appel jeu authentifié (clé COMSPEC Link + session). */
    public function send(Request $request, array $params = []): Response
    {
        // Canal réservé au jeu : une session navigateur ne publie pas par ici.
        if (!ComspecApiKeyAuth::armaInlineAuthOk() || ComspecApiKeyAuth::extractPresentedKey() === '') {
            return Response::json(['error' => 'Unauthorized', 'message' => 'Authentification terrain requise.'], 401);
        }
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId === null || $tenantId < 2) {
            return Response::json([
                'error' => 'tenant_context_required',
                'message' => 'Communauté non identifiée. Reliez le compte Athena en jeu.',
            ], 403);
        }
        $body = $this->jsonBody($request);
        $actor = $this->armaGuard->assertActor($request, $tenantId, $body, false);
        if ($actor instanceof Response) {
            return $actor;
        }

        $steam = is_array($actor) && !empty($actor['steam_uid']) ? SteamId::normalize((string) $actor['steam_uid']) : null;
        if ($steam === null || $steam === '') {
            $steam = SteamId::normalize((string) ($body['steam_uid'] ?? $body['submitter_steam_id'] ?? ''));
        }
        $callsign = (string) ($body['callsign'] ?? $body['call_sign'] ?? '');
        // Sans Steam : le délai par joueur s'applique à l'indicatif et à l'adresse.
        $playerKey = ($steam !== null && $steam !== '') ? 's:' . $steam : 'c:' . mb_strtolower($callsign) . '|' . (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        $text = (string) ($body['text'] ?? $body['message'] ?? '');
        if (mb_strlen($text) > AtakDiscordService::MAX_LENGTH * 2) {
            return Response::json(['error' => 'too_long', 'message' => 'Message trop long (500 caractères au plus).'], 422);
        }
        $result = $this->service->sendFromPhone(
            $tenantId,
            $playerKey,
            $callsign,
            $text,
            (string) ($body['kind'] ?? 'MSG'),
            (string) ($body['grid'] ?? '')
        );
        $out = ['ok' => $result['ok'], 'message' => $result['message']];
        if (!$result['ok']) {
            $out['error'] = $result['error'] ?? 'error';
        }

        return Response::json($out, $result['status']);
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
        $body = $this->jsonBody($request);
        if (!empty($body['tenant_id']) && (int) $body['tenant_id'] > 0) {
            return (int) $body['tenant_id'];
        }
        $q = $request->query('tenant_id');
        if ($q !== null && $q !== '' && (int) $q > 0) {
            return (int) $q;
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function jsonBody(Request $request): array
    {
        if ($this->jsonBodyCache !== null) {
            return $this->jsonBodyCache;
        }
        $raw = file_get_contents('php://input');
        $decoded = ($raw === false || $raw === '') ? null : json_decode($raw, true);
        $this->jsonBodyCache = is_array($decoded) ? $decoded : [];

        return $this->jsonBodyCache;
    }
}
