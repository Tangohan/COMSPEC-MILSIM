<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakModReportWiringAssetTest extends TestCase
{
    public function testContainerDoesNotConstructReconImageRepositoryForAtakApiController(): void
    {
        $container = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Core/Container.php');
        $start = strpos($container, '\\App\\Controllers\\Api\\AtakApiController::class =>');
        self::assertNotFalse($start);
        $block = substr($container, $start, 1800);

        self::assertStringNotContainsString(
            'reconRepo: self::get(\\App\\Repositories\\ReconImageRepository::class)',
            $block
        );
        self::assertStringContainsString('reconRepo lazy dans AtakApiController', $block);
        self::assertStringContainsString('modReportRepository lazy dans AtakApiController', $block);
    }

    public function testReconImageRepositoryDoesNotConnectInConstructor(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/ReconImageRepository.php');
        self::assertDoesNotMatchRegularExpression(
            '/public function __construct\(\)\s*\{[^}]*getPdo\s*\(/s',
            $src
        );
        self::assertStringContainsString('onDatabaseConnected', $src);
        self::assertStringContainsString('function reconImages()', (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Controllers/Api/AtakApiController.php'
        ));
    }
}
