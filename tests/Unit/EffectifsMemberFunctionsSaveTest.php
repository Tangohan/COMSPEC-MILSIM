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
        self::assertStringContainsString('Effectifs functions: post-save notification failed:', $controller);
        self::assertStringContainsString("Session::flash('success', 'Fonctions du membre mises à jour.')", $controller);
    }

    public function testMemberFileExplainsDifferenceBetweenFunctionAndAccessRole(): void
    {
        $view = (string) file_get_contents(
            dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/member.php'
        );

        // Member file redesign (#855): "Fonctions" and "Niveau d’accès" cards, still explaining
        // that a function is a post held, not an access right, and that access is a single level.
        self::assertStringContainsString('id="fonctions"', $view);
        self::assertStringContainsString('La fonction décrit le poste tenu', $view);
        self::assertStringContainsString('Ce n’est ni l’équipe ni un droit d’accès.', $view);
        self::assertStringContainsString('Niveau d’accès', $view);
        self::assertStringContainsString('Un seul niveau par personne.', $view);
        self::assertStringContainsString('Corriger ce qu’un niveau a le droit de faire', $view);
    }
}
