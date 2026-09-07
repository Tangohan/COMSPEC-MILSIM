<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CommunitySettingsHubAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testHubGroupsSettingsAndKeepsAccueilBeforeMainForm(): void
    {
        $root = $this->root();
        $settings = (string) file_get_contents($root . '/views/admin/organization/settings.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/OrganizationSettingsController.php');
        $js = (string) file_get_contents($root . '/public/assets/js/community-settings-hub.js');
        $css = (string) file_get_contents($root . '/public/assets/css/back-office-shell.css');
        $inscription = (string) file_get_contents($root . '/views/admin/organization/inscription_settings.php');

        self::assertFileExists($root . '/public/assets/js/community-settings-hub.js');
        self::assertStringContainsString('data-settings-hub', $settings);
        self::assertStringContainsString('data-settings-tab="inscription"', $settings);
        self::assertStringContainsString('data-settings-tab="accueil"', $settings);
        self::assertStringContainsString('name="settings_tab"', $settings);
        self::assertStringContainsString('bo-settings-hub-save', $settings);
        self::assertStringContainsString('community-settings-hub.js', $settings);
        self::assertStringContainsString('inscription_settings.php', $settings);
        self::assertStringNotContainsString('Nouveaux réglages', $settings);
        self::assertStringNotContainsString('Indexation moteurs', $settings);

        $accueil = strpos($settings, 'id="accueil-connexion"');
        $form = strpos($settings, 'id="bo-community-settings-form"');
        self::assertNotFalse($accueil);
        self::assertNotFalse($form);
        self::assertLessThan($form, $accueil);

        self::assertStringContainsString('settingsHubUrl', $controller);
        self::assertStringContainsString("return Response::redirect(\$this->settingsHubUrl(\$request, 'inscription'));", $controller);
        self::assertStringContainsString("settingsHubUrl(\$request, 'accueil')", $controller);
        self::assertStringContainsString('#accueil-connexion', $controller);

        self::assertStringContainsString('bo-community-settings-tab', $js);
        self::assertStringContainsString('localStorage', $js);
        self::assertStringContainsString('bo-settings-hub-save', $css);
        self::assertStringContainsString('$settingsHubEmbed', $inscription);
        self::assertStringContainsString('id="bo-inscription-settings-form"', $inscription);
    }

    public function testSidebarMergesInscriptionIntoCommunitySettings(): void
    {
        $root = $this->root();
        $nav = (string) file_get_contents($root . '/views/partials/ath_sidebar_nav.php');
        $pages = (string) file_get_contents($root . '/config/back_office_pages.php');
        $configNav = (string) file_get_contents($root . '/config/navigation.php');

        self::assertStringContainsString("['label' => 'Paramètres', 'href' => url('back-office/community')", $nav);
        self::assertStringNotContainsString('Paramètres d’inscription', $nav);
        self::assertStringContainsString('?onglet=inscription', $pages);
        self::assertStringNotContainsString("'path' => 'back-office/community/inscription'", $configNav);
        self::assertStringContainsString('Mise à niveau', $configNav);
    }
}
