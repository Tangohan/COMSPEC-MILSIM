<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdvancedEditRbacGateFixTest extends TestCase
{
    public function testControllersUseGateInsteadOfMissingRbacMethod(): void
    {
        $advanced = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/AdvancedFicheEditGrantController.php');
        $correction = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/PersonnelCorrectionController.php');
        $container = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Core/Container.php');

        self::assertStringNotContainsString('userHasPermission', $advanced);
        self::assertStringNotContainsString('userHasPermission', $correction);
        self::assertStringContainsString('Gate::getInstance()', $advanced);
        self::assertStringContainsString('Gate::getInstance()', $correction);
        self::assertStringContainsString('$gate->allows($slug)', $advanced);
        self::assertStringContainsString('$gate->allows($slug)', $correction);

        // Container ne doit plus injecter RbacService dans ces deux contrôleurs.
        self::assertMatchesRegularExpression(
            '/AdvancedFicheEditGrantController::class => new[^\n]+\n(?:[^\n]+\n){0,6}\s*\),/m',
            $container
        );
        self::assertStringNotContainsString(
            "UserAdvancedEditGrantRepository::class),\n                self::get(RbacService::class),",
            $container
        );
    }
}
