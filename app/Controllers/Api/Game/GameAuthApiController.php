<?php

declare(strict_types=1);

namespace App\Controllers\Api\Game;

use App\Core\Request;
use App\Core\Response;
use App\Services\Game\GameAuthService;
use App\Support\ComspecApiKeyAuth;
use App\Support\HttpJsonBody;

final class GameAuthApiController
{
    public function __construct(private GameAuthService $auth) {}

    public function password(Request $request, array $params = []): Response
    {
        return $this->respond($this->auth->authPassword($this->body()));
    }

    public function otpRequest(Request $request, array $params = []): Response
    {
        return $this->respond($this->auth->requestOtp($this->body()));
    }

    public function otpVerify(Request $request, array $params = []): Response
    {
        return $this->respond($this->auth->verifyOtp($this->body()));
    }

    public function steamChallenge(Request $request, array $params = []): Response
    {
        return $this->respond($this->auth->steamChallenge($this->body()));
    }

    public function steamExchange(Request $request, array $params = []): Response
    {
        return $this->respond($this->auth->steamExchange($this->body()));
    }

    public function restore(Request $request, array $params = []): Response
    {
        return $this->respond($this->auth->restore($this->body()));
    }

    public function refresh(Request $request, array $params = []): Response
    {
        $session = $this->requireSession();
        if ($session instanceof Response) {
            return $session;
        }

        return $this->respond($this->auth->refresh($this->body(), $session));
    }

    public function logout(Request $request, array $params = []): Response
    {
        $session = $this->requireSession();
        if ($session instanceof Response) {
            return $session;
        }
        $this->auth->logout($session);

        return Response::json(['ok' => true]);
    }

    public function bootstrap(Request $request, array $params = []): Response
    {
        $session = $this->requireSession();
        if ($session instanceof Response) {
            return $session;
        }
        $payload = $this->auth->bootstrap($session);
        if (isset($payload['error'])) {
            return Response::json($payload, 401);
        }

        return Response::json($payload);
    }

    public function profile(Request $request, array $params = []): Response
    {
        $session = $this->requireSession();
        if ($session instanceof Response) {
            return $session;
        }
        $known = (int) ($request->query('revision') ?? $this->body()['revision'] ?? 0);

        return Response::json($this->auth->profile($session, $known));
    }

    public function config(Request $request, array $params = []): Response
    {
        $session = $this->requireSession();
        if ($session instanceof Response) {
            return $session;
        }
        $boot = $this->auth->bootstrap($session);
        if (isset($boot['error'])) {
            return Response::json($boot, 401);
        }

        return Response::json([
            'overwatch' => $boot['overwatch'] ?? [],
            'tenant' => $boot['tenant'] ?? [],
        ]);
    }

    public function branding(Request $request, array $params = []): Response
    {
        $session = $this->requireSession();
        if ($session instanceof Response) {
            return $session;
        }
        $boot = $this->auth->bootstrap($session);
        if (isset($boot['error'])) {
            return Response::json($boot, 401);
        }

        return Response::json($boot['branding'] ?? []);
    }

    public function brandingRender(Request $request, array $params = []): Response
    {
        $slug = strtolower(trim((string) ($params['slug'] ?? $request->query('slug') ?? '')));
        $data = $slug !== '' ? $this->auth->brandingForSlug($slug) : $this->auth->genericBranding();
        $html = $this->brandingHtml($data);
        $res = new Response();
        $res->setStatusCode(200);
        $res->header('Content-Type', 'text/html; charset=utf-8');
        $res->header('Cache-Control', 'public, max-age=120');
        $res->setBody($html);

        return $res;
    }

    /**
     * @param array{ok: bool, status: int, payload: array<string, mixed>} $result
     */
    private function respond(array $result): Response
    {
        return Response::json($result['payload'], $result['status']);
    }

    /**
     * @return array<string, mixed>|Response
     */
    private function requireSession(): array|Response
    {
        $token = $this->bearer();
        $session = $this->auth->sessionFromBearer($token);
        if ($session === null) {
            return Response::json(['authenticated' => false, 'error' => 'SESSION_EXPIRED'], 401);
        }

        return $session;
    }

    private function bearer(): string
    {
        $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if (str_starts_with($auth, 'Bearer ')) {
            return trim(substr($auth, 7));
        }
        $header = (string) ($_SERVER['HTTP_X_COMSPEC_SESSION'] ?? '');
        if ($header !== '') {
            return trim($header);
        }
        $body = $this->body();

        return trim((string) ($body['access_token'] ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function body(): array
    {
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

    /**
     * @param array<string, mixed> $data
     */
    private function brandingHtml(array $data): string
    {
        $name = htmlspecialchars((string) ($data['name'] ?? 'ATHENA'), ENT_QUOTES, 'UTF-8');
        $msg = htmlspecialchars((string) ($data['welcome_message'] ?? ''), ENT_QUOTES, 'UTF-8');
        $img = trim((string) ($data['login_image'] ?? $data['logo'] ?? ''));
        $imgTag = '';
        if ($img !== '' && preg_match('#^https?://#i', $img)) {
            $safe = htmlspecialchars($img, ENT_QUOTES, 'UTF-8');
            $imgTag = '<img src="' . $safe . '" width="480" height="180">';
        }

        return '<html><body bgcolor="#061018">'
            . $imgTag
            . '<br><font color="#e8f4f0" size="4"><b>' . $name . '</b></font>'
            . ($msg !== '' ? '<br><font color="#8aa0b4" size="2">' . $msg . '</font>' : '')
            . '</body></html>';
    }
}
