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
        self::assertStringContainsString("[LegacyPersonnelRedirectController::class, 'member']", $routes);
    }

    public function testEffectifsOwnsAccountCreationAndDutyPositionActions(): void
    {
        $roster = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/roster.php');
        $member = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/member.php');

        self::assertStringContainsString("effectifs_workspace_url('nouveau')", $roster);
        self::assertStringContainsString("/position-service')", $member);
        self::assertStringNotContainsString("url('back-office/users/", $roster);
    }

    public function testPublicDirectoryConvergesToReadableDarkEffectifsRoster(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/PersonnelController.php');
        $roster = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/roster.php');
        $styles = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/effectifs_lms.css');

        self::assertStringContainsString("Response::redirect(\$target . (\$query !== '' ? '?' . http_build_query(['q' => \$query]) : ''))", $controller);
        self::assertStringContainsString('eff-catalog eff-catalog--dark', $roster);
        self::assertStringContainsString('Personnage ·', $roster);
        self::assertStringContainsString('<b>Matricule</b>', $roster);
        self::assertStringContainsString('<b>Radio</b>', $roster);
        self::assertStringContainsString('<b>Distinctions</b>', $roster);
        self::assertStringContainsString('.eff-catalog--dark', $styles);
    }
}
