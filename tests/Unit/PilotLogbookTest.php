<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\PilotLogbookService as Book;
use App\Services\Personnel\PilotQualificationEngine as Eng;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Carnet de vol : le pilote note ses vols, un instructeur contresigne, puis ses heures comptent pour le cursus. */
final class PilotLogbookTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    public function testDurationsAreReadLikeAPilotWritesThem(): void
    {
        self::assertSame(5400, Book::parseDuration('1:30'));
        self::assertSame(5400, Book::parseDuration('1h30'));
        self::assertSame(5400, Book::parseDuration('1 h 30'));
        self::assertSame(7200, Book::parseDuration('2h'));
        self::assertSame(5400, Book::parseDuration('1,5'));
        self::assertSame(5400, Book::parseDuration('90 min'));
        self::assertSame(0, Book::parseDuration('beaucoup'));
        self::assertSame(0, Book::parseDuration(''));
    }

    public function testEntryIsNormalisedAndConditionHoursAreCappedAtTheFlight(): void
    {
        $d = Book::normalizeEntry([
            'flown_on' => '2026-10-20', 'duration' => '1:30', 'aircraft' => 'uh60', 'crew_role' => 'pic',
            'night' => '3h', 'ifr' => '', 'formation' => '0:20', 'landings' => '4', 'remarks' => '  Navigation basse altitude ',
        ], new DateTimeImmutable('2026-10-26'));
        self::assertSame('2026-10-20', $d['flown_on']);
        self::assertSame(5400, $d['seconds']);
        self::assertSame('heli', $d['vehicle_kind']);
        self::assertSame('pic', $d['crew_role']);
        self::assertSame(5400, $d['night_seconds']);
        self::assertSame(0, $d['ifr_seconds']);
        self::assertSame(1200, $d['formation_seconds']);
        self::assertSame(4, $d['landings']);
        self::assertSame('Navigation basse altitude', $d['remarks']);
    }

    public function testOtherAircraftAndUnknownRoleAreAccepted(): void
    {
        $d = Book::normalizeEntry(['flown_on' => '2026-10-20', 'duration' => '45 min', 'aircraft_other' => 'Pilatus PC-21', 'vehicle_kind' => 'plane', 'crew_role' => 'pirate'], new DateTimeImmutable('2026-10-26'));
        self::assertSame('Pilatus PC-21', $d['vehicle_label']);
        self::assertSame('Pilatus_PC_21', $d['vehicle_class']);
        self::assertSame('plane', $d['vehicle_kind']);
        self::assertNull($d['crew_role']);
    }

    public function testFutureFlightsAndAbsurdDurationsAreRefused(): void
    {
        $today = new DateTimeImmutable('2026-10-26');
        foreach ([
            ['flown_on' => '2026-10-27', 'duration' => '1:00', 'aircraft' => 'uh60'],
            ['flown_on' => '2026-10-20', 'duration' => '30:00', 'aircraft' => 'uh60'],
            ['flown_on' => '2026-10-20', 'duration' => '', 'aircraft' => 'uh60'],
            ['flown_on' => '2026-10-20', 'duration' => '1:00', 'aircraft' => '', 'aircraft_other' => ''],
        ] as $in) {
            try {
                Book::normalizeEntry($in, $today);
                self::fail('Saisie acceptée à tort : ' . json_encode($in));
            } catch (RuntimeException) {
                self::assertTrue(true);
            }
        }
    }

    public function testOnlyCountersignedEntriesAddHoursAndModFlightsAreNotCountedTwice(): void
    {
        $totals = ['kind:heli' => ['seconds' => 3600, 'last_flown_at' => '2026-10-01 10:00:00'], 'family:uh60' => ['seconds' => 3600, 'last_flown_at' => '2026-10-01 10:00:00']];
        $entries = [
            // Relevé en jeu, déjà dans atak_pilot_time : seules ses heures de nuit contresignées comptent en plus.
            ['source' => 'mod', 'status' => 'countersigned', 'vehicle_class' => 'RHS_UH60M', 'vehicle_label' => 'UH-60M', 'vehicle_kind' => 'heli', 'flown_on' => '2026-10-01', 'seconds' => 3600, 'night_seconds' => 1800, 'ifr_seconds' => 0, 'formation_seconds' => 0],
            ['source' => 'pilot', 'status' => 'countersigned', 'vehicle_class' => 'UH_60M', 'vehicle_label' => 'UH-60M', 'vehicle_kind' => 'heli', 'flown_on' => '2026-10-21', 'seconds' => 7200, 'night_seconds' => 0, 'ifr_seconds' => 9999, 'formation_seconds' => 0],
            ['source' => 'pilot', 'status' => 'declared', 'vehicle_class' => 'UH_60M', 'vehicle_label' => 'UH-60M', 'vehicle_kind' => 'heli', 'flown_on' => '2026-10-22', 'seconds' => 7200, 'night_seconds' => 7200, 'ifr_seconds' => 0, 'formation_seconds' => 0],
            ['source' => 'pilot', 'status' => 'rejected', 'vehicle_class' => 'UH_60M', 'vehicle_label' => 'UH-60M', 'vehicle_kind' => 'heli', 'flown_on' => '2026-10-23', 'seconds' => 7200, 'night_seconds' => 0, 'ifr_seconds' => 0, 'formation_seconds' => 0],
        ];
        $t = Eng::mergeLogbook($totals, $entries);
        self::assertSame(3 * 3600, $t['kind:heli']['seconds']);
        self::assertSame(3 * 3600, $t['family:uh60']['seconds']);
        self::assertSame('2026-10-21 00:00:00', $t['family:uh60']['last_flown_at']);
        self::assertSame(1800, $t['cond:night:heli']['seconds']);
        self::assertSame(7200, $t['cond:ifr:heli']['seconds']);
        self::assertArrayNotHasKey('cond:formation:heli', $t);
    }

    public function testConditionTiersNeedCountersignedConditionHours(): void
    {
        $rule = ['definition_id' => 9, 'tier' => 'night', 'scope' => 'kind:heli', 'track' => 'heli', 'min_scope_hours' => 2, 'min_track_hours' => 0, 'min_cond_hours' => 1, 'prereq_definition_id' => 0];
        $totals = ['kind:heli' => ['seconds' => 5 * 3600, 'last_flown_at' => '']];
        $r = Eng::evaluate($rule, $totals, []);
        self::assertSame(Eng::PROGRESS, $r['state']);
        self::assertSame('night', $r['cond']);
        self::assertSame(0, $r['pct']);

        $totals['cond:night:heli'] = ['seconds' => 3600, 'last_flown_at' => ''];
        self::assertSame(Eng::ELIGIBLE, Eng::evaluate($rule, $totals, [])['state']);
    }

    public function testSummaryCountsLikeTheBottomOfARealLogbook(): void
    {
        $entries = [
            ['source' => 'mod', 'status' => 'verified', 'vehicle_class' => 'RHS_UH60M', 'vehicle_label' => 'UH-60M', 'vehicle_kind' => 'heli', 'seconds' => 3600, 'night_seconds' => 0, 'ifr_seconds' => 0, 'formation_seconds' => 0, 'landings' => 0],
            ['source' => 'pilot', 'status' => 'countersigned', 'vehicle_class' => 'UH_60M', 'vehicle_label' => 'UH-60M', 'vehicle_kind' => 'heli', 'seconds' => 1800, 'night_seconds' => 1800, 'ifr_seconds' => 0, 'formation_seconds' => 0, 'landings' => 3],
            ['source' => 'pilot', 'status' => 'declared', 'vehicle_class' => 'UH_60M', 'vehicle_label' => 'UH-60M', 'vehicle_kind' => 'heli', 'seconds' => 7200, 'night_seconds' => 0, 'ifr_seconds' => 0, 'formation_seconds' => 0, 'landings' => 1],
        ];
        // atak_pilot_time : 3 h de Black Hawk dont 1 h déjà rangée vol par vol, 2 h relevées avant le carnet.
        $legacy = [['vehicle_class' => 'RHS_UH60M', 'vehicle_label' => 'UH-60M', 'vehicle_kind' => 'heli', 'seconds' => 3 * 3600]];
        $s = Book::summary($entries, $legacy);
        self::assertSame(2 * 3600, $s['carried']);
        self::assertSame(3600 + 1800 + 2 * 3600, $s['total']);
        self::assertSame(1800, $s['cond']['night']);
        self::assertSame(3, $s['landings']);
        self::assertSame(1, $s['pending']);
        self::assertSame(2, $s['flights']);
        self::assertSame($s['total'], $s['by_aircraft'][0]['seconds']);
    }

    public function testLogbookIsWiredInRoutesContainerMigrationAndSidebar(): void
    {
        $routes = (string) file_get_contents(self::ROOT . '/routes/web.php');
        self::assertStringContainsString("'/back-office/ma-situation/carnet-de-vol'", $routes);
        self::assertStringContainsString('cursus-pilote/carnet/vols/{entryId}', $routes);
        $container = (string) file_get_contents(self::ROOT . '/app/Core/Container.php');
        self::assertStringContainsString('PilotLogbookController::class', $container);
        self::assertStringContainsString('PilotLogbookService::class', $container);
        $registry = (string) file_get_contents(self::ROOT . '/database/steps/registry.php');
        self::assertStringContainsString('pilot_logbook', $registry);
        self::assertFileExists(self::ROOT . '/database/steps/3110_pilot_logbook.php');
        $migration = (string) file_get_contents(self::ROOT . '/bootstrap/pilot_logbook_migration.php');
        self::assertStringContainsString('pilot_logbook_entries', $migration);
        self::assertStringContainsString('min_cond_hours', $migration);
        self::assertStringContainsString('ma-situation/carnet-de-vol', (string) file_get_contents(self::ROOT . '/views/partials/ath_sidebar_nav.php'));
        // Le relevé en jeu range chaque vol au carnet.
        self::assertStringContainsString('recordModFlights', (string) file_get_contents(self::ROOT . '/app/Repositories/AtakDistanceRepository.php'));
    }
}
