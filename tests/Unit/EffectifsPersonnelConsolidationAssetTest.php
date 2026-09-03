<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EffectifsPersonnelConsolidationAssetTest extends TestCase
{
    public function testLegacyPersonnelPagesRedirectToEffectifs(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');

        self::assertStringContainsString("[LegacyPersonnelRedirectController::class, 'roster']", $routes);
        self::assertStringContainsString("[LegacyPersonnelRedirectController::class, 'member']", $routes);
        self::assertStringContainsString("[LegacyPersonnelRedirectController::class, 'edit']", $routes);
        self::assertStringContainsString("'/back-office/ressources/effectifs/nouveau'", $routes);
        self::assertStringContainsString("'/back-office/ressources/effectifs/membres/{id}/modifier'", $routes);
    }

    public function testEffectifsOwnsAccountCreationAndDutyPositionActions(): void
    {
        $roster = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/roster.php');
        $member = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/member.php');

        self::assertStringContainsString("effectifs_workspace_url('nouveau')", $roster);
        self::assertStringContainsString("/position-service')", $member);
        self::assertStringNotContainsString("url('back-office/users/", $roster);
    }
}
