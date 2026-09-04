<?php

declare(strict_types=1);

namespace App\Controllers\Api\Game;

use App\Core\Request;
use App\Core\Response;
use App\Services\Game\GameAtakPairingService;
use App\Support\ComspecApiKeyAuth;
use App\Support\HttpJsonBody;

final class GameAtakPairingApiController
{
    public function __construct(private GameAtakPairingService $pairing) {}

    public function start(Request $request, array $params = []): Response
    {
        return $this->respond($this->pairing->start($this->body($request)));
    }

    public function status(Request $request, array $params = []): Response
    {
        $code = trim((string) ($request->query('device_code') ?? $this->body($request)['device_code'] ?? ''));

        return $this->respond($this->pairing->status($code));
    }

    public function redeem(Request $request, array $params = []): Response
    {
        $body = $this->body($request);
        $code = (string) ($body['user_code'] ?? $body['code'] ?? $request->input('user_code', $request->input('code', '')));

        return $this->respond($this->pairing->redeemPortalCode($code, $body));
    }

    public function recoveryRedeem(Request $request, array $params = []): Response
    {
        $body = $this->body($request);
        $code = (string) ($body['code'] ?? $body['user_code'] ?? $request->input('code', ''));

        return $this->respond($this->pairing->redeemPortalCode($code, $body));
    }

    /**
     * @param array{ok: bool, status: int, payload: array<string, mixed>} $result
     */
    private function respond(array $result): Response
    {
        return Response::json($result['payload'], $result['status']);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Request $request): array
    {
        unset($request);
        $json = HttpJsonBody::isMultipart() ? HttpJsonBody::postFields() : ComspecApiKeyAuth::peekJsonObject();
        if ($json === []) {
            $raw = HttpJsonBody::rawJson();
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                $json = is_array($decoded) ? $decoded : [];
            }
        }

        return $json;
    }
}
