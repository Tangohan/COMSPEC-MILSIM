<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\UnitMilitarySymbol;
use PHPUnit\Framework\TestCase;

/** Symbole MIL-STD-2525 des unités de l’ORBAT : validation, valeurs déduites et marque d’échelon. */
final class UnitMilitarySymbolTest extends TestCase
{
    public function testValidSidcIsNormalizedAndEmptyMeansAutomatic(): void
    {
        self::assertSame('SFGPUCI----D---', UnitMilitarySymbol::normalizeSidc(' sfgpuci----d--- '));
        self::assertSame('10031000141211000000', UnitMilitarySymbol::normalizeSidc('10031000141211000000'));
        self::assertNull(UnitMilitarySymbol::normalizeSidc(''));
        self::assertNull(UnitMilitarySymbol::normalizeSidc(null));
    }

    /** @return list<array{0: string}> */
    public static function invalidSidcs(): array
    {
        return [['SFGPUCI'], ['<script>alert(1)</script>'], ['XFGPUCI--------'], ['SFGPUCI---------'], ['1234']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidSidcs')]
    public function testInvalidSidcIsRefused(string $raw): void
    {
        $this->expectException(\InvalidArgumentException::class);
        UnitMilitarySymbol::normalizeSidc($raw);
    }

    public function testEchelonMustBeKnown(): void
    {
        self::assertSame('peloton', UnitMilitarySymbol::normalizeEchelon('Peloton'));
        self::assertNull(UnitMilitarySymbol::normalizeEchelon(''));
        $this->expectException(\InvalidArgumentException::class);
        UnitMilitarySymbol::normalizeEchelon('escouade_géante');
    }

    public function testEchelonMarkIsAppliedToBothSidcFormats(): void
    {
        self::assertSame('SFGPUCI----A---', UnitMilitarySymbol::compose('SFGPUCI--------', 'equipe'));
        self::assertSame('SFGPUCI---AE---', UnitMilitarySymbol::compose('SFGPUCI---A----', 'compagnie'));
        self::assertSame('10031000141211000000', UnitMilitarySymbol::compose('10031000001211000000', 'section'));
        self::assertSame('SFGPUCI--------', UnitMilitarySymbol::compose('SFGPUCI--------', null));
    }

    public function testDefaultsAreDeducedFromNameAndStructureType(): void
    {
        $team = UnitMilitarySymbol::resolve(['name' => 'Gold Team — Pararescue', 'type' => 'team']);
        self::assertSame('medical', $team['functionKey']);
        self::assertSame('equipe', $team['echelon']);
        self::assertSame('SFGPUSM----A---', $team['symbol']);
        self::assertTrue($team['sidcAuto']);
        self::assertTrue($team['echelonAuto']);

        $hq = UnitMilitarySymbol::resolve(['name' => 'A Troop — HQ', 'type' => 'section']);
        self::assertSame('escadron', $hq['echelon']);
        self::assertSame('A', $hq['sidc'][10], 'Le QG porte la hampe de poste de commandement.');

        $plain = UnitMilitarySymbol::resolve(['name' => 'Bravo', 'type' => 'squad']);
        self::assertSame('infantry', $plain['functionKey']);
        self::assertSame('groupe', $plain['echelon']);

        self::assertSame('sof', UnitMilitarySymbol::resolve(['name' => 'Joint Special Operations Command'])['functionKey']);
        self::assertSame('aviation_rotary', UnitMilitarySymbol::resolve(['name' => 'Escadrille d’hélicoptères'])['functionKey']);
    }

    public function testStoredValuesWinOverDeduction(): void
    {
        $r = UnitMilitarySymbol::resolve(['name' => 'Alpha Team', 'type' => 'team', 'sidc' => 'SFGPUCA--------', 'echelon' => 'compagnie']);
        self::assertSame('armor', $r['functionKey']);
        self::assertSame('SFGPUCA----E---', $r['symbol']);
        self::assertFalse($r['sidcAuto']);
        self::assertFalse($r['echelonAuto']);
        // Une valeur héritée invalide en base ne casse pas l’affichage : on retombe sur la déduction.
        self::assertTrue(UnitMilitarySymbol::resolve(['name' => 'X', 'sidc' => 'n’importe quoi'])['sidcAuto']);
    }

    public function testOptionsAreLocalized(): void
    {
        $fr = UnitMilitarySymbol::options('fr');
        $en = UnitMilitarySymbol::options('en');
        self::assertSame('Peloton', array_column($fr['echelons'], 'label', 'id')['peloton']);
        self::assertSame('Platoon (cavalry)', array_column($en['echelons'], 'label', 'id')['peloton']);
        self::assertSame('SFGPUCI--------', array_column($fr['functions'], 'sidc', 'id')['infantry']);
        foreach ($fr['functions'] as $f) {
            self::assertTrue(UnitMilitarySymbol::isValidSidc($f['sidc']), $f['id']);
        }
    }
}
