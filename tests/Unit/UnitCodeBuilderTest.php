<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\UnitCodeBuilder;
use PHPUnit\Framework\TestCase;

final class UnitCodeBuilderTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function soar(): array
    {
        return [
            ['id' => 1, 'parent_id' => null, 'name' => 'Escadron B', 'code' => null, 'display_order' => 0],
            ['id' => 2, 'parent_id' => 1, 'name' => 'Troop A', 'code' => null, 'display_order' => 0],
            ['id' => 3, 'parent_id' => 2, 'name' => 'Groupe 1', 'code' => null, 'display_order' => 0],
            ['id' => 4, 'parent_id' => 2, 'name' => 'Groupe 2', 'code' => null, 'display_order' => 1],
            ['id' => 5, 'parent_id' => null, 'name' => 'État-major', 'code' => null, 'display_order' => 1],
        ];
    }

    public function testHierarchicalCodesFollowTheTree(): void
    {
        $codes = UnitCodeBuilder::build($this->soar(), 'SOAR');

        self::assertSame('SOAR-B', $codes[1]['path']);
        self::assertSame('SOAR-B-A', $codes[2]['path']);
        self::assertSame('SOAR-B-A-1', $codes[3]['path']);
        self::assertSame('SOAR-B-A-2', $codes[4]['path']);
        self::assertSame('SOAR-EM', $codes[5]['path']);
        self::assertSame('1', $codes[3]['segment']);
        self::assertSame(2, $codes[3]['depth']);
    }

    public function testAutomaticSegments(): void
    {
        self::assertSame('B', UnitCodeBuilder::autoSegment('Escadron B'));
        self::assertSame('A', UnitCodeBuilder::autoSegment('Troop A'));
        self::assertSame('2', UnitCodeBuilder::autoSegment('2nd Platoon'));
        self::assertSame('1', UnitCodeBuilder::autoSegment('1er Escadron'));
        self::assertSame('C', UnitCodeBuilder::autoSegment('Charlie Team'));
        self::assertSame('ST', UnitCodeBuilder::autoSegment('Special Tactics Squadron'));
        self::assertSame('HQ', UnitCodeBuilder::autoSegment('HQ'));
        self::assertSame('EM', UnitCodeBuilder::autoSegment('État-major'));
        self::assertSame('LOG', UnitCodeBuilder::autoSegment('Logistique'));
        self::assertSame('ESC', UnitCodeBuilder::autoSegment('Escadron'));
        // Le nom du parent répété en tête n'est pas repris.
        self::assertSame('GOL', UnitCodeBuilder::autoSegment('24th STS Gold Team', '24th STS'));
    }

    public function testCodesAreUniqueWithinTheCommunity(): void
    {
        $units = [
            ['id' => 10, 'parent_id' => null, 'name' => 'Alpha', 'code' => null],
            ['id' => 11, 'parent_id' => null, 'name' => 'Alpha', 'code' => null],
            ['id' => 12, 'parent_id' => null, 'name' => 'Assaut', 'code' => 'A'],
        ];
        $codes = UnitCodeBuilder::build($units, 'TF');
        $paths = array_column($codes, 'path');

        self::assertCount(3, array_unique($paths));
        // Le repère saisi passe avant le repère déduit.
        self::assertSame('TF-A', $codes[12]['path']);
        self::assertSame(['TF-A2', 'TF-A3'], [$codes[10]['path'], $codes[11]['path']]);
    }

    public function testCodeIsRecomputedWhenTheParentChanges(): void
    {
        $units = $this->soar();
        $before = UnitCodeBuilder::build($units, 'SOAR');
        $units[2]['parent_id'] = 5; // Groupe 1 passe sous l'état-major.
        $after = UnitCodeBuilder::build($units, 'SOAR');

        self::assertSame('SOAR-B-A-1', $before[3]['path']);
        self::assertSame('SOAR-EM-1', $after[3]['path']);
        self::assertSame($before[4]['path'], $after[4]['path']);
    }

    public function testRootNamedLikeTheCommunityCarriesTheCommunityCode(): void
    {
        $units = [
            ['id' => 1, 'parent_id' => null, 'name' => '160th SOAR', 'code' => null],
            ['id' => 2, 'parent_id' => 1, 'name' => 'B Squadron', 'code' => null],
        ];
        $codes = UnitCodeBuilder::build($units, 'SOAR', '160th SOAR');

        self::assertSame('SOAR', $codes[1]['path']);
        self::assertSame('SOAR-B', $codes[2]['path']);
    }

    public function testParentLoopDoesNotHangAndStaysUnique(): void
    {
        $units = [
            ['id' => 1, 'parent_id' => 2, 'name' => 'Alpha', 'code' => null],
            ['id' => 2, 'parent_id' => 1, 'name' => 'Bravo', 'code' => null],
        ];
        $codes = UnitCodeBuilder::build($units, 'X1');

        self::assertCount(2, $codes);
        self::assertNotSame($codes[1]['path'], $codes[2]['path']);
    }

    public function testCommunityCodeAndAdminIdentifiers(): void
    {
        self::assertSame('SOAR', UnitCodeBuilder::communityCodeFromName('160th SOAR - The Special Operations Action Regiments'));
        self::assertSame('TFR', UnitCodeBuilder::communityCodeFromName('Task Force Ranger'));
        self::assertSame('SOAR2', UnitCodeBuilder::uniqueVariant('SOAR', static fn (string $c): bool => $c === 'SOAR'));
        self::assertNull(UnitCodeBuilder::normalizeInput('SO AR', 2, 10));
        self::assertSame('SOAR', UnitCodeBuilder::normalizeInput(' soar ', 2, 10));

        self::assertSame('0042', UnitCodeBuilder::formatTenantNumber(42));
        self::assertSame('0042-0137', UnitCodeBuilder::formatUnitAdminId(42, 137));
        self::assertSame([42, 137], UnitCodeBuilder::parseAdminQuery('0042-0137'));
        self::assertSame([42, 137], UnitCodeBuilder::parseAdminQuery('00420137'));
        self::assertSame([null, 137], UnitCodeBuilder::parseAdminQuery('137'));
        self::assertNull(UnitCodeBuilder::parseAdminQuery('SOAR-B'));
    }
}
