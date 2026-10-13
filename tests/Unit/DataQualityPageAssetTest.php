<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Page back-office « Qualité des données » : mêmes routes et champs qu’avant la refonte,
 * styles et script chargés, noms des membres résolus par le contrôleur.
 */
final class DataQualityPageAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testViewKeepsFormsAndLoadsAssets(): void
    {
        $root = $this->root();
        $view = (string) file_get_contents($root . '/views/admin/organization/data_quality.php');
        $pages = (string) file_get_contents($root . '/config/back_office_pages.php');

        self::assertFileExists($root . '/public/assets/css/back-office-data-quality.css');
        self::assertFileExists($root . '/public/assets/js/back-office-data-quality.js');
        self::assertStringContainsString("'back-office-data-quality.css'", $pages);
        self::assertStringContainsString('back-office-data-quality.js', $view);

        self::assertStringContainsString("url('back-office/organisation/qualite-donnees/snapshot')", $view);
        self::assertStringContainsString("'/restaurer'", $view);
        foreach (['name="label"', 'name="kind"', 'name="effective_at"', 'name="notes"', 'name="_csrf_token"'] as $field) {
            self::assertStringContainsString($field, $view);
        }
        foreach (['manual', 'pre_reorg', 'scheduled'] as $kind) {
            self::assertStringContainsString("'" . $kind . "'", $view);
        }
        self::assertStringContainsString('data-dq-list', $view);
        self::assertStringContainsString('id="dq-versions"', $view);
    }

    public function testControllerPassesMemberNames(): void
    {
        $controller = (string) file_get_contents($this->root() . '/app/Controllers/Admin/Organization/OrganizationDataQualityController.php');

        self::assertStringContainsString("'peopleNames' => \$this->peopleNames(", $controller);
        self::assertStringNotContainsString("'email'", $controller);
    }
}
