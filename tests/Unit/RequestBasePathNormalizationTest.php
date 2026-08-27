<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * VPS : APP_BASE_PATH vide + document root = public/.
 * Les mods Workshop appellent encore /public/api/… ; le routeur doit voir /api/…
 * sans avaler un second /public (double préfixe).
 */
final class RequestBasePathNormalizationTest extends TestCase
{
    /** @var mixed */
    private $previousBasePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousBasePath = $_ENV['APP_BASE_PATH'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->previousBasePath === null) {
            unset($_ENV['APP_BASE_PATH']);
        } else {
            $_ENV['APP_BASE_PATH'] = $this->previousBasePath;
        }
        parent::tearDown();
    }

    public function testVpsEmptyBasePathStripsWorkshopPublicPrefix(): void
    {
        $_ENV['APP_BASE_PATH'] = '';

        $path = Request::normalizePathFromServer([
            'REQUEST_URI' => '/public/api/atak/ping',
            'SCRIPT_NAME' => '/index.php',
        ]);

        self::assertSame('/api/atak/ping', $path);
    }

    public function testVpsEmptyBasePathStripsPublicOnClientInit(): void
    {
        $_ENV['APP_BASE_PATH'] = '';

        $path = Request::normalizePathFromServer([
            'REQUEST_URI' => '/public/api/atak/client-init?x=1',
            'SCRIPT_NAME' => '/index.php',
        ]);

        self::assertSame('/api/atak/client-init', $path);
    }

    public function testDoublePublicPrefixDoesNot404TheRoute(): void
    {
        $_ENV['APP_BASE_PATH'] = '';

        $path = Request::normalizePathFromServer([
            'REQUEST_URI' => '/public/public/api/atak/ping',
            'SCRIPT_NAME' => '/index.php',
        ]);

        self::assertSame('/api/atak/ping', $path);
    }

    public function testRootPublicIsHomepage(): void
    {
        $_ENV['APP_BASE_PATH'] = '';

        $path = Request::normalizePathFromServer([
            'REQUEST_URI' => '/public',
            'SCRIPT_NAME' => '/index.php',
        ]);

        self::assertSame('/', $path);
    }

    public function testCanonicalApiPathUnchanged(): void
    {
        $_ENV['APP_BASE_PATH'] = '';

        $path = Request::normalizePathFromServer([
            'REQUEST_URI' => '/api/atak/ping',
            'SCRIPT_NAME' => '/index.php',
        ]);

        self::assertSame('/api/atak/ping', $path);
    }

    public function testUnrelatedPathNamedPublicSomethingIsNotStripped(): void
    {
        $_ENV['APP_BASE_PATH'] = '';

        $path = Request::normalizePathFromServer([
            'REQUEST_URI' => '/publications',
            'SCRIPT_NAME' => '/index.php',
        ]);

        self::assertSame('/publications', $path);
    }

    public function testHostingerBasePathStillStripsOnce(): void
    {
        $_ENV['APP_BASE_PATH'] = '/public';

        $path = Request::normalizePathFromServer([
            'REQUEST_URI' => '/public/api/atak/game-link/redeem',
            'SCRIPT_NAME' => '/public/index.php',
        ]);

        self::assertSame('/api/atak/game-link/redeem', $path);
    }
}
