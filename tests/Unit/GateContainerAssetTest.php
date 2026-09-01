<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Container;
use App\Core\Gate;
use PHPUnit\Framework\TestCase;

final class GateContainerAssetTest extends TestCase
{
    public function testContainerResolvesTheLiveGateSingleton(): void
    {
        $fromContainer = Container::get(Gate::class);
        self::assertInstanceOf(Gate::class, $fromContainer);
        self::assertSame(Gate::getInstance(), $fromContainer);
    }

    public function testOrganizationControllersDoNotAskTheContainerForGate(): void
    {
        $root = dirname(__DIR__, 2);
        $files = [
            $root . '/app/Controllers/Admin/Organization/OrganizationMemberNumberController.php',
            $root . '/app/Controllers/Admin/Organization/OrganizationProgressionHubController.php',
            $root . '/app/Controllers/Admin/Organization/OrganizationCallsignSequencesController.php',
            $root . '/app/Controllers/Admin/Organization/OrganizationCatalogController.php',
        ];
        foreach ($files as $file) {
            $src = (string) file_get_contents($file);
            self::assertStringContainsString('Gate::getInstance()', $src, $file);
            self::assertStringNotContainsString('Container::get(Gate::class)', $src, $file);
        }

        $integrations = (string) file_get_contents($root . '/app/Core/ContainerIntegrations.php');
        self::assertStringContainsString('Gate::class => Gate::getInstance()', $integrations);
        self::assertStringContainsString('OrganizationProgressionHubController::class', $integrations);
        self::assertStringContainsString('OrganizationMemberNumberController::class', $integrations);
        self::assertStringContainsString('OrganizationCallsignSequencesController::class', $integrations);
        self::assertStringContainsString('OrganizationCatalogController::class', $integrations);
    }
}
