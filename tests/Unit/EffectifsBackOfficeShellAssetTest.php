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
        $roster = (string) file_get_contents($root . '/views/admin/effectifs_workspace/roster.php');

        self::assertStringContainsString("Response::view('layout.main'", $controller);
        self::assertStringContainsString("'effectifsContent' => \$content", $controller);
        self::assertStringContainsString("Response::view('layout.main'", $rhController);
        self::assertStringContainsString("'effectifsContent' => \$content", $rhController);

        self::assertStringContainsString('bo-eff-head', $shell);
        self::assertStringContainsString('bo-eff-tabs', $shell);
        self::assertStringContainsString('Roleplay', $shell);
        self::assertStringContainsString('Intégration', $shell);
        self::assertStringContainsString('Réglages', $shell);
        self::assertStringContainsString('Tableur', $shell);
        self::assertStringContainsString('Accès', $shell);
        self::assertStringContainsString('Emplois', $shell);
        self::assertStringContainsString('Ajouter un membre', $shell);
        self::assertStringNotContainsString('centre de conduite', $shell);
        self::assertStringNotContainsString('Pilotage quotidien', $shell);
        self::assertStringNotContainsString('Actions rapides', $shell);
        self::assertStringNotContainsString('bo-eff-hero', $shell);
        self::assertStringNotContainsString('<dialog', $shell);

        self::assertStringNotContainsString('eff-catalog__title', $roster);
        self::assertStringNotContainsString('Ressources humaines', $roster);

        self::assertStringContainsString('.bo-eff-workspace .eff-rh-form', $css);
        self::assertStringContainsString('.bo-eff-workspace', $css);
        self::assertStringContainsString('.bo-eff-tabs', $css);
        self::assertStringContainsString('.bo-eff-head', $css);
        self::assertStringContainsString('@media (max-width: 720px)', $css);
    }
}
