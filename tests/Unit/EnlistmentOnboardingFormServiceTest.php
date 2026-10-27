<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Recruitment\EnlistmentOnboardingFormService;
use PHPUnit\Framework\TestCase;

final class EnlistmentOnboardingFormServiceTest extends TestCase
{
    public function testServicePositionsAreNotOfferedAsAccessLevels(): void
    {
        $rows = EnlistmentOnboardingFormService::filterAccessRoles([
            ['id' => 1, 'slug' => 'member', 'name' => 'Membre'],
            ['id' => 2, 'slug' => 'status_in_training', 'name' => 'En formation'],
            ['id' => 3, 'slug' => 'status_active_duty', 'name' => 'En service actif'],
            ['id' => 4, 'slug' => 'access-custom-instructeur', 'name' => 'Instructeur'],
        ]);

        self::assertSame([1, 4], array_map(static fn (array $r): int => (int) $r['id'], $rows));
    }

    public function testAccessLevelDefaultsToCurrentThenMember(): void
    {
        $roles = [
            ['id' => 5, 'slug' => 'community_owner'],
            ['id' => 6, 'slug' => 'hr'],
            ['id' => 7, 'slug' => 'member'],
        ];

        self::assertSame(6, EnlistmentOnboardingFormService::pickAccessRoleId($roles, [6, 99]));
        self::assertSame(7, EnlistmentOnboardingFormService::pickAccessRoleId($roles, []));
        self::assertSame(0, EnlistmentOnboardingFormService::pickAccessRoleId([], []));
    }

    public function testUnitsFollowTheOrgChartWithDepth(): void
    {
        $out = EnlistmentOnboardingFormService::orderUnits([
            ['id' => 11, 'name' => 'TRP A', 'parent_id' => 10],
            ['id' => 10, 'name' => 'B SQN', 'parent_id' => null],
            ['id' => 13, 'name' => 'HQ', 'code' => 'HQ', 'parent_id' => 0],
            ['id' => 12, 'name' => 'Section 1', 'parent_id' => 11],
        ]);

        self::assertSame([10, 11, 12, 13], array_column($out, 'id'));
        self::assertSame([0, 1, 2, 0], array_column($out, 'depth'));
        self::assertSame('HQ (HQ)', $out[3]['label']);
    }

    public function testUnitLoopsStayListed(): void
    {
        $out = EnlistmentOnboardingFormService::orderUnits([
            ['id' => 1, 'name' => 'A', 'parent_id' => 2],
            ['id' => 2, 'name' => 'B', 'parent_id' => 1],
        ]);

        self::assertCount(2, $out);
    }

    public function testDateNormalisation(): void
    {
        self::assertSame('2026-10-25', EnlistmentOnboardingFormService::ymd('2026-10-25 21:04:00'));
        self::assertNull(EnlistmentOnboardingFormService::ymd('0000-00-00'));
        self::assertNull(EnlistmentOnboardingFormService::ymd('pas une date'));
        self::assertNull(EnlistmentOnboardingFormService::ymd(''));
    }

    public function testCivilChoicesComeFromTheDossierCatalog(): void
    {
        $opts = EnlistmentOnboardingFormService::civilOptions();

        self::assertContains('O+', $opts['blood_type']);
        self::assertContains('Femme', $opts['sex']);
    }
}
