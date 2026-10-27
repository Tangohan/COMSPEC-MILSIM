<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PortalHost;
use PHPUnit\Framework\TestCase;

final class PortalHostTest extends TestCase
{
    private mixed $previousAppUrl;
    private mixed $previousBasePath;
    private mixed $previousScriptName;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousAppUrl = $_ENV['APP_URL'] ?? null;
        $this->previousBasePath = $_ENV['APP_BASE_PATH'] ?? null;
        $this->previousScriptName = $_SERVER['SCRIPT_NAME'] ?? null;
        $_ENV['APP_URL'] = 'https://athena-comspec.eu';
        $_ENV['APP_BASE_PATH'] = '';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
    }

    protected function tearDown(): void
    {
        foreach (['APP_URL' => $this->previousAppUrl, 'APP_BASE_PATH' => $this->previousBasePath] as $k => $v) {
            if ($v === null) {
                unset($_ENV[$k]);
            } else {
                $_ENV[$k] = $v;
            }
        }
        if ($this->previousScriptName === null) {
            unset($_SERVER['SCRIPT_NAME']);
        } else {
            $_SERVER['SCRIPT_NAME'] = $this->previousScriptName;
        }
        parent::tearDown();
    }

    /** @return array<string, string> */
    private function browser(string $uri, string $host = 'athena.ttrd.fr'): array
    {
        return [
            'HTTP_HOST' => $host,
            'REQUEST_METHOD' => 'GET',
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,*/*;q=0.8',
            'REQUEST_URI' => $uri,
        ];
    }

    public function testOldDomainPagesPointToTheSamePageOnTheNewDomain(): void
    {
        self::assertSame('athena-comspec.eu', PortalHost::host());
        self::assertSame('https://athena-comspec.eu/', PortalHost::legacyNoticeTarget($this->browser('/')));
        self::assertSame('https://athena-comspec.eu/lier?code=AB12', PortalHost::legacyNoticeTarget($this->browser('/public/lier?code=AB12')));
        self::assertSame('https://athena-comspec.eu/', PortalHost::legacyNoticeTarget($this->browser('/public')));
        self::assertSame('https://athena-comspec.eu/atak', PortalHost::legacyNoticeTarget(['HTTP_HOST' => 'athena.ttrd.fr:443'] + $this->browser('/atak')));
    }

    public function testModsApiFilesAndNewDomainAreNeverInterrupted(): void
    {
        self::assertNull(PortalHost::legacyNoticeTarget($this->browser('/public/api/atak/ping')));
        self::assertNull(PortalHost::legacyNoticeTarget($this->browser('/api/atak/ping')));
        self::assertNull(PortalHost::legacyNoticeTarget($this->browser('/uploads/recon/x.jpg')));
        self::assertNull(PortalHost::legacyNoticeTarget($this->browser('/public/assets/workshop/sse/fr/banniere.png')));
        self::assertNull(PortalHost::legacyNoticeTarget($this->browser('/', 'athena-comspec.eu')));
        self::assertNull(PortalHost::legacyNoticeTarget(['REQUEST_METHOD' => 'POST'] + $this->browser('/login')));
        self::assertNull(PortalHost::legacyNoticeTarget(['HTTP_ACCEPT' => 'application/json'] + $this->browser('/connect/x')));
        self::assertNull(PortalHost::legacyNoticeTarget(['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'] + $this->browser('/atak')));

        $_ENV['APP_URL'] = 'https://athena.ttrd.fr';
        self::assertNull(PortalHost::legacyNoticeTarget($this->browser('/')));
    }

    public function testFrontControllerShowsTheAnnouncementBeforeTheApp(): void
    {
        $root = dirname(__DIR__, 2);
        $index = (string) file_get_contents($root . '/public/index.php');
        self::assertStringContainsString('PortalHost::legacyNoticeTarget($_SERVER)', $index);
        self::assertLessThan(strpos($index, 'MaintenanceGuard::isInfrastructurePath'), strpos($index, 'legacyNoticeTarget'));
        $view = (string) file_get_contents($root . '/views/errors/domain_moved.php');
        self::assertStringContainsString('$domainMovedTarget', $view);
        self::assertStringContainsString('class="btn"', $view);
    }
}
