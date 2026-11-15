<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakTerrainCoverageStatusAssetTest extends TestCase
{
    public function testTerrainScriptReadsCoverageFieldsIntoHumanStatus(): void
    {
        $javascript = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-terrain.js');

        self::assertStringContainsString('/api/atak/theater/coverage', $javascript);
        self::assertStringContainsString('terrain_coverage_pct', $javascript);
        self::assertStringContainsString('cov.buildings', $javascript);
        self::assertStringContainsString('cov.forests', $javascript);
        self::assertStringContainsString('cov.last_survey_at', $javascript);
        self::assertStringContainsString('function renderInventory', $javascript);
        self::assertStringContainsString('function knownCount', $javascript);
        self::assertStringContainsString('Présent', $javascript);
        self::assertStringContainsString('Pas encore sur le poste', $javascript);
        self::assertStringContainsString('Compte indisponible, réessayez', $javascript);
        self::assertStringContainsString('Le décompte n’est pas encore disponible', $javascript);
        self::assertStringContainsString('Aucun relevé reçu pour l’instant', $javascript);
        self::assertStringContainsString('atak-terrain-inv-hillshade', $javascript);
        self::assertStringContainsString('lookPanelOpen()', $javascript);
        self::assertStringContainsString('status === 404', $javascript);
        self::assertStringContainsString('status === 503', $javascript);
        self::assertStringContainsString("countStatus === 'missing'", $javascript);
        self::assertStringContainsString("countStatus === 'retry'", $javascript);
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
        self::assertStringContainsString("\$payload['buildings']", $controller);
        self::assertStringContainsString("\$payload['forests']", $controller);
        self::assertStringContainsString('$counts = null;', $controller);
        self::assertStringNotContainsString("\$counts = ['building' => 0, 'forest' => 0]", $controller);
        self::assertStringContainsString("kind IN ('building', 'buildings')", $scene);
        self::assertStringContainsString("kind IN ('forest', 'forests')", $scene);
        self::assertStringContainsString('isMissingTable', $scene);
        self::assertStringContainsString('1146', $scene);
    }

    public function testStatusDoesNotClaimUnsurveyedWhenCoverageExists(): void
    {
        $javascript = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-terrain.js');
        $api = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/AtakTerrainApiController.php');

        self::assertStringContainsString('function coverageLabelFromInventory', $javascript);
        self::assertStringContainsString('function setStatusFromSurvey', $javascript);
        self::assertStringContainsString('function hasVolumeSurvey', $javascript);
        self::assertStringContainsString('setStatusFromSurvey()', $javascript);
        self::assertStringContainsString('Bâtiments et forêts reçus. L’ombrage du sol n’est pas encore sur le poste.', $javascript);
        self::assertStringContainsString('terrain_chunks', $javascript);
        self::assertStringContainsString('Number(j.coverage_pct) > 0 || Number(j.filled_cells) > 0', $javascript);
        self::assertStringContainsString("\$filled > 0 && is_string(\$grid['heights'] ?? null)", $api);
        self::assertStringNotContainsString("include') === 'heights' && \$ready && is_string", $api);
    }
}
