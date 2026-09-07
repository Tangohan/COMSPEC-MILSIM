<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RhWorkspaceClarityAssetTest extends TestCase
{
    public function testWorkspaceIsMesDemarchesWithoutPortalShortcuts(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/personnel/rh_workspace.php');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Web/RhWorkspaceController.php');
        $nav = (string) file_get_contents($root . '/config/navigation.php');
        $hub = (string) file_get_contents($root . '/app/Controllers/Web/HubController.php');
        $aside = (string) file_get_contents($root . '/views/partials/dashboard_aside.php');
        $parcours = (string) file_get_contents($root . '/views/partials/dashboard_rh_parcours.php');

        self::assertStringContainsString("'title' => 'Mes démarches'", $ctrl);
        self::assertStringContainsString('Mes démarches', $view);
        self::assertStringContainsString('id="elevation"', $view);
        self::assertStringContainsString('elevation_request_fields.php', $view);
        self::assertStringContainsString('id="absences"', $view);
        self::assertStringContainsString('id="mobilite"', $view);

        self::assertStringNotContainsString('Espace RH et formations', $view);
        self::assertStringNotContainsString('Accès rapides', $view);
        self::assertStringNotContainsString('Raccourcis du portail', $view);
        self::assertStringNotContainsString('Formations et engagements', $view);

        self::assertStringContainsString("'label' => 'Mes démarches', 'path' => 'personnel/mon-espace-rh'", $nav);
        self::assertStringNotContainsString("'label' => 'Espace RH et formations'", $nav);
        $formationBlockStart = strpos($nav, "'label' => 'Formation'");
        self::assertNotFalse($formationBlockStart);
        $formationSlice = substr($nav, $formationBlockStart, 2500);
        self::assertStringNotContainsString('personnel/mon-espace-rh', $formationSlice);

        self::assertStringContainsString("'label' => 'Mes démarches'", $hub);
        self::assertStringContainsString("'label' => 'Mes démarches'", $aside);
        self::assertStringContainsString("'label' => 'Démarche rapide'", $aside);
        self::assertStringContainsString('Toutes les démarches', $parcours);
        self::assertStringNotContainsString('Espace RH complet', $parcours);
        self::assertStringNotContainsString('Mon dossier RH', $parcours);
    }
}
