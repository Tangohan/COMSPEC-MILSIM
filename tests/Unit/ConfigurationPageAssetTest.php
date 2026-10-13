<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Page back-office « Configuration » : sommaire à sections, recherche, formulaire avec états
 * et journal des changements (audit_logs, actions configuration.*).
 */
final class ConfigurationPageAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testViewKeepsFormFieldsAndLoadsAssets(): void
    {
        $root = $this->root();
        $view = (string) file_get_contents($root . '/views/admin/configuration.php');
        $pages = (string) file_get_contents($root . '/config/back_office_pages.php');

        self::assertFileExists($root . '/public/assets/css/back-office-configuration.css');
        self::assertFileExists($root . '/public/assets/js/back-office-configuration.js');
        self::assertStringContainsString("'back-office-configuration.css'", $pages);
        self::assertStringContainsString('back-office-configuration.js', $view);

        // Mêmes champs et mêmes routes qu’avant la refonte (aucun réglage perdu).
        self::assertStringContainsString("url('back-office/configuration/member-role-display')", $view);
        self::assertStringContainsString('name="member_can_choose_display_role" value="0"', $view);
        self::assertStringContainsString('name="member_can_choose_display_role" value="1"', $view);
        self::assertStringContainsString('name="display_badges_mode" value="primary_only"', $view);
        self::assertStringContainsString('name="display_badges_mode" value="multi"', $view);
        self::assertStringContainsString('name="organization_role_labels" value="fr"', $view);
        self::assertStringContainsString('name="organization_role_labels" value="en"', $view);
        self::assertStringContainsString("url('back-office/configuration/debug-recruit-sync')", $view);
        self::assertStringContainsString('if ($appDebug)', $view);

        self::assertStringContainsString('data-cfg-search-input', $view);
        self::assertStringContainsString('data-cfg-savebar', $view);
        self::assertStringContainsString('id="cfg-historique"', $view);
    }

    public function testControllerLogsRoleDisplayChangesForHistory(): void
    {
        $controller = (string) file_get_contents($this->root() . '/app/Controllers/Admin/AdminConfigurationController.php');

        self::assertStringContainsString("AUDIT_ACTION_PREFIX = 'configuration.'", $controller);
        self::assertStringContainsString("self::AUDIT_ACTION_PREFIX . 'member_role_display'", $controller);
        self::assertStringContainsString('AuditFieldSnapshot::diffOnly', $controller);
        self::assertStringContainsString("'configHistory' => \$configHistory", $controller);
    }
}
