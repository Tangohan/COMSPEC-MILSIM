<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EffectifsRolesUiAssetTest extends TestCase
{
    public function testRolesPagePresentsTheThreeAccessLevels(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/admin/effectifs_workspace/roles.php');
        $member = (string) file_get_contents($root . '/views/admin/effectifs_workspace/member.php');

        self::assertStringContainsString('Trois niveaux d’accès', $view);
        self::assertStringContainsString('Membre, Ressources humaines ou Gestionnaire', $view);
        self::assertStringNotContainsString('endpoint', $view);
        self::assertStringNotContainsString('JSON', $view);
        self::assertStringNotContainsString('slug', strtolower($view));

        self::assertStringContainsString('name="access_key"', $member);
        self::assertStringContainsString('type="radio"', $member);
        self::assertStringNotContainsString('name="role_ids[]"', $member);
    }

    public function testFonctionsPageExplainsJobsComeFromOrbat(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/admin/effectifs_workspace/fonctions.php');

        self::assertStringContainsString('organigramme', $view);
        self::assertStringContainsString('ORBAT', $view);
        self::assertStringContainsString('Ce ne sont pas des droits d’accès', $view);
        self::assertStringNotContainsString('slug', strtolower($view));
        self::assertStringNotContainsString('JSON', $view);
    }
}
