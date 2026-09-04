<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EffectifsMemberCompleteFileAssetTest extends TestCase
{
    public function testEffectifsMemberPageCentralizesTheRhFile(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/admin/effectifs_workspace/member.php');
        $nav = (string) file_get_contents($root . '/views/partials/member_hub_nav.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Admin/EffectifsWorkspaceController.php');

        foreach (['Dossier RH complet', 'Qualifications', 'Absences', 'Documents RH', 'Mobilité et souhaits', 'Historique RH et bilans'] as $section) {
            self::assertStringContainsString($section, $view);
        }
        self::assertStringContainsString('point d’entrée RH unique', $controller);
        self::assertStringContainsString('Point d’entrée unique', $nav);
        self::assertStringNotContainsString("url('personnel/' . \$memberHubUserId . '/edit')", $nav);
        self::assertStringNotContainsString("url('back-office/users/' . \$memberHubUserId . '/edit')", $nav);
    }
}
