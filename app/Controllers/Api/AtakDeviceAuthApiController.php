<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AtakDeviceAuthRepository;
use App\Repositories\AtakRealismRepository;
use App\Services\Atak\AtakDeviceAuthService;
use App\Services\Game\GameAuthService;
use App\Services\Security\FileRateLimiter;
use App\Support\HttpJsonBody;

final class AtakDeviceAuthApiController
{
    private AtakDeviceAuthService $service;
    private FileRateLimiter $limits;

    public function __construct(?AtakDeviceAuthService $service = null, ?FileRateLimiter $limits = null)
    {
        $this->service = $service ?? new AtakDeviceAuthService(
            new AtakDeviceAuthRepository(),
            Container::get(GameAuthService::class),
            Container::get(AtakRealismRepository::class),
        );
        $this->limits = $limits ?? new FileRateLimiter();
    }

    private function body(Request $r): array
    {
        $v = json_decode(HttpJsonBody::rawJson(32768), true);

        return is_array($v) ? $v : [];
    }

    private function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    private function limited(string $scope, string $terminal, int $max, int $seconds): bool
    {
        return $this->limits->tooManyAttempts('atak:' . $scope . ':' . $this->ip() . ':' . hash('sha256', $terminal), $max, $seconds);
    }

    public function start(Request $r, array $p = []): Response
    {
        $b = $this->body($r);
        $t = (string) ($b['terminal_uid'] ?? '');
        if ($this->limited('pair-start', $t, 8, 600)) {
            return Response::json(['error' => 'rate_limited', 'retry_after' => 600], 429)->header('Retry-After', '600');
        }
        $out = $this->service->start($b, $this->ip(), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));

        return Response::json($out['payload'], $out['status_code'])->header('Cache-Control', 'no-store');
    }

    public function status(Request $r, array $p = []): Response
    {
        $secret = trim((string) $r->query('device_code', ''));
        if ($this->limited('pair-status', substr(hash('sha256', $secret), 0, 20), 360, 600)) {
            return Response::json(['error' => 'rate_limited'], 429)->header('Retry-After', '2');
        }
        $out = $this->service->status($secret, $this->ip());

        return Response::json($out['payload'], $out['status_code'])->header('Cache-Control', 'no-store');
    }

    public function redeem(Request $r, array $p = []): Response
    {
        $b = $this->body($r);
        $t = (string) ($b['terminal_uid'] ?? '');
        if ($this->limited('recovery', $t, 6, 900)) {
            return Response::json(['error' => 'rate_limited', 'retry_after' => 900], 429)->header('Retry-After', '900');
        }
        $out = $this->service->redeem($b, $this->ip());

        return Response::json($out['payload'], $out['status_code'])->header('Cache-Control', 'no-store');
    }
}
