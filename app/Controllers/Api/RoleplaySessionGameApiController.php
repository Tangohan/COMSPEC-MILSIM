<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\UserRepository;
use App\Services\Personnel\RoleplaySessionGameApp;
use App\Services\Personnel\RoleplaySessionNotifier;
use App\Support\ClientIp;
use App\Support\ComspecApiKeyAuth;

/**
 * App Session du téléphone ATAK (contrat v1 : docs/technical/ICD-SESSION-JEU.md).
 *
 *  GET  /api/atak/session/current           session du joueur (ou de sa communauté), état de l'app, jauge, sa présence
 *  POST /api/atak/session/join     {"code"} rejoindre avec le code annoncé par l'animateur
 *  POST /api/atak/session/checkin  {"session_id"}  pointer sa présence (session démarrée)
 *  POST /api/atak/session/leave    {"session_id"}  annoncer son départ (ferme son temps ouvert)
 *
 * Authentification : comme les autres routes /api/atak/*, protégées en amont par ComspecApiKeyAuth ; il faut ici la session
 * de jeu du compte (jeton émis par COMSPEC Link), car le code rattache un membre précis. Clé de communauté seule : 401.
 * Les anciennes routes du mod (/api/atak/game-session/*) ne changent pas.
 */
final class RoleplaySessionGameApiController
{
    private ?RoleplaySessionGameApp $app;

    public function __construct(?RoleplaySessionGameApp $app = null)
    {
        $this->app = $app;
    }

    private function app(): RoleplaySessionGameApp
    {
        return $this->app ??= new RoleplaySessionGameApp(null, null, null, new RoleplaySessionNotifier());
    }

    /** @return array{0: int, 1: int, 2: string} communauté, compte, Steam */
    private function identity(): array
    {
        $tenantId = (int) (ComspecApiKeyAuth::matchedTenantId() ?? 0);
        $userId = (int) (ComspecApiKeyAuth::matchedUserId() ?? 0);
        $steam = (string) (ComspecApiKeyAuth::matchedSteamId() ?? '');
        if ($userId < 1 && $tenantId > 0 && $steam !== '') {
            try {
                $userId = (int) ((new UserRepository())->findBySteamIdForTenant($tenantId, $steam)['id'] ?? 0);
            } catch (\Throwable) {
                $userId = 0;
            }
        }

        return [$tenantId, $userId, $steam];
    }

    /** @return array<string, mixed> */
    private function body(): array
    {
        return ComspecApiKeyAuth::peekJsonObject();
    }

    /** @param array{0: int, 1: array<string, mixed>} $result */
    private function respond(array $result): Response
    {
        [$status, $body] = $result;
        $res = Response::json($body, $status)->header('Cache-Control', 'private, no-store');
        if ($status === 429 && isset($body['retry_after'])) {
            $res->header('Retry-After', (string) (int) $body['retry_after']);
        }

        return $res;
    }

    private function guarded(callable $fn): Response
    {
        try {
            return $this->respond($fn());
        } catch (\PDOException) {
            return $this->respond([503, ['v' => RoleplaySessionGameApp::VERSION, 'ok' => false, 'error' => 'unavailable']]);
        }
    }

    public function current(Request $request, array $params = []): Response
    {
        [$tenantId, $userId] = $this->identity();

        return $this->guarded(fn (): array => $this->app()->current($tenantId, $userId));
    }

    public function join(Request $request, array $params = []): Response
    {
        [$tenantId, $userId, $steam] = $this->identity();
        $body = $this->body();

        return $this->guarded(fn (): array => $this->app()->join($tenantId, $userId, $steam, $body['code'] ?? null, ClientIp::resolve()));
    }

    public function checkin(Request $request, array $params = []): Response
    {
        [$tenantId, $userId] = $this->identity();
        $sid = $this->body()['session_id'] ?? 0;

        return $this->guarded(fn (): array => $this->app()->checkin($tenantId, $userId, is_numeric($sid) ? (int) $sid : 0));
    }

    public function leave(Request $request, array $params = []): Response
    {
        [$tenantId, $userId] = $this->identity();
        $sid = $this->body()['session_id'] ?? 0;

        return $this->guarded(fn (): array => $this->app()->leave($tenantId, $userId, is_numeric($sid) ? (int) $sid : 0));
    }
}
