<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Response;
use App\Middleware\LoadShedMiddleware;
use App\Services\Watch\PlanRankIndex;
use App\Services\Watch\WatchSpool;
use App\Services\Watch\WatchState;
use PHPUnit\Framework\TestCase;

/**
 * Priorité sous charge : qui est ralenti, quand, et surtout dans quels cas personne ne l'est.
 */
final class LoadShedMiddlewareTest extends TestCase
{
    private const NOW = 1_800_000_000;

    private string $dir = '';

    /** @var array<string, mixed> */
    private array $savedServer = [];

    /** @var array<string, mixed> */
    private array $savedSession = [];

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/watch-mw-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0775, true);
        WatchSpool::setDir($this->dir);
        PlanRankIndex::forget();
        $this->savedServer = $_SERVER;
        $this->savedSession = $_SESSION ?? [];
        foreach (['HTTP_AUTHORIZATION', 'HTTP_X_COMSPEC_KEY', 'HTTP_X_ATAK_KEY', 'HTTP_X_COMSPEC_SESSION'] as $k) {
            unset($_SERVER[$k]);
        }
        // Index des formules : 1 Gratuit, 2 Standard, 3 Pro, 4 Pro+.
        file_put_contents($this->dir . '/plans.json', json_encode(['at' => self::NOW, 't' => [1 => 0, 2 => 1, 3 => 2, 4 => 3]]));
        $_SESSION = ['user_id' => 10, 'tenant_id' => 1];
    }

    protected function tearDown(): void
    {
        WatchSpool::setDir(null);
        PlanRankIndex::forget();
        WatchState::forget();
        $_SERVER = $this->savedServer;
        $_SESSION = $this->savedSession;
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    /** @return array<string, mixed> */
    private function state(int $level, array $over = []): array
    {
        return $over + [
            'at' => self::NOW, 'shed_enabled' => true, 'shed_percent' => 60, 'level' => $level,
            'sensor' => ['reliable' => true],
        ];
    }

    private function request(string $method, string $path): Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $path;
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        return new Request();
    }

    /** Vrai si la requête est ralentie. */
    private function slowed(?array $state, string $method, string $path, float $roll = 0.0): bool
    {
        $reached = false;
        $mw = new LoadShedMiddleware($state, self::NOW, static fn (): float => $roll);
        $response = $mw($this->request($method, $path), function () use (&$reached): Response {
            $reached = true;

            return new Response();
        });
        if ($reached) {
            self::assertSame(200, $response->statusCode());

            return false;
        }
        self::assertSame(503, $response->statusCode());

        return true;
    }

    public function testFreeCommunityIsSlowedFirstOnANonCriticalPage(): void
    {
        self::assertTrue($this->slowed($this->state(1), 'GET', '/c/une-communaute'));
        $_SESSION['tenant_id'] = 2;
        self::assertFalse($this->slowed($this->state(1), 'GET', '/c/une-communaute'), 'Standard : pas au palier 1');
        self::assertTrue($this->slowed($this->state(2), 'GET', '/c/une-communaute'), 'Standard : palier 2');
        $_SESSION['tenant_id'] = 3;
        self::assertFalse($this->slowed($this->state(2), 'GET', '/c/une-communaute'), 'Pro : pas au palier 2');
        self::assertTrue($this->slowed($this->state(3), 'GET', '/c/une-communaute'), 'Pro : palier 3');
        $_SESSION['tenant_id'] = 4;
        foreach ([1, 2, 3] as $level) {
            self::assertFalse($this->slowed($this->state($level), 'GET', '/c/une-communaute'), 'Pro+ jamais, palier ' . $level);
        }
    }

    public function testNobodyIsSlowedWithoutAReliableFreshEnabledState(): void
    {
        $paths = ['/c/une-communaute', '/communities', '/members'];
        $states = [
            'capteur absent' => null,
            'état trop ancien' => $this->state(3, ['at' => self::NOW - 600]),
            'mesure non fiable' => $this->state(3, ['sensor' => ['reliable' => false]]),
            'ralentissement désactivé' => $this->state(3, ['shed_enabled' => false]),
            'palier zéro' => $this->state(0),
        ];
        foreach ($states as $label => $state) {
            foreach ($paths as $path) {
                self::assertFalse($this->slowed($state, 'GET', $path), $label . ' ' . $path);
            }
        }
    }

    public function testMissingOrCorruptStateFileMeansNoSlowdown(): void
    {
        $run = function (): bool {
            WatchState::forget();
            $reached = false;
            (new LoadShedMiddleware())($this->request('GET', '/c/une-communaute'), function () use (&$reached): Response {
                $reached = true;

                return new Response();
            });

            return $reached;
        };
        // Aucun fichier d'état : capteur jamais démarré ou en panne.
        self::assertTrue($run());
        file_put_contents($this->dir . '/state.json', '{corrompu');
        self::assertTrue($run());
        // Un état de la veille, même au palier le plus haut, ne ralentit personne.
        file_put_contents($this->dir . '/state.json', json_encode($this->state(3, ['at' => time() - 86400])));
        self::assertTrue($run());
    }

    public function testPlanIndexFailuresNeverSlowAnyone(): void
    {
        // Communauté absente de l'index.
        $_SESSION['tenant_id'] = 99;
        self::assertFalse($this->slowed($this->state(3), 'GET', '/c/une-communaute'));
        // Visiteur sans session.
        $_SESSION = [];
        self::assertFalse($this->slowed($this->state(3), 'GET', '/c/une-communaute'));
        // Index trop ancien.
        $_SESSION = ['user_id' => 10, 'tenant_id' => 1];
        file_put_contents($this->dir . '/plans.json', json_encode(['at' => self::NOW - 7200, 't' => [1 => 0]]));
        PlanRankIndex::forget();
        self::assertFalse($this->slowed($this->state(3), 'GET', '/c/une-communaute'));
        // Index absent ou illisible.
        file_put_contents($this->dir . '/plans.json', 'illisible');
        PlanRankIndex::forget();
        self::assertFalse($this->slowed($this->state(3), 'GET', '/c/une-communaute'));
        unlink($this->dir . '/plans.json');
        PlanRankIndex::forget();
        self::assertFalse($this->slowed($this->state(3), 'GET', '/c/une-communaute'));
    }

    public function testCriticalRoutesPassForAFreeCommunityAtTheHighestLevel(): void
    {
        $critical = [
            ['POST', '/login'], ['GET', '/login'], ['POST', '/logout'], ['POST', '/api/stripe/webhook'], ['POST', '/api/paypal/webhook'],
            ['GET', '/admin'], ['GET', '/admin/system/surcharge'], ['POST', '/admin/system/surcharge/reglages'],
            ['POST', '/api/atak/mod-report'], ['GET', '/api/atak/orders'], ['POST', '/api/atak/orders'],
            ['GET', '/api/health'], ['POST', '/platform/upgrade/checkout'], ['GET', '/atak'], ['GET', '/m/carte'],
        ];
        foreach ($critical as [$method, $path]) {
            self::assertFalse($this->slowed($this->state(3), $method, $path), $method . ' ' . $path);
        }
    }

    public function testBrowserWritesAndApiCallsAreNotSlowed(): void
    {
        self::assertFalse($this->slowed($this->state(3), 'POST', '/c/une-communaute/contact'));
        self::assertFalse($this->slowed($this->state(3), 'GET', '/api/atak/units'));
        self::assertFalse($this->slowed($this->state(3), 'POST', '/api/atak/position'));
    }

    public function testTheSlowedShareFollowsTheConfiguredPercentage(): void
    {
        self::assertTrue($this->slowed($this->state(1, ['shed_percent' => 40]), 'GET', '/c/x', 0.39));
        self::assertFalse($this->slowed($this->state(1, ['shed_percent' => 40]), 'GET', '/c/x', 0.4));
    }

    public function testApiRefusalTellsTheModToWaitWithoutTriggeringAGlobalPauseOrAnAuthRefusal(): void
    {
        $response = LoadShedMiddleware::refusal($this->request('POST', '/api/atak/position'), 2, 4);
        self::assertSame(503, $response->statusCode(), '503 : pause de cette seule route côté mod (un 429 met toute la liaison en pause)');
        $retry = (int) $response->headerValue('Retry-After');
        self::assertSame(49, $retry);
        self::assertSame('shed', $response->headerValue('X-Athena-Load'));
        $body = $response->body();
        self::assertStringContainsString('"retry_after":49', $body);
        // La DLL lit ces mots comme une maintenance (pause globale) ou un refus définitif.
        foreach (['maintenance', 'unauthorized', 'forbidden', 'database'] as $word) {
            self::assertStringNotContainsString($word, strtolower($body));
        }
        $decoded = json_decode($body, true);
        self::assertSame('server_busy', $decoded['error']);
    }

    public function testPageRefusalIsAnHtmlPageWithRetryAfter(): void
    {
        $response = LoadShedMiddleware::refusal($this->request('GET', '/c/une-communaute'), 1, 0);
        self::assertSame(503, $response->statusCode());
        self::assertSame('30', $response->headerValue('Retry-After'));
        self::assertStringContainsString('text/html', (string) $response->headerValue('Content-Type'));
        self::assertStringNotContainsString('maintenance', strtolower($response->body()));
    }
}
