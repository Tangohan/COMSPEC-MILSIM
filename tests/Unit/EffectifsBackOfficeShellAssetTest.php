<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EffectifsBackOfficeShellAssetTest extends TestCase
{
    public function testEveryEffectifsWorkspacePageUsesTheBackOfficeShell(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string) file_get_contents($root . '/app/Controllers/Admin/EffectifsWorkspaceController.php');
        $rhController = (string) file_get_contents($root . '/app/Controllers/Admin/RhDossierWorkspaceController.php');
        $shell = (string) file_get_contents($root . '/views/admin/effectifs_workspace/shell.php');
        $css = (string) file_get_contents($root . '/public/assets/css/back-office-effectifs-workspace.css');

        self::assertStringContainsString("Response::view('layout.main'", $controller);
        self::assertStringContainsString("'effectifsContent' => \$content", $controller);
        self::assertStringContainsString("Response::view('layout.main'", $rhController);
        self::assertStringContainsString("'effectifsContent' => \$content", $rhController);

        self::assertStringContainsString('Pilotage quotidien', $shell);
        self::assertStringContainsString('Organisation & accès', $shell);
        self::assertStringContainsString('Parcours RH', $shell);
        self::assertStringContainsString('bo-eff-hero__badges', $shell);
        self::assertStringContainsString('data-eff-modal-open', $shell);
        self::assertStringContainsString('<dialog class="bo-eff-modal"', $shell);
        self::assertStringContainsString('showModal()', $shell);

        self::assertStringContainsString('.bo-eff-workspace', $css);
        self::assertStringContainsString('.bo-eff-subnav', $css);
        self::assertStringContainsString('.bo-eff-modal::backdrop', $css);
        self::assertStringContainsString('@media(max-width:640px)', $css);
    }
}
