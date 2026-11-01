<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Response;
use App\Middleware\RateLimitMiddleware;
use App\Services\Security\FileRateLimiter;
use App\Support\ComspecApiKeyAuth;
use PHPUnit\Framework\TestCase;

/**
 * Plafond des sondages GET /api/* : un joueur (clé ou jeton du jeu) ou un poste web emballé reçoit 429 + Retry-After
 * (en-tête et champ JSON retry_after, tous deux lus par COMSPEC Link) ; l'usage normal, les tuiles et la sonde de vie
 * passent sans compteur.
 */
final class ApiPollRateLimitTest extends TestCase
{
    /** @var list<string> */
    private array $keys = [];

    /** @var array<string, mixed> */
    private array $savedServer = [];

    private bool $hadPost = false;

    private mixed $savedPost = null;

    private string $ip = '';

    protected function setUp(): void
    {
        $this->hadPost = isset($_POST);
        $this->savedPost = $_POST ?? null;
        $_POST = [];
        foreach (['REQUEST_METHOD', 'REQUEST_URI', 'SCRIPT_NAME', 'REMOTE_ADDR', 'HTTP_X_COMSPEC_KEY'] as $key) {
            if (array_key_exists($key, $_SERVER)) {
                $this->savedServer[$key] = $_SERVER[$key];
            }
        }
        $this->ip = '198.19.' . random_int(1, 254) . '.' . random_int(1, 254);
        ComspecApiKeyAuth::resetForTests();
    }

    protected function tearDown(): void
    {
        $limiter = new FileRateLimiter();
        foreach ($this->keys as $key) {
            $limiter->clear($key);
        }
        foreach (['REQUEST_METHOD', 'REQUEST_URI', 'SCRIPT_NAME', 'REMOTE_ADDR', 'HTTP_X_COMSPEC_KEY'] as $key) {
            if (array_key_exists($key, $this->savedServer)) {
                $_SERVER[$key] = $this->savedServer[$key];
            } else {
                unset($_SERVER[$key]);
            }
        }
        if ($this->hadPost) {
            $_POST = $this->savedPost;
        } else {
            unset($_POST);
        }
        ComspecApiKeyAuth::resetForTests();
    }

    public function testRunawayGameClientGets429WithRetryAfterInHeaderAndBody(): void
    {
        $key = 'rl:api_poll:game:ip:' . $this->ip;
        $this->seed($key, time() - 40, RateLimitMiddleware::GAME_POLL_MAX_PER_MIN);

        $response = $this->get('/api/atak/markers', true, static fn () => self::fail('aurait dû être freiné'));

        self::assertSame(429, $response->statusCode());
        $retry = (int) $response->headerValue('Retry-After');
        self::assertGreaterThanOrEqual(1, $retry);
        self::assertLessThanOrEqual(20, $retry, 'temps restant de la fenêtre, pas 60 s');
        $json = json_decode($response->body(), true);
        self::assertIsArray($json);
        self::assertSame('too_many_requests', $json['error'] ?? null);
        self::assertSame($retry, $json['retry_after'] ?? null);
    }

    public function testNormalGamePollingPassesAndIsCounted(): void
    {
        $key = 'rl:api_poll:game:ip:' . $this->ip;
        $this->keys[] = $key;
        $passed = 0;
        for ($i = 0; $i < 5; $i++) {
            $this->get('/api/units', true, static function () use (&$passed): Response {
                $passed++;

                return Response::json([]);
            });
        }
        self::assertSame(5, $passed);
        self::assertSame(5, (new FileRateLimiter())->attempts($key, 60));
    }

    public function testTilesAndHeartbeatAreNeverCounted(): void
    {
        $key = 'rl:api_poll:game:ip:' . $this->ip;
        $this->seed($key, time() - 10, RateLimitMiddleware::GAME_POLL_MAX_PER_MIN + 50);
        foreach (['/api/atak/tiles', '/api/atak/ping', '/api/health', '/api/recon/images/12/file'] as $path) {
            $response = $this->get($path, true, static fn (): Response => Response::json(['ok' => true]));
            self::assertSame(200, $response->statusCode(), $path);
        }
    }

    public function testAnonymousBrowserGetOnTacticalApiKeepsItsOldExemption(): void
    {
        $response = $this->get('/api/atak/markers', false, static fn (): Response => Response::json([]));
        self::assertSame(200, $response->statusCode());
    }

    private function get(string $path, bool $gameKey, callable $next): Response
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $path;
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REMOTE_ADDR'] = $this->ip;
        if ($gameKey) {
            $_SERVER['HTTP_X_COMSPEC_KEY'] = 'cle-de-test';
        } else {
            unset($_SERVER['HTTP_X_COMSPEC_KEY']);
        }

        return (new RateLimitMiddleware(new FileRateLimiter()))(new Request(), $next);
    }

    private function seed(string $key, int $windowStart, int $count): void
    {
        $path = base_path('storage/cache/ratelimit') . '/' . hash('sha256', $key) . '.json';
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, json_encode(['window_start' => $windowStart, 'count' => $count]));
        $this->keys[] = $key;
    }
}
