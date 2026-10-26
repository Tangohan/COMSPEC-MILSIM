<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Billing\SubscriptionPlanFeaturesCatalog;
use App\Services\Cron\CronSchedule;
use App\Services\Personnel\PilotQualificationEngine as Eng;
use App\Services\Personnel\PilotQualificationService;
use App\Support\PilotSyllabusCatalog as Cat;
use PHPUnit\Framework\TestCase;

/** Cursus pilote : paliers inspirés des vrais cursus, attribués d'après les heures de vol relevées en jeu. */
final class PilotQualificationTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** @return list<array<string, mixed>> */
    private static function flights(): array
    {
        return [
            ['vehicle_class' => 'RHS_UH60M', 'vehicle_label' => 'UH-60M', 'vehicle_kind' => 'heli', 'seconds' => 3 * 3600, 'last_flown_at' => '2026-10-20 10:00:00'],
            ['vehicle_class' => 'B_Heli_Transport_01_F', 'vehicle_label' => 'Ghost Hawk', 'vehicle_kind' => 'heli', 'seconds' => 2 * 3600, 'last_flown_at' => '2026-10-22 10:00:00'],
            ['vehicle_class' => 'RHS_AH64D', 'vehicle_label' => 'AH-64D', 'vehicle_kind' => 'heli', 'seconds' => 3600, 'last_flown_at' => '2026-10-01 10:00:00'],
            ['vehicle_class' => 'RHS_C130J', 'vehicle_label' => 'C-130J', 'vehicle_kind' => 'plane', 'seconds' => 1800, 'last_flown_at' => '2026-10-01 10:00:00'],
        ];
    }

    public function testFlightHoursAreGroupedByAircraftTypeAndTrack(): void
    {
        $t = Eng::scopeTotals(self::flights());
        // Le Ghost Hawk d'Arma est rangé avec le Black Hawk : 3 h + 2 h, dernier vol le plus récent des deux.
        self::assertSame(5 * 3600, $t['family:uh60']['seconds']);
        self::assertSame('2026-10-22 10:00:00', $t['family:uh60']['last_flown_at']);
        self::assertSame(6 * 3600, $t['kind:heli']['seconds']);
        self::assertSame(1800, $t['kind:plane']['seconds']);
        self::assertSame(1800, $t['family:c130']['seconds']);
    }

    public function testTierNeedsHoursAndThePreviousTierObtained(): void
    {
        $totals = Eng::scopeTotals(self::flights());
        $rule = ['definition_id' => 10, 'scope' => 'family:uh60', 'track' => 'heli', 'min_scope_hours' => 3, 'min_track_hours' => 0, 'prereq_definition_id' => 5];

        $noSolo = Eng::evaluate($rule, $totals, []);
        self::assertSame(Eng::PROGRESS, $noSolo['state']);
        self::assertTrue($noSolo['hours_ok']);
        self::assertFalse($noSolo['prereq_ok']);

        $pendingSolo = Eng::evaluate($rule, $totals, [5 => ['id' => 1, 'admin_status' => 'in_evaluation']]);
        self::assertSame(Eng::PROGRESS, $pendingSolo['state'], 'un palier précédent seulement proposé ne débloque rien');

        $eligible = Eng::evaluate($rule, $totals, [5 => ['id' => 1, 'admin_status' => 'obtained']]);
        self::assertSame(Eng::ELIGIBLE, $eligible['state']);
        self::assertSame(5.0, $eligible['scope_hours']);
        self::assertSame(100, $eligible['pct']);

        $rule['min_track_hours'] = 12;
        $short = Eng::evaluate($rule, $totals, [5 => ['id' => 1, 'admin_status' => 'obtained']]);
        self::assertSame(Eng::PROGRESS, $short['state']);
        self::assertSame(50, $short['pct'], '6 h sur 12 h de filière : la condition la moins avancée donne la progression');
    }

    public function testExistingAwardsAreNeverProposedTwice(): void
    {
        $totals = Eng::scopeTotals(self::flights());
        $rule = ['definition_id' => 10, 'scope' => 'kind:heli', 'track' => 'heli', 'min_scope_hours' => 2, 'min_track_hours' => 0, 'prereq_definition_id' => null];

        self::assertSame(Eng::HELD, Eng::evaluate($rule, $totals, [10 => ['id' => 7, 'admin_status' => 'obtained']])['state']);
        self::assertSame(Eng::PENDING, Eng::evaluate($rule, $totals, [10 => ['id' => 7, 'admin_status' => 'in_evaluation']])['state']);
        self::assertSame(Eng::BLOCKED, Eng::evaluate($rule, $totals, [10 => ['id' => 7, 'admin_status' => 'failed']])['state']);
        self::assertSame(Eng::HELD, Eng::evaluate($rule, $totals, [10 => ['id' => 7, 'status' => 'valid']])['state'], 'anciennes lignes sans admin_status');
        self::assertSame(7, Eng::evaluate($rule, $totals, [10 => ['id' => 7, 'admin_status' => 'in_evaluation']])['award_id']);
    }

    public function testObtainedAwardWinsOverOlderAttempts(): void
    {
        $by = Eng::awardsByDefinition([
            ['id' => 1, 'definition_id' => 3, 'admin_status' => 'failed'],
            ['id' => 2, 'definition_id' => 3, 'admin_status' => 'obtained'],
            ['id' => 3, 'definition_id' => 3, 'admin_status' => 'in_evaluation'],
            ['id' => 4, 'definition_id' => 0, 'admin_status' => 'obtained'],
        ]);
        self::assertSame([3], array_keys($by));
        self::assertSame(2, (int) $by[3]['id']);
    }

    public function testSyllabusFollowsTheRealPilotPath(): void
    {
        $tiers = Cat::tiers();
        self::assertSame(['solo', 'type', 'rl1', 'pc', 'ip', 'night', 'ifr', 'form'], array_keys($tiers));
        foreach ($tiers as $key => $t) {
            if ($t['prereq'] !== null) {
                self::assertArrayHasKey($t['prereq'], $tiers, $key);
                self::assertLessThan(array_search($key, array_keys($tiers), true), array_search($t['prereq'], array_keys($tiers), true), $key . ' vient après son prérequis');
            }
            foreach ([Cat::TRACK_HELI, Cat::TRACK_PLANE] as $track) {
                self::assertNotSame('', $t['real'][$track] ?? '', $key . ' : référence réelle ' . $track);
            }
            self::assertContains($t['mode'], [Cat::MODE_DIRECT, Cat::MODE_PROPOSE]);
        }
        self::assertStringContainsString('FCL.210.H', $tiers['solo']['real'][Cat::TRACK_HELI]);
        self::assertStringContainsString('61.109', $tiers['solo']['real'][Cat::TRACK_PLANE]);
        self::assertStringContainsString('TC 3-04.11', $tiers['rl1']['real'][Cat::TRACK_HELI]);
        self::assertSame(Cat::MODE_DIRECT, $tiers['rl1']['mode'], 'le palier « heures seules » est automatique');
        self::assertSame('ip', $tiers['solo']['validator'], 'le lâché solo est validé par un instructeur');
        self::assertSame(Cat::PER_FAMILY, $tiers['type']['per']);
        self::assertSame(Cat::PER_TRACK, $tiers['night']['per']);
    }

    public function testDefinitionCodesAreShortStableAndUnique(): void
    {
        self::assertSame('PIL-H-SOLO', Cat::definitionCode(Cat::TRACK_HELI, 'solo', null));
        self::assertSame('PIL-A-NUIT', Cat::definitionCode(Cat::TRACK_PLANE, 'night', null));
        self::assertSame('PIL-UH60-QT', Cat::definitionCode(Cat::TRACK_HELI, 'type', 'uh60'));
        self::assertSame('PIL-CLASS-RHS_MELB_MH6M-OPS', Cat::definitionCode(Cat::TRACK_HELI, 'rl1', 'class:rhs_melb_mh6m'));
        self::assertLessThanOrEqual(64, strlen(Cat::definitionCode(Cat::TRACK_HELI, 'ip', 'class:' . str_repeat('x', 80))));
        $codes = [];
        foreach ([Cat::TRACK_HELI => 'uh60', Cat::TRACK_PLANE => 'c130'] as $track => $family) {
            foreach (array_keys(Cat::tiers()) as $tier) {
                $codes[] = Cat::definitionCode($track, $tier, null);
                $codes[] = Cat::definitionCode($track, $tier, $family);
            }
        }
        self::assertSame(count($codes), count(array_unique($codes)));
        self::assertSame('Qualification de type UH-60 Black Hawk', Cat::definitionName(Cat::TRACK_HELI, 'type', 'UH-60 Black Hawk'));
        self::assertSame('Lâché solo hélicoptère', Cat::definitionName(Cat::TRACK_HELI, 'solo', null));
        self::assertSame('family:uh60', Cat::scopeFor(Cat::TRACK_HELI, 'uh60'));
        self::assertSame('kind:plane', Cat::scopeFor(Cat::TRACK_PLANE, null));
    }

    public function testAircraftTypesAreSortedIntoTracks(): void
    {
        self::assertSame(Cat::TRACK_HELI, Cat::familyTrack('ah64'));
        self::assertSame(Cat::TRACK_PLANE, Cat::familyTrack('c130'));
        self::assertSame(Cat::TRACK_PLANE, Cat::familyTrack('class:rhs_l39', 'plane'));
        self::assertArrayHasKey('uh60', Cat::knownFamilies(Cat::TRACK_HELI));
        self::assertArrayNotHasKey('f16', Cat::knownFamilies(Cat::TRACK_HELI));
        self::assertArrayHasKey('f16', Cat::knownFamilies(Cat::TRACK_PLANE));
    }

    public function testAutomaticNoteExplainsTheHours(): void
    {
        $note = PilotQualificationService::autoNote(
            ['scope_label' => 'UH-60 Black Hawk', 'track' => 'heli'],
            ['scope_hours' => 5.0, 'target_scope' => 3.0, 'track_hours' => 6.0, 'target_track' => 0.0],
            false
        );
        self::assertSame('Proposée automatiquement, à valider par un instructeur : 5 h 00 de vol (UH-60 Black Hawk) pour 3 h 00 demandées.', $note);
        self::assertSame('45 min', Eng::formatHours(0.75));
        self::assertSame('12 h 30', Eng::formatHours(12.5));
    }

    public function testPilotSyllabusIsAPaidPlanModule(): void
    {
        self::assertContains('pilot_qualifications', SubscriptionPlanFeaturesCatalog::BOOL_FEATURES);
        self::assertFalse(SubscriptionPlanFeaturesCatalog::defaultsForSlug('free')['pilot_qualifications']);
        foreach (['standard', 'pro', 'pro_plus'] as $plan) {
            self::assertTrue(SubscriptionPlanFeaturesCatalog::defaultsForSlug($plan)['pilot_qualifications'], $plan);
        }
        self::assertSame(PilotQualificationService::FEATURE, 'pilot_qualifications');
    }

    public function testPageCronAndMigrationAreWired(): void
    {
        $routes = (string) file_get_contents(self::ROOT . '/routes/web.php');
        foreach (['cursus-pilote\'', 'cursus-pilote/installer', 'cursus-pilote/evaluer', 'cursus-pilote/paliers/{ruleId}', 'cursus-pilote/propositions/{awardId}'] as $path) {
            self::assertStringContainsString($path, $routes);
        }
        $container = (string) file_get_contents(self::ROOT . '/app/Core/Container.php');
        self::assertStringContainsString('PilotSyllabusController::class => new', $container);
        self::assertStringContainsString('self::get(\App\Services\Cron\Jobs\PilotQualificationsCronJob::class)', $container);
        self::assertSame(60, CronSchedule::intervalMinutes('pilot_qualifications'));

        $registry = (string) file_get_contents(self::ROOT . '/database/steps/registry.php');
        self::assertStringContainsString("'3100_pilot_qualification_rules.php'", $registry);
        self::assertFileExists(self::ROOT . '/database/steps/3100_pilot_qualification_rules.php');
        $migration = (string) file_get_contents(self::ROOT . '/bootstrap/pilot_qualification_rules_migration.php');
        self::assertStringContainsString('CREATE TABLE pilot_qualification_rules', $migration);
        self::assertStringContainsString('UNIQUE KEY uniq_pilot_rule_def (tenant_id, definition_id)', $migration);

        $nav = (string) file_get_contents(self::ROOT . '/views/partials/ath_sidebar_nav.php');
        self::assertStringContainsString("'Cursus pilote'", $nav);
        $view = (string) file_get_contents(self::ROOT . '/views/admin/organization/qualifications/pilot_syllabus.php');
        self::assertStringContainsString('À valider par un instructeur', $view);
        self::assertStringContainsString('Vraie vie :', $view);
        self::assertStringContainsString("url('platform/upgrade')", $view);
        self::assertFileExists(self::ROOT . '/public/assets/css/referentiel-pilot-syllabus.css');
        $member = (string) file_get_contents(self::ROOT . '/views/admin/member_situation/qualifications.php');
        self::assertStringContainsString('_pilot_progress.php', $member);
    }
}
