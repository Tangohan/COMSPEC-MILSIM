<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EffectifsProfileEditingAssetTest extends TestCase
{
    public function testProfileManagementConvergesOnTheMemberFile(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $controller = file_get_contents(base_path('app/Controllers/Web/PersonnelController.php'));
        $member = file_get_contents(base_path('views/admin/effectifs_workspace/member.php'));
        $roster = file_get_contents(base_path('views/admin/effectifs_workspace/roster.php'));
        $edit = file_get_contents(base_path('views/personnel/edit.php'));
        $head = file_get_contents(base_path('views/admin/effectifs_workspace/partials/effectifs_lms_head.php'));

        self::assertIsString($routes);
        self::assertStringContainsString("effectifs/membres/{id}/modifier', [EffectifsWorkspaceController::class, 'member']", $routes);
        self::assertIsString($controller);
        self::assertStringContainsString("\$fromEffectifs ? 'layout.effectifs_lms' : 'layout.main'", $controller);
        self::assertStringContainsString("\$embeddedInEffectifs ? 'personnel.edit'", $controller);
        self::assertStringContainsString('public function embeddedEditor', $controller);
        self::assertStringContainsString("effectifs_workspace_url('membres/' . (int) \$target['id'])", $controller);
        self::assertStringContainsString("\$request->input('effectifs_context', '') === '1'", $controller);
        self::assertIsString($member);
        self::assertStringContainsString('id="modifier-dossier"', $member);
        self::assertStringContainsString('<?= \$memberEditorHtml ?>', $member);
        self::assertIsString($roster);
        self::assertStringContainsString("\$personnelEditUrl = effectifs_workspace_url('membres/' . \$id) . '#modifier-dossier';", $roster);
        self::assertIsString($edit);
        self::assertStringContainsString('name="effectifs_context" value="1"', $edit);
        self::assertIsString($head);
        self::assertStringContainsString("\$backOfficePageCss", $head);
        self::assertStringContainsString('alpine.min.js', $head);
    }
}
