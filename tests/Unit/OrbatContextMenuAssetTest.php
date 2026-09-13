<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OrbatContextMenuAssetTest extends TestCase
{
    public function testStructureHubExposesFullRightClickEditing(): void
    {
        $root = dirname(__DIR__, 2);
        $canvas = (string) file_get_contents($root . '/views/partials/orbat/orbat_canvas.php');
        $hub = (string) file_get_contents($root . '/views/admin/organization/structure_hub.php');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/OrganizationDashboardController.php');

        self::assertStringContainsString('id="orbat-ctx-menu"', $canvas);
        self::assertStringContainsString('data-ctx="rename"', $canvas);
        self::assertStringContainsString('data-ctx="struct-type"', $canvas);
        self::assertStringContainsString('data-ctx="commander"', $canvas);
        self::assertStringContainsString('data-ctx="hub-invite"', $canvas);
        self::assertStringContainsString('function openOrbatContextMenu', $canvas);
        self::assertStringContainsString('bindOrbatCardContext', $canvas);
        self::assertStringContainsString('postUnitPatch', $canvas);
        self::assertStringContainsString('document.body.appendChild(m)', $canvas);
        self::assertStringContainsString('Clic droit (ou bouton ⋯)', $hub);
        self::assertStringContainsString('site.support', $ctrl);
        self::assertStringContainsString('allUnitsForParent', $ctrl);
    }
}
