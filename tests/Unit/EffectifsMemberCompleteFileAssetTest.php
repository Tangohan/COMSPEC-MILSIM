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
        $controller = (string) file_get_contents($root . '/app/Controllers/Admin/EffectifsWorkspaceController.php');

        foreach (['Dossier RH complet', 'Qualifications', 'Absences', 'Documents RH', 'Mobilité et souhaits', 'Chronologie du dossier'] as $section) {
            self::assertStringContainsString($section, $view);
        }
        self::assertStringContainsString('bo-eff-jump', $view);
    }
}
