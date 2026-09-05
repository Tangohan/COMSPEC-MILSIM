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

    public function testRootHtaccessRedirectsShortAtakToPublicAtak(): void
    {
        $ht = (string) file_get_contents(dirname(__DIR__, 2) . '/.htaccess');
        self::assertStringContainsString('RewriteRule ^atak/?$ /public/atak [R=302,L,QSA]', $ht);
        self::assertStringContainsString('RewriteRule ^connect/?$ /public/connect [R=302,L,QSA]', $ht);
        self::assertStringNotContainsString('public/index.php [L,QSA]', $ht);
    }

    public function testNginxExampleForcesAtakThroughFrontController(): void
    {
        $ngx = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/nginx.example.conf');
        self::assertStringContainsString('try_files $uri /index.php?$query_string;', $ngx);
        self::assertStringNotContainsString('try_files $uri $uri/ /index.php?$query_string;', $ngx);
        self::assertStringContainsString('location = /atak', $ngx);
        self::assertStringContainsString('location = /atak/', $ngx);
    }

    public function testDeployRemovesStrayPublicAtakDirectory(): void
    {
        $yml = (string) file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/deploy-vps.yml');
        self::assertStringContainsString('rm -rf public/atak', $yml);
        self::assertStringContainsString('conflicts with /atak route', $yml);
    }
}
