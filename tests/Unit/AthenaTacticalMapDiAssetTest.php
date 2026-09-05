<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AthenaTacticalMapDiAssetTest extends TestCase
{
    public function testAdminControllerConstructorIsOptionalForRouterFallback(): void
    {
        $ref = new ReflectionClass(\App\Controllers\Admin\AdminAthenaTacticalMapController::class);
        $params = $ref->getConstructor()?->getParameters() ?? [];
        self::assertCount(1, $params);
        self::assertTrue($params[0]->allowsNull());
        self::assertTrue($params[0]->isDefaultValueAvailable());
    }

    public function testApiControllerConstructorIsOptionalForRouterFallback(): void
    {
        $ref = new ReflectionClass(\App\Controllers\Api\AthenaTacticalApiController::class);
        $params = $ref->getConstructor()?->getParameters() ?? [];
        self::assertCount(1, $params);
        self::assertTrue($params[0]->allowsNull());
        self::assertTrue($params[0]->isDefaultValueAvailable());
    }

    public function testContainerRegistersTacticalMapServices(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Core/Container.php');
        self::assertStringContainsString('AdminAthenaTacticalMapController::class', $src);
        self::assertStringContainsString('AthenaTacticalApiController::class', $src);
        self::assertStringContainsString('AthenaTacticalRepository::class', $src);
    }

    public function testRootHtaccessRoutesAtakThroughFrontController(): void
    {
        $ht = (string) file_get_contents(dirname(__DIR__, 2) . '/.htaccess');
        self::assertStringContainsString('RewriteRule ^atak(/.*)?$ public/index.php [L,QSA]', $ht);
        self::assertStringContainsString('RewriteRule ^connect(/.*)?$ public/index.php [L,QSA]', $ht);
        self::assertStringNotContainsString('public/atak [L,QSA]', $ht);
        self::assertStringNotContainsString('public/connect [L,QSA]', $ht);
    }
}
