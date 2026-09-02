<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EffectifsProfileEditingAssetTest extends TestCase
{
    public function testProfileEditorIsHostedByEffectifsWorkspace(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $controller = file_get_contents(base_path('app/Controllers/Web/PersonnelController.php'));
        $member = file_get_contents(base_path('views/admin/effectifs_workspace/member.php'));
        $roster = file_get_contents(base_path('views/admin/effectifs_workspace/roster.php'));
        $head = file_get_contents(base_path('views/admin/effectifs_workspace/partials/effectifs_lms_head.php'));

        self::assertIsString($routes);
        self::assertStringContainsString("effectifs/membres/{id}/modifier', [PersonnelController::class, 'editFromEffectifs']", $routes);
        self::assertIsString($controller);
        self::assertStringContainsString("Response::view(\$fromEffectifs ? 'layout.effectifs_lms' : 'layout.main'", $controller);
        self::assertStringContainsString("effectifs_workspace_url('membres/' . (int) \$target['id'] . '/modifier')", $controller);
        self::assertIsString($member);
        self::assertStringContainsString("effectifs_workspace_url('membres/' . \$id . '/modifier')", $member);
        self::assertIsString($roster);
        self::assertStringContainsString("\$personnelEditUrl = effectifs_workspace_url('membres/' . \$id . '/modifier')", $roster);
        self::assertIsString($head);
        self::assertStringContainsString("\$backOfficePageCss", $head);
        self::assertStringContainsString('alpine.min.js', $head);
    }
}
