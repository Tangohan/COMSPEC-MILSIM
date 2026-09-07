<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Effectifs\PersonnelCommandChainService;
use PHPUnit\Framework\TestCase;

final class PersonnelCommandChainServiceTest extends TestCase
{
    public function testMemberReportsToUnitCommander(): void
    {
        $units = [
            1 => ['id' => 1, 'parent_id' => 0, 'commander_user_id' => 10, 'name' => 'Bravo'],
        ];
        $primary = [20 => 1];
        $commanded = PersonnelCommandChainService::commandedUnitIdsByUser($units);

        self::assertSame(10, PersonnelCommandChainService::resolveImmediateSuperiorId(20, $units, $primary, $commanded));
        self::assertSame([10], PersonnelCommandChainService::resolveChainIds(20, $units, $primary, $commanded));
    }

    public function testCommanderReportsToParentCommander(): void
    {
        $units = [
            1 => ['id' => 1, 'parent_id' => 0, 'commander_user_id' => 10, 'name' => 'Compagnie'],
            2 => ['id' => 2, 'parent_id' => 1, 'commander_user_id' => 20, 'name' => 'Groupe'],
        ];
        $primary = [20 => 2, 30 => 2];
        $commanded = PersonnelCommandChainService::commandedUnitIdsByUser($units);

        self::assertSame(20, PersonnelCommandChainService::resolveImmediateSuperiorId(30, $units, $primary, $commanded));
        self::assertSame(10, PersonnelCommandChainService::resolveImmediateSuperiorId(20, $units, $primary, $commanded));
        self::assertSame([20, 10], PersonnelCommandChainService::resolveChainIds(30, $units, $primary, $commanded));
        self::assertSame([10], PersonnelCommandChainService::resolveChainIds(20, $units, $primary, $commanded));
        self::assertSame([], PersonnelCommandChainService::resolveChainIds(10, $units, [10 => 1], $commanded));
    }

    public function testSamePersonCommandingTwoLevelsWalksAboveBoth(): void
    {
        $units = [
            1 => ['id' => 1, 'parent_id' => 0, 'commander_user_id' => 10, 'name' => 'Compagnie'],
            2 => ['id' => 2, 'parent_id' => 1, 'commander_user_id' => 10, 'name' => 'Groupe'],
        ];
        $primary = [10 => 2];
        $commanded = PersonnelCommandChainService::commandedUnitIdsByUser($units);

        self::assertSame([], PersonnelCommandChainService::resolveChainIds(10, $units, $primary, $commanded));
    }

    public function testCommanderPrimaryInSubUnitStillReportsToParentChef(): void
    {
        $units = [
            1 => ['id' => 1, 'parent_id' => 0, 'commander_user_id' => 10, 'name' => 'Compagnie'],
            2 => ['id' => 2, 'parent_id' => 1, 'commander_user_id' => 20, 'name' => 'Groupe'],
        ];
        $primary = [20 => 2];
        $commanded = PersonnelCommandChainService::commandedUnitIdsByUser($units);

        self::assertSame(10, PersonnelCommandChainService::resolveImmediateSuperiorId(20, $units, $primary, $commanded));
    }

    public function testParentCycleDoesNotLoop(): void
    {
        $units = [
            1 => ['id' => 1, 'parent_id' => 2, 'commander_user_id' => 10, 'name' => 'A'],
            2 => ['id' => 2, 'parent_id' => 1, 'commander_user_id' => 20, 'name' => 'B'],
        ];
        $primary = [30 => 1];
        $commanded = PersonnelCommandChainService::commandedUnitIdsByUser($units);
        $chain = PersonnelCommandChainService::resolveChainIds(30, $units, $primary, $commanded);

        self::assertSame([10, 20], $chain);
    }

    public function testNoUnitHasNoSuperior(): void
    {
        $units = [
            1 => ['id' => 1, 'parent_id' => 0, 'commander_user_id' => 10, 'name' => 'Bravo'],
        ];
        $commanded = PersonnelCommandChainService::commandedUnitIdsByUser($units);

        self::assertNull(PersonnelCommandChainService::resolveImmediateSuperiorId(99, $units, [], $commanded));
        self::assertSame([], PersonnelCommandChainService::resolveChainIds(99, $units, [], $commanded));
    }

    public function testLabelFromUserRowPrefersDisplayName(): void
    {
        self::assertSame('Dupont', PersonnelCommandChainService::labelFromUserRow([
            'display_name' => 'Dupont',
            'callsign' => 'Wolf',
            'email' => 'a@b.c',
        ]));
        self::assertSame('Wolf', PersonnelCommandChainService::labelFromUserRow([
            'display_name' => '',
            'callsign' => 'Wolf',
            'email' => 'a@b.c',
        ]));
    }
}
