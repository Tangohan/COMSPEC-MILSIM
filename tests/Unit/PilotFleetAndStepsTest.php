<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\PilotFleetService;
use App\Services\Personnel\PilotQualificationEngine;
use App\Services\Personnel\PilotSyllabusStepService as Steps;
use App\Services\Personnel\QualificationAwardRuleService as Svc;
use App\Services\Personnel\QualificationCriteriaEngine as Eng;
use App\Support\PilotFleet;
use App\Support\QualificationCriteriaCatalog as Cat;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Cursus pilote : appareils réellement pilotés (relevés par le mod), appareils et familles de la communauté, paliers
 * de la communauté délivrés par le moteur commun des qualifications, migration rejouable.
 */
final class PilotFleetAndStepsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** @return list<array<string, mixed>> */
    private static function flown(): array
    {
        return [
            ['vehicle_class' => 'RHS_UH60M', 'vehicle_label' => 'UH-60M', 'vehicle_kind' => 'heli', 'seconds' => 3 * 3600, 'pilots' => 2, 'last_flown_at' => '2026-10-20 10:00:00'],
            ['vehicle_class' => 'B_Heli_Transport_01_F', 'vehicle_label' => 'Ghost Hawk', 'vehicle_kind' => 'heli', 'seconds' => 2 * 3600, 'pilots' => 1, 'last_flown_at' => '2026-10-22 10:00:00'],
            ['vehicle_class' => 'UK3CB_BAF_Tigre', 'vehicle_label' => 'Tigre HAD', 'vehicle_kind' => 'heli', 'seconds' => 3600, 'pilots' => 1, 'last_flown_at' => '2026-10-01 10:00:00'],
            ['vehicle_class' => 'B_Plane_Fighter_01_F', 'vehicle_label' => 'F/A-181 Black Wasp II', 'vehicle_kind' => 'plane', 'seconds' => 1800, 'pilots' => 1, 'last_flown_at' => '2026-10-02 10:00:00'],
        ];
    }

    private static function communityFleet(): PilotFleet
    {
        return new PilotFleet(
            ['g_evasan' => ['name' => 'EVASAN', 'sort' => 5, 'archived' => false], 'drone' => ['name' => 'Drone', 'sort' => 50, 'archived' => true]],
            [
                'ac_tigre' => ['name' => 'Tigre', 'track' => 'heli', 'group_key' => 'attack', 'is_custom' => 1, 'archived_at' => null],
                'uh60' => ['name' => 'HH-60 EVASAN', 'track' => null, 'group_key' => 'g_evasan', 'is_custom' => 0, 'archived_at' => null],
            ],
            ['uk3cb_baf_tigre' => 'ac_tigre', 'uk3cb_baf_tigre_hap' => 'ac_tigre']
        );
    }

    public function testFlownAircraftAreDetectedAndGroupedByAircraftAndFamily(): void
    {
        $list = PilotFleetService::buildAircraft(PilotFleet::empty(), self::flown(), ['rhs_uh60m' => [5, 6], 'b_heli_transport_01_f' => [5]]);
        $by = array_column($list, null, 'key');
        // Le Ghost Hawk d'Arma est rangé avec le Black Hawk : heures cumulées, pilotes sans doublon, dernier vol.
        self::assertSame(5.0, $by['uh60']['hours']);
        self::assertSame(2, $by['uh60']['pilots']);
        self::assertSame('2026-10-22 10:00:00', $by['uh60']['last_flown_at']);
        self::assertSame(['RHS_UH60M', 'B_Heli_Transport_01_F'], array_column($by['uh60']['classes'], 'class'));
        self::assertSame('transport', $by['uh60']['group']);
        self::assertTrue($by['uh60']['flown']);
        // Appareil inconnu du site : sa classe, famille devinée d'après la classe.
        self::assertSame('jet', $by['class:b_plane_fighter_01_f']['group']);
        self::assertSame('plane', $by['class:b_plane_fighter_01_f']['track']);
        // Appareils reconnus jamais pilotés : proposés quand même, après les pilotés.
        self::assertFalse($by['ch47']['flown']);
        self::assertSame(0.0, $by['ch47']['hours']);
        self::assertTrue($list[0]['flown']);
        self::assertSame('uh60', $list[0]['key'], 'le plus piloté d\'abord');
    }

    public function testGroupsAreGuessedFromTheArmaClassWhenTheAircraftIsUnknown(): void
    {
        self::assertSame('drone', PilotFleet::guessGroup('B_UAV_02_dynamicLoadout_F', 'Greyhawk'));
        self::assertSame('jet', PilotFleet::guessGroup('O_Plane_Fighter_02_F', 'To-201 Shikra'));
        self::assertSame('cargo', PilotFleet::guessGroup('B_T_VTOL_01_infantry_F', 'V-44 X Blackfish'));
        self::assertSame('attack', PilotFleet::guessGroup('O_Heli_Attack_02_dynamicLoadout_F', 'Mi-48 Kajman'));
        self::assertSame('other', PilotFleet::guessGroup('XYZ_Custom_1', ''));
        self::assertSame('transport', PilotFleet::builtinGroup('ch47'));
    }

    public function testCommunityAircraftAddsItsOwnScopeAndInstalledSyllabusKeepsItsHours(): void
    {
        $fleet = self::communityFleet();
        $rows = [
            ['vehicle_class' => 'UK3CB_BAF_Tigre', 'vehicle_label' => 'Tigre HAD', 'vehicle_kind' => 'heli', 'seconds' => 3600, 'last_flown_at' => '2026-10-01 10:00:00'],
            ['vehicle_class' => 'UK3CB_BAF_Tigre_HAP', 'vehicle_label' => 'Tigre HAP', 'vehicle_kind' => 'heli', 'seconds' => 1800, 'last_flown_at' => '2026-10-03 10:00:00'],
            ['vehicle_class' => 'RHS_UH60M', 'vehicle_label' => 'UH-60M', 'vehicle_kind' => 'heli', 'seconds' => 7200, 'last_flown_at' => '2026-10-04 10:00:00'],
        ];
        $t = PilotQualificationEngine::scopeTotals($rows, $fleet);
        self::assertSame(5400, $t['family:ac_tigre']['seconds'], 'les deux variantes comptent pour l\'appareil de la communauté');
        self::assertSame(3600, $t['family:class:uk3cb_baf_tigre']['seconds'], 'un cursus déjà installé sur la classe garde ses heures');
        self::assertSame(5400, $t['group:attack']['seconds']);
        self::assertSame(7200, $t['group:g_evasan']['seconds'], 'appareil reconnu rangé dans une famille de la communauté');
        self::assertSame(7200, $t['family:uh60']['seconds']);
        self::assertSame(12600, $t['kind:heli']['seconds']);
        // Sans réglage : exactement comme avant, plus la famille déduite.
        $plain = PilotQualificationEngine::scopeTotals($rows);
        self::assertArrayNotHasKey('family:ac_tigre', $plain);
        self::assertSame(7200, $plain['group:transport']['seconds']);

        self::assertSame('HH-60 EVASAN', $fleet->resolve('RHS_UH60M', 'UH-60M', 'heli')['label']);
        self::assertSame(['transport' => 'Transport', 'g_evasan' => 'EVASAN'], array_slice($fleet->groups(), 0, 2, true));
        self::assertArrayNotHasKey('drone', $fleet->groups(), 'famille archivée : plus proposée');
        self::assertSame(['uk3cb_baf_tigre', 'uk3cb_baf_tigre_hap'], $fleet->classesOf('ac_tigre'));
        $list = array_column(PilotFleetService::buildAircraft($fleet, self::flown()), null, 'key');
        self::assertSame('Tigre', $list['ac_tigre']['label']);
        self::assertTrue($list['ac_tigre']['custom']);
        self::assertSame('ac_tigre_had', PilotFleet::customKey('Tigre HAD'));
        self::assertSame('g_evasan_nuit', PilotFleet::groupKey('Évasan nuit'));
    }

    public function testFlightCountsPerScope(): void
    {
        $counts = Svc::flightCounts([7 => [
            ['vehicle_class' => 'RHS_UH60M', 'vehicle_label' => 'UH-60M', 'vehicle_kind' => 'heli', 'flights' => 4],
            ['vehicle_class' => 'UK3CB_BAF_Tigre', 'vehicle_label' => 'Tigre', 'vehicle_kind' => 'heli', 'flights' => 2],
        ]], self::communityFleet())[7];
        self::assertSame(6, $counts['']);
        self::assertSame(6, $counts['kind:heli']);
        self::assertSame(4, $counts['family:uh60']);
        self::assertSame(2, $counts['family:ac_tigre']);
        self::assertSame(2, $counts['vclass:uk3cb_baf_tigre']);
        self::assertSame('group:transport', Cat::normalizeScope('flight_count', 'group:transport'));
        self::assertTrue(Cat::isMetric('flight_count'));
    }

    /** @return array<string, mixed> */
    private static function stepInput(): array
    {
        return [
            'name' => 'Pilote EVASAN',
            'track' => 'heli',
            'total_hours' => '10',
            'scope' => ['group:g_evasan', 'family:ac_tigre', '', ''],
            'scope_hours' => ['4,5', '0', '3', ''],
            'flights' => '3',
            'flights_scope' => 'group:g_evasan',
            'night_hours' => '1',
            'prereq_step' => '12',
            'prereq_qualification' => '0',
            'instructor' => '1',
        ];
    }

    public function testCustomStepBecomesARuleOfTheCommonEngineAndReadsBack(): void
    {
        $form = Steps::normalizeForm(self::stepInput());
        self::assertSame([['scope' => 'group:g_evasan', 'hours' => 4.5]], $form['scopes'], 'lignes vides ou sans heures ignorées');
        $rule = Steps::buildRule($form);
        $items = Eng::criteria($rule);
        self::assertSame(['flight_hours', 'flight_hours', 'flight_count', 'flight_cond_hours', null], array_map(static fn (array $c): ?string => $c['metric'] ?? null, $items));
        self::assertSame('kind:heli', $items[0]['scope']);
        self::assertSame('cond:night:heli', $items[3]['scope']);
        self::assertSame(['type' => 'qualification', 'definition_id' => 12], $items[4]);

        $back = Steps::parseRule($rule, 'heli', [12]);
        self::assertSame(10.0, $back['total_hours']);
        self::assertSame([['scope' => 'group:g_evasan', 'hours' => 4.5]], $back['scopes']);
        self::assertSame(3, $back['flights']);
        self::assertSame('group:g_evasan', $back['flights_scope']);
        self::assertSame(1.0, $back['night_hours']);
        self::assertSame(12, $back['prereq_step']);
        self::assertSame([], $back['extra_items']);

        // Filière « autre » : heures du carnet en hélicoptère ou en avion ; un critère ajouté ailleurs est gardé.
        $other = Steps::normalizeForm(['name' => 'Drone', 'track' => 'other', 'total_hours' => '2', 'ifr_hours' => '1']);
        $other['extra_items'] = [['type' => 'academy', 'course_key' => 'jtac']];
        $r2 = Steps::buildRule($other);
        self::assertSame('any', $r2['groups'][1]['op']);
        $b2 = Steps::parseRule($r2, 'other');
        self::assertSame(2.0, $b2['total_hours']);
        self::assertSame(1.0, $b2['ifr_hours']);
        self::assertSame([['type' => 'academy', 'course_key' => 'jtac']], $b2['extra_items']);
        self::assertSame([], Steps::buildRule(Steps::normalizeForm(['name' => 'Vide'])), 'sans condition : pas de règle');
    }

    public function testCustomStepIsDeliveredByTheCommonEngineFromTheModFlights(): void
    {
        $fleet = self::communityFleet();
        $rows = [7 => [
            ['vehicle_class' => 'RHS_UH60M', 'vehicle_label' => 'UH-60M', 'vehicle_kind' => 'heli', 'seconds' => 8 * 3600, 'last_flown_at' => '2026-10-20 10:00:00'],
            ['vehicle_class' => 'UK3CB_BAF_Tigre', 'vehicle_label' => 'Tigre', 'vehicle_kind' => 'heli', 'seconds' => 3 * 3600, 'last_flown_at' => '2026-10-21 10:00:00'],
        ]];
        $log = [7 => [['status' => 'countersigned', 'source' => 'mod', 'vehicle_class' => 'RHS_UH60M', 'vehicle_label' => 'UH-60M', 'vehicle_kind' => 'heli', 'seconds' => 3600, 'flown_on' => '2026-10-20', 'night_seconds' => 3600]]];
        $t = Svc::flightScopes($rows, $log, $fleet)[7];
        $counts = Svc::flightCounts([7 => [['vehicle_class' => 'RHS_UH60M', 'vehicle_label' => 'UH-60M', 'vehicle_kind' => 'heli', 'flights' => 3]]], $fleet)[7];
        $measure = static function (array $c) use ($t, $counts): float {
            if ($c['metric'] === 'flight_count') {
                return (float) ($counts[$c['scope']] ?? 0);
            }

            return ($t[$c['scope']]['seconds'] ?? 0) / 3600;
        };
        $rule = Eng::normalize(Steps::buildRule(Steps::normalizeForm(self::stepInput())));
        $held = static fn (int $id): bool => $id === 12;
        $ev = Eng::evaluate($rule, $measure, $held, static fn (): bool => false);
        self::assertTrue($ev['met'], '11 h en hélicoptère, 8 h EVASAN, 3 vols EVASAN, 1 h de nuit contresignée, palier précédent détenu');
        self::assertSame(Eng::ELIGIBLE, Eng::state(null, $ev['met'], true));
        // Palier précédent pas encore obtenu : pas délivré.
        $ev2 = Eng::evaluate($rule, $measure, static fn (): bool => false, static fn (): bool => false);
        self::assertFalse($ev2['met']);
        self::assertSame(Eng::PROGRESS, Eng::state(null, $ev2['met'], true));
        // Déjà titulaire, ou ajourné : jamais redonné tout seul.
        self::assertSame(Eng::HELD, Eng::state(['admin_status' => 'obtained'], true, true));
        self::assertSame(Eng::BLOCKED, Eng::state(['admin_status' => 'failed'], true, true));
    }

    private static function base(): MysqlDdlSqlitePdo
    {
        $pdo = new MysqlDdlSqlitePdo();
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec('CREATE TABLE personnel_qualification_definitions (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, code TEXT, name TEXT)');
        $pdo->exec('CREATE TABLE pilot_qualification_rules (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, definition_id INTEGER, scope TEXT)');
        $pdo->exec("INSERT INTO tenants (id, name) VALUES (1, 'SOAR')");
        $pdo->exec("INSERT INTO personnel_qualification_definitions (id, tenant_id, code, name) VALUES (5, 1, 'PIL-UH60-QT', 'Qualification de type UH-60')");
        $pdo->exec("INSERT INTO pilot_qualification_rules (tenant_id, definition_id, scope) VALUES (1, 5, 'family:uh60')");
        $pdo->changes = 0;

        return $pdo;
    }

    private static function runStep(\PDO $pdo): string
    {
        $root = self::ROOT;
        $migrationFlush = static function (): void {
        };
        $migrationEnsurePdo = static function (): void {
        };
        require $root . '/database/steps/_context.php';
        ob_start();
        try {
            require $root . '/database/steps/3570_pilot_fleet_and_steps.php';
        } finally {
            $out = (string) ob_get_clean();
        }

        return $out;
    }

    public function testMigrationCreatesTheTablesOnceAndLeavesInstalledSyllabusesIntact(): void
    {
        $pdo = self::base();
        self::assertStringContainsString('Tables créées', self::runStep($pdo));
        foreach (['pilot_aircraft_groups', 'pilot_aircraft', 'pilot_aircraft_classes', 'pilot_syllabus_steps'] as $t) {
            self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM ' . $t)->fetchColumn(), $t . ' : aucune ligne créée');
        }
        self::assertSame('family:uh60', $pdo->query('SELECT scope FROM pilot_qualification_rules')->fetchColumn(), 'cursus installé intact');
        $pdo->changes = 0;
        self::assertStringContainsString('déjà présentes', self::runStep($pdo));
        self::assertSame(0, $pdo->changes, 'second passage : rien ne change');

        $empty = new MysqlDdlSqlitePdo();
        self::assertStringContainsString('rien à créer', self::runStep($empty));
        self::assertSame(0, $empty->changes);

        $registry = (string) file_get_contents(self::ROOT . '/database/steps/registry.php');
        self::assertStringContainsString("'file' => '3570_pilot_fleet_and_steps.php'", $registry);
        $sql = (string) file_get_contents(self::ROOT . '/migrations/2026_11_02_120_pilot_fleet_and_steps.sql');
        foreach (['uk_pilot_ac_group_key', 'uk_pilot_aircraft_key', 'uk_pilot_ac_class', 'fk_pilot_ac_class_aircraft', 'uk_pilot_step_definition', 'fk_pilot_step_definition'] as $name) {
            self::assertStringContainsString($name, $sql);
        }
        self::assertStringNotContainsString('DROP ', $sql);
        self::assertStringNotContainsString('DELETE FROM', $sql);
        self::assertStringContainsString('pilot_fleet_and_steps', (string) file_get_contents(self::ROOT . '/docs/technical/migrations-active-queue.md'));
    }

    public function testRoutesAndContainerAreWired(): void
    {
        $routes = (string) file_get_contents(self::ROOT . '/routes/web.php');
        foreach (["cursus-pilote/appareils'", "cursus-pilote/familles'", "cursus-pilote/paliers-communaute'", "cursus-pilote/paliers-communaute/{stepId}/action'", "cursus-pilote/paliers-communaute/propositions/{awardId}'"] as $path) {
            self::assertStringContainsString($path, $routes);
        }
        $container = (string) file_get_contents(self::ROOT . '/app/Core/Container.php');
        self::assertStringContainsString('self::get(\App\Services\Personnel\PilotSyllabusStepService::class)', $container);
        self::assertStringContainsString('self::get(\App\Services\Personnel\PilotFleetService::class)', $container);
    }
}
