<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\UnitAbbreviation;
use PHPUnit\Framework\TestCase;

final class UnitAbbreviationTest extends TestCase
{
    protected function setUp(): void
    {
        // Pas de base en test : aucune saisie manuelle sauf celles posées par le test.
        UnitAbbreviation::primeOverrides(9001, []);
    }

    protected function tearDown(): void
    {
        UnitAbbreviation::primeOverrides(9001, null);
    }

    public function testLongNamesBecomeMilitaryAcronyms(): void
    {
        self::assertSame('JSOC', UnitAbbreviation::auto('Joint Special Operations Command'));
        self::assertSame('AFSOC', UnitAbbreviation::auto('Air Force Special Operations Command'));
        self::assertSame('24th STS Gold Team', UnitAbbreviation::auto('24th Special Tactics Squadron - Gold Team'));
        self::assertSame('1er REP', UnitAbbreviation::auto('1er Régiment Étranger de Parachutistes'));
        self::assertSame('13e RDP', UnitAbbreviation::auto('13e Régiment de Dragons Parachutistes'));
        self::assertSame('COS', UnitAbbreviation::auto('Commandement des Opérations Spéciales'));
        self::assertSame('2e RIM', UnitAbbreviation::auto("2e Régiment d'Infanterie de Marine"));
        self::assertSame('SOAR', UnitAbbreviation::auto('SOAR - The Special Operations Action Regiments'));
    }

    public function testShortNamesAreLeftAlone(): void
    {
        self::assertSame('Section Alpha', UnitAbbreviation::standalone('Section Alpha', 9001));
        self::assertSame('', UnitAbbreviation::standalone('   ', 9001));
    }

    public function testTrailDropsTheRepeatedParentName(): void
    {
        $trail = UnitAbbreviation::trail(
            'Joint Special Operations Command / Air Force Special Operations Command / 24th Special Tactics Squadron - Gold Team / 24th STS Gold Team SOF TACP',
            9001
        );

        self::assertSame(['JSOC', 'AFSOC', '24th STS Gold Team', 'SOF TACP'], array_column($trail, 'short'));
        self::assertSame('24th STS Gold Team SOF TACP', $trail[3]['full']);
    }

    public function testRoleNamedAfterItsUnitTakesTheUnitShortName(): void
    {
        $ancestors = ['Joint Special Operations Command', 'Air Force Special Operations Command', '24th Special Tactics Squadron - Gold Team'];

        self::assertSame('SOF TACP', UnitAbbreviation::role('24th STS Gold Team SOF TACP', '24th STS Gold Team SOF TACP', $ancestors, 9001));
        self::assertSame('Team Leader', UnitAbbreviation::role('24th STS Gold Team SOF TACP - Team Leader', '24th STS Gold Team SOF TACP', $ancestors, 9001));
        self::assertSame("Chef d'équipe", UnitAbbreviation::role("Chef d'équipe", '24th STS Gold Team SOF TACP', $ancestors, 9001));
    }

    public function testManualShortNameWinsEverywhere(): void
    {
        UnitAbbreviation::primeOverrides(9001, ['24th Special Tactics Squadron - Gold Team' => 'GOLD']);

        self::assertSame('GOLD', UnitAbbreviation::standalone('24th special tactics squadron gold team', 9001));
        self::assertSame(
            ['JSOC', 'GOLD'],
            array_column(UnitAbbreviation::trail(['Joint Special Operations Command', '24th Special Tactics Squadron - Gold Team'], 9001), 'short')
        );
    }

    public function testHtmlKeepsTheFullNameAsTooltip(): void
    {
        self::assertSame(
            '<abbr class="ath-abbr" title="Joint Special Operations Command">JSOC</abbr>',
            UnitAbbreviation::html('Joint Special Operations Command', 'JSOC')
        );
        self::assertSame('A &amp; B', UnitAbbreviation::html('A & B', 'A & B'));
    }

    public function testAtakUsesTheSameRules(): void
    {
        $main = dirname(__DIR__, 2) . '/mod/COMSPEC_ATAK_Native/Sources/addons/main';
        self::assertStringContainsString('class abbrev {}', (string) file_get_contents($main . '/config.cpp'));
        self::assertStringContainsString('comspec_atak_native_fnc_abbrev', (string) file_get_contents($main . '/functions/map/fn_unitGroup.sqf'));
        $sqf = (string) file_get_contents($main . '/functions/ui/fn_abbrev.sqf');
        foreach (['"of"', '"the"', '"de"', '"des"', '<= 14', '["_max", 24'] as $needle) {
            self::assertStringContainsString($needle, $sqf);
        }
    }
}
