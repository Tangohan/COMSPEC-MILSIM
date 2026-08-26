<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakTerrainCoverageStatusAssetTest extends TestCase
{
    public function testLookPrefsPanelListsCoverageStatusUnderTheExistingLine(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak.php');
        $statusPos = strpos($view, 'id="atak-terrain-status"');
        $inventoryPos = strpos($view, 'id="atak-terrain-inventory"');
        $closePos = strpos($view, 'data-tool-ui="look-close"');

        self::assertNotFalse($statusPos);
        self::assertNotFalse($inventoryPos);
        self::assertNotFalse($closePos);
        self::assertGreaterThan($statusPos, $inventoryPos);
        self::assertGreaterThan($inventoryPos, $closePos);

        self::assertStringContainsString('Données terrain — aucune couverture', $view);
        self::assertStringContainsString('Ombrage', $view);
        self::assertStringContainsString('Relevé divers (pente, courbe', $view);
        self::assertStringContainsString('Bâtiments', $view);
        self::assertStringContainsString('Forêts', $view);
        self::assertStringContainsString('Dernier relevé le', $view);
        self::assertStringContainsString('Pas encore sur le poste', $view);
        self::assertStringContainsString('Aucun relevé reçu pour l’instant', $view);

        $blockStart = strpos($view, 'id="atak-terrain-inventory"');
        $blockEnd = strpos($view, 'atak-map-tools__prefs-actions', $blockStart);
        self::assertNotFalse($blockStart);
        self::assertNotFalse($blockEnd);
        $block = strtolower(substr($view, $blockStart, $blockEnd - $blockStart));
        self::assertStringNotContainsString('json', $block);
        self::assertStringNotContainsString('endpoint', $block);
        self::assertStringNotContainsString('/api/', $block);
        self::assertStringNotContainsString('sql', $block);
        self::assertStringNotContainsString('sqf', $block);
    }

    public function testTerrainScriptReadsCoverageFieldsIntoHumanStatus(): void
    {
        $javascript = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-terrain.js');

        self::assertStringContainsString('/api/atak/theater/coverage', $javascript);
        self::assertStringContainsString('terrain_coverage_pct', $javascript);
        self::assertStringContainsString('cov.buildings', $javascript);
        self::assertStringContainsString('cov.forests', $javascript);
        self::assertStringContainsString('cov.last_survey_at', $javascript);
        self::assertStringContainsString('function renderInventory', $javascript);
        self::assertStringContainsString('Présent', $javascript);
        self::assertStringContainsString('Pas encore sur le poste', $javascript);
        self::assertStringContainsString('Aucun relevé reçu pour l’instant', $javascript);
        self::assertStringContainsString('atak-terrain-inv-hillshade', $javascript);
        self::assertStringContainsString('lookPanelOpen()', $javascript);
        self::assertStringNotContainsString('JSON.stringify', $javascript);
        self::assertStringNotContainsString("textContent = JSON", $javascript);
        self::assertStringNotContainsString('coller du JSON', $javascript);
    }

    public function testCoverageApiExposesLastSurveyWithoutANewSettingsCatalog(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/AtakSceneApiController.php');
        $terrain = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/AtakTerrainRepository.php');
        $scene = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/AtakSceneObjectRepository.php');

        self::assertStringContainsString("'last_survey_at' => \$lastSurvey", $controller);
        self::assertStringContainsString('function lastUpdatedAt', $scene);
        self::assertStringContainsString("'sampled_at' => self::laterStamp(\$gridLast, \$chunkLast)", $terrain);
        self::assertStringContainsString('MAX(`received_at`)', $terrain);
        self::assertStringContainsString('MAX(`updated_at`)', $scene);
    }
}
