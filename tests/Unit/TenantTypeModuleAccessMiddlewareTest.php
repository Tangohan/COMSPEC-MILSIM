<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Session;
use App\Middleware\TenantTypeModuleAccessMiddleware;
use App\Repositories\TenantRepository;
use PDOException;
use PHPUnit\Framework\TestCase;

final class TenantTypeModuleAccessMiddlewareTest extends TestCase
{
    /** @var array{set: bool, locale: mixed, translator: mixed} */
    private array $savedLocale = ['set' => false, 'locale' => null, 'translator' => null];

    /**
     * Le site est anglais par défaut et d'autres tests posent $GLOBALS['__app_locale'] :
     * on fige le français pour les assertions « réessayez », puis on restaure l'état.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->savedLocale = [
            'set' => array_key_exists('__app_locale', $GLOBALS),
            'locale' => $GLOBALS['__app_locale'] ?? null,
            'translator' => $GLOBALS['__app_translator'] ?? null,
        ];
        $this->useLocale('fr');
    }

    private function useLocale(string $locale): void
    {
        $GLOBALS['__app_locale'] = $locale;
        unset($GLOBALS['__app_translator']);
    }

    protected function tearDown(): void
    {
        Session::forget('tenant_id');
        if ($this->savedLocale['set']) {
            $GLOBALS['__app_locale'] = $this->savedLocale['locale'];
        } else {
            unset($GLOBALS['__app_locale']);
        }
        if ($this->savedLocale['translator'] !== null) {
            $GLOBALS['__app_translator'] = $this->savedLocale['translator'];
        } else {
            unset($GLOBALS['__app_translator']);
        }
        parent::tearDown();
    }

    public function testHubGoneAwayReturnsActionablePageWithoutSqlstate(): void
    {
        $_SESSION['tenant_id'] = 7;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/hub';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        $repo = $this->createMock(TenantRepository::class);
        $gone = new PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
        $gone->errorInfo = ['HY000', 2006, 'MySQL server has gone away'];
        $repo->method('findById')->willThrowException($gone);

        $middleware = new TenantTypeModuleAccessMiddleware($repo);
        $response = $middleware(new Request(), static fn () => self::fail('next must not run'));

        self::assertSame(503, $response->statusCode());
        self::assertSame('30', $response->headerValue('Retry-After'));
        $body = $response->body();
        self::assertStringNotContainsString('SQLSTATE', $body);
        self::assertStringNotContainsString('PDOException', $body);
        self::assertStringNotContainsString('server has gone away', $body);
        self::assertStringContainsString('réessayez', mb_strtolower($body));
    }

    public function testHubGoneAwayFollowsEnglishLocale(): void
    {
        $this->useLocale('en');
        $_SESSION['tenant_id'] = 7;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/hub';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        $repo = $this->createMock(TenantRepository::class);
        $gone = new PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
        $gone->errorInfo = ['HY000', 2006, 'MySQL server has gone away'];
        $repo->method('findById')->willThrowException($gone);

        $response = (new TenantTypeModuleAccessMiddleware($repo))(new Request(), static fn () => self::fail('next must not run'));

        self::assertSame(503, $response->statusCode());
        self::assertStringNotContainsString('SQLSTATE', $response->body());
        self::assertStringContainsString('try again', mb_strtolower($response->body()));
    }

    public function testApiGoneAwayReturnsJsonWithoutSqlstate(): void
    {
        $_SESSION['tenant_id'] = 7;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/atak/units';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        $repo = $this->createMock(TenantRepository::class);
        $gone = new PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
        $gone->errorInfo = ['HY000', 2006, 'MySQL server has gone away'];
        $repo->method('findById')->willThrowException($gone);

        $middleware = new TenantTypeModuleAccessMiddleware($repo);
        $response = $middleware(new Request(), static fn () => self::fail('next must not run'));

        self::assertSame(503, $response->statusCode());
        $payload = json_decode($response->body(), true);
        self::assertIsArray($payload);
        self::assertSame('database_unavailable', $payload['error'] ?? null);
        self::assertArrayHasKey('message', $payload);
        self::assertStringNotContainsString('SQLSTATE', (string) $payload['message']);
        self::assertStringNotContainsString('PDO', (string) $payload['message']);
    }
}
