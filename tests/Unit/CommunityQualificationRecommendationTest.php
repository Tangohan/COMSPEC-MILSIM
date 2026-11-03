<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\CommunityQualificationRecommender as Reco;
use PHPUnit\Framework\TestCase;

/**
 * « Recommandé pour votre communauté » dans l’assistant d’importation du catalogue : nature des unités de l’ORBAT et
 * unités réelles, emplois reconnus dans les fonctions, prérequis des fiches déjà importées, confidentialité, câblage.
 */
final class CommunityQualificationRecommendationTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** @param array<string, mixed> $in @return array<string, mixed> */
    private static function reco(array $in): array
    {
        return Reco::recommend($in + ['lang' => 'fr']);
    }

    /** @param array<string, mixed> $r @return array<string, array<string, mixed>> éléments d’un groupe par identifiant */
    private static function group(array $r, string $key): array
    {
        foreach ($r['groups'] as $g) {
            if ($g['key'] === $key) {
                return array_column($g['items'], null, 'id');
            }
        }

        return [];
    }

    /** @return array<string, mixed> communauté : 24th STS et une section dessous, fonctions TACP (6 membres) */
    private static function sts(bool $confidential = false, bool $staff = false): array
    {
        return [
            'staff' => $staff,
            'units' => [
                ['id' => 1, 'name' => '24th STS', 'slug' => 'escadron', 'members' => 2, 'confidential' => $confidential],
                ['id' => 2, 'name' => 'Section Alpha', 'parent_id' => 1, 'members' => 8, 'confidential' => $confidential],
            ],
            'roles' => [['role_id' => 1, 'name' => 'SOF TACP', 'members' => 6], ['role_id' => 2, 'name' => 'Secrétaire', 'members' => 0]],
            'imported' => ['US-JTAC' => true],
            'imported_definitions' => ['US-JTAC' => 10],
        ];
    }

    public function testSpecialTacticsSquadronAndTacpFunctionsRecommendJtacAndAirborne(): void
    {
        $r = self::reco(self::sts());
        $d = $r['detected'];
        self::assertSame('US', $d['country']);
        self::assertSame('real_units', $d['country_source'], 'pays de l’unité réelle reconnue (24th STS)');
        self::assertSame('special_tactics', $d['natures'][0]['key']);
        self::assertSame(10, $d['natures'][0]['members'], 'effectif de l’escadron et de la section dessous');
        self::assertSame('24th STS', $d['natures'][0]['sources'][0]['name']);
        self::assertSame(['SOF TACP', 'TACP', 6], [$d['jobs'][0]['role'], $d['jobs'][0]['job'], $d['jobs'][0]['members']]);

        $jobs = self::group($r, 'jobs');
        foreach (['us-jtac', 'us-af-tacp-icc', 'us-af-sere'] as $id) {
            self::assertArrayHasKey($id, $jobs, $id . ' pour l’emploi TACP');
            self::assertSame(6, $jobs[$id]['members']);
        }
        self::assertTrue($jobs['us-jtac']['done'], 'déjà dans le référentiel : marquée');
        self::assertFalse($jobs['us-af-tacp-icc']['done']);
        $units = self::group($r, 'units');
        foreach (['us-basic-airborne', 'us-mffpc'] as $id) {
            self::assertArrayHasKey($id, $units, $id . ' courant dans les unités Special Tactics');
        }
        foreach ($r['groups'] as $g) {
            foreach ($g['items'] as $it) {
                self::assertContains($it['country'], ['US', 'NATO'], $it['id'] . ' : catalogue du pays retenu');
            }
            self::assertSame(count(array_filter($g['items'], static fn (array $it): bool => !$it['done'])), $g['to_import']);
            self::assertLessThanOrEqual(Reco::PER_GROUP, count($g['items']));
        }
        // Sources : « 6 membres TACP → JTAC… ».
        $tacp = array_values(array_filter($r['groups'][0]['sources'], static fn (array $s): bool => $s['label'] === 'TACP'))[0];
        self::assertSame(6, $tacp['members']);
        self::assertContains('us-af-tacp-icc', $tacp['ids']);
    }

    public function testEngineerUnitGetsEngineeringQualifications(): void
    {
        $r = self::reco(['units' => [['id' => 5, 'name' => 'Compagnie du génie', 'members' => 12]], 'country' => 'FR']);
        self::assertSame('affiliation', $r['detected']['country_source']);
        self::assertSame('engineer', $r['detected']['natures'][0]['key']);
        $units = self::group($r, 'units');
        self::assertArrayHasKey('fr-nedex-air', $units);
        self::assertSame(12, $units['fr-nedex-air']['members']);
        self::assertSame([], self::group($r, 'jobs'));

        $empty = self::reco(['units' => [['id' => 1, 'name' => 'Section Alpha']]]);
        self::assertSame([], $empty['groups']);
        self::assertSame('', $empty['detected']['country']);
    }

    public function testConfidentialUnitsAreNotNamedToViewersWithoutClearance(): void
    {
        $hidden = self::reco(self::sts(true));
        self::assertSame('special_tactics', $hidden['detected']['natures'][0]['key'], 'comptée dans la détection');
        self::assertSame(['name' => '', 'confidential' => true], $hidden['detected']['natures'][0]['sources'][0]);
        $json = (string) json_encode($hidden);
        self::assertStringNotContainsString('24th STS', $json);
        self::assertStringNotContainsString('Section Alpha', $json);

        $staff = self::reco(self::sts(true, true));
        self::assertSame('24th STS', $staff['detected']['natures'][0]['sources'][0]['name']);
    }

    public function testMissingPrerequisitesOfImportedQualificationsAndCachedStateIsRefreshed(): void
    {
        $r = self::reco(['imported' => ['US-AF-TACPICC' => true], 'imported_definitions' => ['US-AF-TACPICC' => 11]]);
        $prereqs = self::group($r, 'prereqs');
        self::assertArrayHasKey('us-af-tacp-apprentice', $prereqs);
        self::assertFalse($prereqs['us-af-tacp-apprentice']['done']);

        $code = strtoupper((string) $prereqs['us-af-tacp-apprentice']['code']);
        $after = Reco::markImported($r, ['US-AF-TACPICC' => true, $code => true]);
        $prereqs = self::group($after, 'prereqs');
        self::assertTrue($prereqs['us-af-tacp-apprentice']['done'], 'importée depuis : marquée sans recalcul');
        self::assertSame($r['groups'][0]['to_import'] - 1, $after['groups'][0]['to_import']);
    }

    public function testRouteControllerCacheViewAndScriptAreWired(): void
    {
        $routes = (string) file_get_contents(self::ROOT . '/routes/web.php');
        self::assertStringContainsString("\$router->get('/academy/catalogue/assistant/recommandations', [\\App\\Controllers\\Web\\AcademyCatalogImportController::class, 'recommendations'], \$academyMw);", $routes);
        $ctrl = (string) file_get_contents(self::ROOT . '/app/Controllers/Web/AcademyCatalogImportController.php');
        self::assertMatchesRegularExpression('/function recommendations\(.*?\$this->guard\(\).*?Session::get\(self::RECO.*?CommunityQualificationRecommender::markImported.*?Session::set\(self::RECO/s', $ctrl);
        self::assertStringContainsString('$caps->viewHiddenUnits', $ctrl);
        $view = (string) file_get_contents(self::ROOT . '/views/academy/import_wizard.php');
        self::assertLessThan(strpos($view, 'data-aiw-dims'), strpos($view, 'data-aiw-reco'), 'en tête, avant la portée');
        $js = (string) file_get_contents(self::ROOT . '/public/assets/js/academy-import-wizard.js');
        foreach (["request('/recommandations')", 'data-aiw-reco-all', 'data-aiw-reco-item', 'S.manual[it.id] = true', 'Ce qu’on a détecté', 'unité confidentielle'] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
        $en = (string) file_get_contents(self::ROOT . '/lang/en/site_auto.php');
        foreach (['Recommandé pour votre communauté', 'Pour vos emplois', 'Pour vos unités', 'Prérequis manquants', 'Ce qu’on a détecté', 'Pays retenu'] as $fr) {
            self::assertStringContainsString("'" . $fr . "' =>", $en, $fr);
        }
    }
}
