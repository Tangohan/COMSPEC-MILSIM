<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Middleware\RateLimitMiddleware;
use App\Repositories\UserRepository;
use App\Services\Security\FileRateLimiter;
use App\Services\Tactical\AtakActivityLogService;
use App\Support\AtakArmaWriteGuard;
use PHPUnit\Framework\TestCase;

/**
 * NET-027 : FileRateLimiter compte par fenêtre FIXE (remise à zéro à window_start + fenêtre). Le site annonçait
 * pourtant la fenêtre entière (RateLimitMiddleware : min(120, fenêtre) ; garde Arma : 60 s ou 5 s fixes) : après un
 * 429, la DLL COMSPEC Link et Tacmap attendaient jusqu'à une fenêtre de plus que le blocage réel.
 * Vrai limiteur, compteurs posés sur disque comme après une rafale ; l'en-tête et le corps doivent annoncer le temps
 * qui reste dans la fenêtre (au moins 1 s, 120 s au plus comme avant).
 */
final class RateLimitRetryAfterTest extends TestCase
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
        parent::setUp();
        // Un test précédent peut avoir retiré $_POST (unset) : Request le copie dans une propriété typée array.
        $this->hadPost = isset($_POST);
        $this->savedPost = $_POST ?? null;
        $_POST = [];
        foreach (['REQUEST_METHOD', 'REQUEST_URI', 'SCRIPT_NAME', 'REMOTE_ADDR'] as $key) {
            if (array_key_exists($key, $_SERVER)) {
                $this->savedServer[$key] = $_SERVER[$key];
            }
        }
        // Adresse propre à ce passage : un compteur laissé par un autre test ne peut pas s'en mêler.
        $this->ip = '198.18.' . random_int(1, 254) . '.' . random_int(1, 254);
    }

    protected function tearDown(): void
    {
        $limiter = new FileRateLimiter();
        foreach ($this->keys as $key) {
            $limiter->clear($key);
        }
        foreach (['REQUEST_METHOD', 'REQUEST_URI', 'SCRIPT_NAME', 'REMOTE_ADDR'] as $key) {
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
        parent::tearDown();
    }

    /** /api/atak/position (200 par 60 s) : fenêtre ouverte il y a 45 s, plafond atteint → 15 s, pas 60. */
    public function testMiddlewareAnnouncesTheTimeLeftInTheFixedWindow(): void
    {
        $before = time();
        $this->seed('rl:/api/atak/position:POST:' . $this->ip, $before - 45, 200);

        $response = $this->throughMiddleware('POST', '/api/atak/position');

        self::assertSame(429, $response->statusCode());
        $header = (int) $response->headerValue('Retry-After');
        self::assertGreaterThanOrEqual(14 - (time() - $before), $header, 'temps restant de la fenêtre');
        self::assertLessThanOrEqual(15, $header, 'pas la fenêtre entière (60 s)');
        $json = json_decode($response->body(), true);
        self::assertIsArray($json);
        self::assertSame('too_many_requests', $json['error'] ?? null);
        self::assertSame($header, $json['retry_after'] ?? null, 'en-tête et corps annoncent le même délai');
    }

    /** Fenêtre de 300 s (/login) : 120 s au plus comme avant, et le temps restant quand il est plus court. */
    public function testLongWindowsKeepTheOldCeilingAndAnnounceTheTimeLeftBelowIt(): void
    {
        $now = time();
        $this->seed('rl:/login:POST:' . $this->ip, $now - 10, 30);
        $capped = $this->throughMiddleware('POST', '/login');
        self::assertSame(429, $capped->statusCode());
        self::assertSame('120', $capped->headerValue('Retry-After'), 'plafond de 120 s gardé');
        self::assertStringContainsString('<html', $capped->body(), 'page HTML pour un navigateur');

        $this->seed('rl:/login:POST:' . $this->ip, $now - 250, 30);
        $short = $this->throughMiddleware('POST', '/login');
        self::assertSame(429, $short->statusCode());
        $header = (int) $short->headerValue('Retry-After');
        self::assertGreaterThanOrEqual(49 - (time() - $now), $header);
        self::assertLessThanOrEqual(50, $header, 'fin de fenêtre dans 50 s : pas 120');
    }

    /**
     * Garde des écritures du jeu : plafond global (150 par 60 s), positions (30 par 60 s) et rafale (10 par 5 s).
     * Chaque refus annonce le temps restant de SA fenêtre, au lieu de 60, 60 et 5 s fixes.
     */
    public function testArmaWriteGuardAnnouncesTheTimeLeftOfTheWindowThatRefuses(): void
    {
        $tenant = 980000 + random_int(1, 9999);
        $now = time();
        $cases = [
            'plafond global' => ['atak:write:%d:steam:%s', $now - 50, 150, 10, 'write'],
            'positions' => ['atak:pos:%d:steam:%s', $now - 20, 30, 40, 'position'],
            'rafale de positions' => ['atak:posburst:%d:steam:%s', $now - 3, 10, 2, 'position'],
        ];
        $guard = new AtakArmaWriteGuard(
            $this->createMock(UserRepository::class),
            $this->createMock(AtakActivityLogService::class),
            new FileRateLimiter()
        );
        $check = new \ReflectionMethod(AtakArmaWriteGuard::class, 'checkRateLimit');
        $n = 0;
        foreach ($cases as $label => [$pattern, $start, $count, $left, $channel]) {
            $steam = '7656119' . str_pad((string) (random_int(1, 999999) * 10 + $n++), 10, '0', STR_PAD_LEFT);
            $this->seed(sprintf($pattern, $tenant, $steam), $start, $count);

            $response = $check->invoke($guard, $tenant, $steam, '', $channel);

            self::assertNotNull($response, $label);
            self::assertSame(429, $response->statusCode(), $label);
            $header = (int) $response->headerValue('Retry-After');
            self::assertGreaterThanOrEqual(max(1, $left - 1 - (time() - $now)), $header, $label);
            self::assertLessThanOrEqual($left, $header, $label . ' : temps restant, pas la fenêtre entière');
            $json = json_decode($response->body(), true);
            self::assertIsArray($json);
            self::assertSame($header, $json['retry_after'] ?? null, $label . ' : en-tête et corps identiques');
            foreach (['atak:write', 'atak:pos', 'atak:posburst'] as $prefix) {
                $this->keys[] = $prefix . ':' . $tenant . ':steam:' . $steam;
            }
        }
    }

    public function testSecondsUntilResetReadsTheWindowWithoutTouchingTheCounter(): void
    {
        $limiter = new FileRateLimiter();
        $now = time();
        $key = 'rl:test-net027:' . $this->ip;

        self::assertSame(1, $limiter->secondsUntilReset($key, 60), 'aucun compteur : 1 s');

        $this->seed($key, $now - 59 - 5, 3);
        self::assertSame(1, $limiter->secondsUntilReset($key, 60), 'fenêtre déjà finie : 1 s');

        $this->seed($key, $now + 500, 3);
        self::assertSame(60, $limiter->secondsUntilReset($key, 60), 'début dans le futur : jamais plus que la fenêtre');

        $this->seed($key, $now - 30, 7);
        $left = $limiter->secondsUntilReset($key, 60);
        self::assertGreaterThanOrEqual(29 - (time() - $now), $left);
        self::assertLessThanOrEqual(30, $left);
        self::assertSame(7, $limiter->attempts($key, 60), 'compteur inchangé');

        file_put_contents($this->counterPath($key), 'pas du json');
        self::assertSame(1, $limiter->secondsUntilReset($key, 60), 'compteur illisible : 1 s');
    }

    private function throughMiddleware(string $method, string $path): \App\Core\Response
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $path;
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REMOTE_ADDR'] = $this->ip;

        return (new RateLimitMiddleware(new FileRateLimiter()))(
            new Request(),
            static fn () => self::fail('la requête aurait dû être freinée')
        );
    }

    /** Compteur tel que FileRateLimiter l'écrit (fichier JSON par clé). */
    private function seed(string $key, int $windowStart, int $count): void
    {
        $path = $this->counterPath($key);
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, json_encode(['window_start' => $windowStart, 'count' => $count]));
        $this->keys[] = $key;
    }

    private function counterPath(string $key): string
    {
        return base_path('storage/cache/ratelimit') . '/' . hash('sha256', $key) . '.json';
    }
}
