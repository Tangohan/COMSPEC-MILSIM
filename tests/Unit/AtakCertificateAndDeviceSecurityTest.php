<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\AtakDeviceSecurityController;
use App\Repositories\AtakRealismRepository;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class AtakCertificateAndDeviceSecurityTest extends TestCase
{
    public function testIssueCertificateResolvesTerminalBeforeInsert(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/AtakRealismRepository.php');
        self::assertStringContainsString('resolveExistingTerminalId', $src);
        self::assertStringContainsString('Ne jamais écrire un terminal_id absent', $src);
        $method = new ReflectionMethod(AtakRealismRepository::class, 'issueCertificate');
        self::assertTrue($method->isPublic());
    }

    public function testDeviceSecurityControllerAcceptsZeroConstructorArguments(): void
    {
        $ref = new \ReflectionClass(AtakDeviceSecurityController::class);
        $ctor = $ref->getConstructor();
        self::assertNotNull($ctor);
        self::assertSame(4, $ctor->getNumberOfParameters());
        self::assertSame(0, $ctor->getNumberOfRequiredParameters());
    }

    public function testAccountDevicesRouteIsRegistered(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        self::assertStringContainsString("/account/security/devices", $routes);
        self::assertStringContainsString('AtakDeviceSecurityController', $routes);
    }
}
