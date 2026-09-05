<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EffectifsMemberFunctionsSaveTest extends TestCase
{
    public function testOnlyPivotFailureIsReportedAsAFunctionSaveFailure(): void
    {
        $controller = (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Controllers/Admin/EffectifsWorkspaceController.php'
        );

        self::assertStringNotContainsString('Enregistrement impossible (fonctions).', $controller);
        self::assertStringContainsString('Effectifs functions: pivot save failed:', $controller);
        self::assertStringContainsString('Effectifs functions: assignment synchronization failed:', $controller);
        self::assertStringContainsString('Effectifs functions: post-save notification failed:', $controller);
        self::assertStringContainsString("Session::flash('success', 'Fonctions du membre mises à jour.')", $controller);
    }

    public function testMemberFileExplainsDifferenceBetweenFunctionAndAccessRole(): void
    {
        $view = (string) file_get_contents(
            dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/member.php'
        );

        self::assertStringContainsString('Fonctions opérationnelles', $view);
        self::assertStringContainsString('Elle n’accorde aucun droit d’accès au site', $view);
        self::assertStringContainsString('Rôles d’accès', $view);
        self::assertStringContainsString('Un rôle accorde des habilitations dans Athena', $view);
    }
}
