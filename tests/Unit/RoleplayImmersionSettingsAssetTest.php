<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RoleplayImmersionSettingsAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testImmersionPageExplainsPurposeAndKeepsExistingFields(): void
    {
        $root = $this->root();
        $view = (string) file_get_contents($root . '/views/admin/organization/roleplay_immersion_settings.php');
        $css = (string) file_get_contents($root . '/public/assets/css/back-office-roleplay-immersion.css');
        $js = (string) file_get_contents($root . '/public/assets/js/roleplay-immersion-settings.js');
        $pages = (string) file_get_contents($root . '/config/back_office_pages.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/RoleplayFollowupAdminController.php');

        self::assertFileExists($root . '/public/assets/css/back-office-roleplay-immersion.css');
        self::assertFileExists($root . '/public/assets/js/roleplay-immersion-settings.js');

        self::assertStringContainsString('Suivre l’arrivée d’un membre, pas le jeu', $view);
        self::assertStringContainsString('Après enregistrement, vous verrez', $view);
        self::assertStringContainsString('Parcours d’immersion', $view);
        self::assertStringContainsString('Bureau de suivi', $view);
        self::assertStringContainsString('Échéances', $view);
        self::assertStringContainsString('ATAK et Overwatch', $view);
        self::assertStringContainsString('admin/atak/roleplay', $view);
        self::assertStringContainsString('Le suivi n’apparaît pas encore.', $view);
        self::assertStringContainsString('name="rp_followup_enabled"', $view);
        self::assertStringContainsString('name="rp_followup_optional"', $view);
        self::assertStringContainsString('name="rp_followup_stages"', $view);
        self::assertStringContainsString('name="rp_followup_tracks"', $view);
        self::assertStringContainsString('name="rp_eligibility_min_completeness"', $view);
        self::assertStringContainsString('name="rp_eligibility_min_readiness"', $view);
        self::assertStringContainsString('name="rp_eligibility_require_unit"', $view);
        self::assertStringContainsString('name="rp_eligibility_require_callsign"', $view);
        self::assertStringContainsString('name="rp_eligibility_require_tutor"', $view);
        self::assertStringContainsString('data-imm-list', $view);
        self::assertStringContainsString('roleplay-immersion-settings.js', $view);
        self::assertStringNotContainsString('endpoint', strtolower($view));
        self::assertStringNotContainsString('json', strtolower($view));
        self::assertStringNotContainsString('slug', strtolower($view));

        self::assertStringContainsString('back-office-roleplay-immersion.css', $pages);
        self::assertStringContainsString('Parcours d’immersion', $pages);
        self::assertStringContainsString("'title' => 'Parcours d’immersion'", $controller);
        self::assertStringContainsString("'rp_followup_enabled'", $controller);
        self::assertStringContainsString("'rp_followup_stages'", $controller);

        self::assertStringContainsString('bo-imm__hero', $css);
        self::assertStringContainsString('bo-imm__cap', $css);
        self::assertStringContainsString('data-imm-list', $js);
        self::assertStringContainsString('Ajouter une étape', $view);
        self::assertStringContainsString('Ajouter une filière', $view);
    }

    public function testSidebarAndSearchUseTheHumanTitle(): void
    {
        $root = $this->root();
        $nav = (string) file_get_contents($root . '/views/partials/ath_sidebar_nav.php');
        $configNav = (string) file_get_contents($root . '/config/navigation.php');
        $search = (string) file_get_contents($root . '/app/Services/Portal/BackOfficeSearchService.php');

        self::assertStringContainsString('Parcours d’immersion', $nav);
        self::assertStringContainsString('Parcours d’immersion', $configNav);
        self::assertStringContainsString('Parcours d’immersion', $search);
        self::assertStringNotContainsString("['label' => 'Réglages d’immersion', 'href' => url('back-office/roleplay/immersion')", $nav);
    }
}
