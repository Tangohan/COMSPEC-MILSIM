<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakPlayedOpRepository;
use App\Services\Atak\PlayedOperationsService;
use App\Support\AtakOpReconciler;
use App\Support\AtakPlayedOpExclusion;
use App\Support\AtakPlayedOpRules;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Parties détectées en jeu : règles (type détecté, « ignorer à l'avenir »), rapprochement avec l'opération prévue,
 * exclusion des heures en SQL, isolation des communautés.
 */
final class AtakPlayedOperationsTest extends TestCase
{
    private const KEY = 'Train_CQB@Altis#202611011930';

    protected function tearDown(): void
    {
        AtakPlayedOpExclusion::setReady(null);
        $p = new \ReflectionProperty(AtakPlayedOpRepository::class, 'schema');
        $p->setValue(null, null);
    }

    public function testFieldValuesAndGlobPatterns(): void
    {
        self::assertSame('Train_CQB', AtakPlayedOpRules::fieldValue(self::KEY, 'mission'));
        self::assertSame('Altis', AtakPlayedOpRules::fieldValue(self::KEY, 'map'));
        self::assertTrue(AtakPlayedOpRules::matches('train_*', 'Train_CQB'));
        self::assertTrue(AtakPlayedOpRules::matches('altis', 'Altis'));
        self::assertFalse(AtakPlayedOpRules::matches('Train', 'Train_CQB'), 'sans joker : valeur exacte');
        self::assertTrue(AtakPlayedOpRules::matches('Tr?in_CQB', 'Train_CQB'));
        self::assertFalse(AtakPlayedOpRules::matches('', 'Train_CQB'));
        self::assertSame('Train\\_%', AtakPlayedOpRules::toLike('Train_*'));
        self::assertSame('100\\%_x', AtakPlayedOpRules::toLike('100%?x'));
    }

    public function testIgnoreRuleAppliesToNewPartiesUnlessCountedAnyway(): void
    {
        $rules = [['id' => 4, 'match_field' => 'mission', 'pattern' => 'tempMission*', 'disabled_at' => null]];
        $next = 'tempMissionSP@Stratis#202612011200';
        $r = AtakPlayedOpRules::resolveIgnore($next, [], $rules);
        self::assertTrue($r['ignored']);
        self::assertSame('rule', $r['by']);
        self::assertSame(4, $r['rule']['id']);

        self::assertFalse(AtakPlayedOpRules::resolveIgnore($next, ['count_anyway' => 1], $rules)['ignored']);
        self::assertFalse(AtakPlayedOpRules::resolveIgnore(self::KEY, [], $rules)['ignored']);
        $rules[0]['disabled_at'] = '2026-11-02 10:00:00';
        self::assertFalse(AtakPlayedOpRules::resolveIgnore($next, [], $rules)['ignored'], 'règle désactivée');
        self::assertSame('manual', AtakPlayedOpRules::resolveIgnore(self::KEY, ['ignored_at' => '2026-11-02 10:00:00'], [])['by']);

        $map = [['id' => 5, 'match_field' => 'map', 'pattern' => 'VR', 'disabled_at' => null]];
        self::assertTrue(AtakPlayedOpRules::resolveIgnore('Essai@VR#202611011200', [], $map)['ignored']);
    }

    public function testTypeDetectedByRuleManualFirstArchivedSkipped(): void
    {
        $types = [
            ['id' => 2, 'name' => 'Entraînement', 'match_field' => 'mission', 'pattern' => 'Train_*', 'archived_at' => null, 'category' => 'Entraînement'],
            ['id' => 3, 'name' => 'Altis', 'match_field' => 'map', 'pattern' => 'Altis', 'archived_at' => null],
            ['id' => 9, 'name' => 'Ancien', 'match_field' => 'mission', 'pattern' => '*', 'archived_at' => '2026-01-01 00:00:00'],
        ];
        $t = AtakPlayedOpRules::resolveType(self::KEY, 'Train CQB', $types);
        self::assertSame(2, $t['type']['id']);
        self::assertSame('rule', $t['source']);
        self::assertSame(3, AtakPlayedOpRules::resolveType(self::KEY, '', $types, 3)['type']['id']);
        self::assertSame('manual', AtakPlayedOpRules::resolveType(self::KEY, '', $types, 3)['source']);
        self::assertNull(AtakPlayedOpRules::resolveType('Op_Aube@Tanoa#202611011930', '', $types));
        $byName = [['id' => 1, 'name' => 'Campagne', 'match_field' => 'name', 'pattern' => 'Campagne *', 'archived_at' => null]];
        self::assertSame(1, AtakPlayedOpRules::resolveType(self::KEY, 'Campagne Aube - ép. 2', $byName)['type']['id']);
    }

    public function testSuggestionFromMissionNamePlayersAndDuration(): void
    {
        self::assertSame('Entraînement', AtakPlayedOpRules::suggest(self::KEY, 6, 3600)['category']);
        self::assertSame('Test', AtakPlayedOpRules::suggest('tempMissionSP@Altis#202611011930', 1, 600)['category']);
        self::assertSame(['short'], AtakPlayedOpRules::suggest('Aube@Altis#202611011930', 2, 900)['reasons']);
        self::assertSame('Opération', AtakPlayedOpRules::suggest('Aube@Altis#202611011930', 12, 7200)['category']);
        self::assertNull(AtakPlayedOpRules::suggest('Aube@Altis#202611011930', 4, 7200));
        self::assertSame(['Test', 'campagne'], AtakPlayedOpRules::parseCategories("Test\ncampagne, test ;Campagne"));
    }

    public function testReconcilerScoresSlotMapAndPlannedPlayers(): void
    {
        $start = PlayedOperationsService::keyStartTs(self::KEY);
        self::assertSame(gmmktime(19, 30, 0, 11, 1, 2026), $start);
        $op = ['start_ts' => $start, 'map' => 'Altis', 'users' => [1, 2, 3, 4]];
        $plan = ['kind' => 'plan', 'id' => 7, 'title' => 'Op Aube', 'start_ts' => $start - 20 * 60, 'map' => 'altis', 'users' => [1, 2, 3, 9]];
        $s = AtakOpReconciler::score($op, $plan);
        self::assertGreaterThanOrEqual(AtakOpReconciler::AUTO_MIN, $s['score']);
        self::assertSame(['slot', 'map', 'players'], array_column($s['signals'], 'code'));
        self::assertSame(20, $s['signals'][0]['value']);

        $far = ['kind' => 'event', 'id' => 8, 'title' => 'Loin', 'start_ts' => $start - 6 * 3600, 'map' => '', 'users' => []];
        self::assertSame(0, AtakOpReconciler::score($op, $far)['score'], 'hors créneau : pas de proposition');
        $window = ['kind' => 'cycle', 'id' => 3, 'title' => 'Cycle', 'start_ts' => $start - 3 * 3600, 'end_ts' => $start + 3600, 'users' => []];
        self::assertSame('window', AtakOpReconciler::score($op, $window)['signals'][0]['code']);

        $best = AtakOpReconciler::best($op, [$far, $plan, $window]);
        self::assertSame(7, $best['cand']['id']);
        self::assertNull(AtakOpReconciler::best($op, [$far]));
        self::assertSame(['plan', 12], AtakOpReconciler::parseLink('plan:12'));
        self::assertSame(['none', 0], AtakOpReconciler::parseLink('none'));
        self::assertSame(['auto', 0], AtakOpReconciler::parseLink('plan:abc'));
    }

    public function testPresentLinksIgnoresAndStepsWithoutDatabase(): void
    {
        $start = (int) PlayedOperationsService::keyStartTs(self::KEY);
        $d = ['day' => '2026-11-01', 'players' => 4, 'play_seconds' => 14400, 'max_play_seconds' => 3600, 'last_seen' => date('Y-m-d H:i:s', $start + 3600), 'reports' => 3];
        $plan = ['kind' => 'plan', 'id' => 7, 'title' => 'Op Aube', 'start_ts' => $start - 600, 'map' => 'Altis', 'users' => [1, 2], 'published' => true];
        $aars = [['id' => 40, 'title' => 'CR Aube', 'status' => 'validated', 'plan_id' => 7, 'cycle_id' => 0]];
        $now = $start + 86400;

        $o = PlayedOperationsService::present(self::KEY, $d, [], [], [], [$plan], [1, 2], 0, $aars, $now);
        self::assertSame('auto', $o['link']['source']);
        self::assertSame(7, $o['link']['cand']['id']);
        self::assertSame('auto', $o['steps']['plan']);
        self::assertSame('done', $o['steps']['brief']);
        self::assertSame('done', $o['steps']['debrief'], 'compte rendu lié à l’opération rattachée');
        self::assertSame('auto', $o['steps']['effects']);
        self::assertFalse($o['live']);

        $none = PlayedOperationsService::present(self::KEY, $d, ['link_mode' => 'none'], [], [], [$plan], [], 0, [], $now);
        self::assertNull($none['link'], 'proposition refusée');
        $manual = PlayedOperationsService::present(self::KEY, $d, ['link_mode' => 'manual', 'event_id' => 5], [], [], [$plan], [], 0, [], $now);
        self::assertSame('event', $manual['link']['cand']['kind']);
        self::assertSame('manual', $manual['link']['source']);

        $rules = [['id' => 1, 'match_field' => 'map', 'pattern' => 'Altis', 'disabled_at' => null]];
        $ign = PlayedOperationsService::present(self::KEY, $d, [], [], $rules, [], [], 0, [], $now);
        self::assertTrue($ign['ignored']['ignored']);
        self::assertSame('off', $ign['steps']['effects']);
        self::assertSame('todo', $ign['steps']['debrief']);
    }

    public function testExclusionSqlIsTenantScopedAndEmptyWithoutSchema(): void
    {
        AtakPlayedOpExclusion::setReady(false);
        self::assertSame('', AtakPlayedOpExclusion::clause('t.mission_key', 't.tenant_id'));
        AtakPlayedOpExclusion::setReady(true);
        $sql = AtakPlayedOpExclusion::clause('t.mission_key', 't.tenant_id');
        self::assertStringStartsWith(' AND ', $sql);
        self::assertSame(3, substr_count($sql, '.tenant_id = t.tenant_id'), 'parties, règles et « compter quand même » de la même communauté');
        self::assertStringContainsString('xr.disabled_at IS NULL', $sql);
        self::assertStringContainsString("COALESCE(t.mission_key, '') <> ''", $sql, 'une ligne sans clé n’est jamais retirée');
    }

    public function testTypesRulesAndCategoriesStayInTheirCommunity(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE atak_op_types (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT, name TEXT, color TEXT, icon TEXT, match_field TEXT, pattern TEXT, category TEXT, created_by_user_id INT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, archived_at TEXT)");
        $pdo->exec("CREATE TABLE atak_op_ignore_rules (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT, match_field TEXT, pattern TEXT, like_pattern TEXT, note TEXT, created_by_user_id INT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, disabled_at TEXT)");
        $pdo->exec('CREATE TABLE atak_op_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT, name TEXT, position INT)');
        $pdo->exec('CREATE TABLE mission_plans (id INTEGER PRIMARY KEY, tenant_id INT)');
        $pdo->exec('INSERT INTO mission_plans (id, tenant_id) VALUES (70, 1), (71, 2)');
        $p = new \ReflectionProperty(AtakPlayedOpRepository::class, 'schema');
        $p->setValue(null, true);
        $repo = new AtakPlayedOpRepository($pdo);

        $typeId = $repo->createType(1, ['name' => 'Entraînement', 'match_field' => 'mission', 'pattern' => 'Train_*', 'color' => 'rouge'], 5);
        $ruleId = $repo->createRule(1, 'mission', 'tempMission*', 'serveur de test', 5);
        self::assertGreaterThan(0, $typeId);
        self::assertSame($ruleId, $repo->createRule(1, 'mission', 'TEMPMISSION*', '', 5), 'même règle : pas de doublon');
        self::assertSame('tempMission%', $repo->rules(1)[0]['like_pattern']);
        self::assertSame('#3b6ea5', $repo->types(1)[0]['color'], 'couleur invalide remplacée');
        $repo->saveCategories(1, ['Test', 'Campagne']);

        self::assertSame([], $repo->types(2));
        self::assertSame([], $repo->rules(2));
        self::assertNull($repo->findType(2, $typeId));
        self::assertSame(AtakPlayedOpRules::DEFAULT_CATEGORIES, $repo->categories(2));
        self::assertSame(['Test', 'Campagne'], $repo->categories(1));
        self::assertFalse($repo->setRuleDisabled(2, $ruleId, true) && $repo->rules(1) === []);
        self::assertTrue($repo->ownsTarget(1, 'plan', 70));
        self::assertFalse($repo->ownsTarget(1, 'plan', 71), 'opération d’une autre communauté');
        self::assertFalse($repo->ownsTarget(1, 'users', 1));
    }

    public function testManagementRoutesArePostWithCsrfAndManageRight(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        preg_match_all("#\\\$router->(get|post)\\('/back-office/atak/parties/[^']*', \\[\\\\App\\\\Controllers\\\\Admin\\\\AdminAtakPlayedOpsController::class, '(\\w+)'\\]#", $routes, $m);
        self::assertCount(11, $m[0]);
        self::assertSame(['post'], array_values(array_unique($m[1])));
        $src = (string) file_get_contents($root . '/app/Controllers/Admin/AdminAtakPlayedOpsController.php');
        self::assertStringContainsString("guardAtak('manage'", $src);
        self::assertStringContainsString('Csrf::validate', $src);
        foreach (array_unique($m[2]) as $action) {
            self::assertMatchesRegularExpression('/function ' . $action . '\(Request \$request, array \$params = \[\]\): Response\s*\{\s*\$ctx = \$this->context\(\$request\);/', $src, $action . ' passe par context()');
        }
    }
}
