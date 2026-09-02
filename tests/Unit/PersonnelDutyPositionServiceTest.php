<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\PersonnelDutyPositionService;
use PHPUnit\Framework\TestCase;

final class PersonnelDutyPositionServiceTest extends TestCase
{
    public function testJoinAlwaysStartsInTrainingUnlessAlreadyActive(): void
    {
        self::assertSame(
            PersonnelDutyPositionService::SLUG_TRAINING,
            PersonnelDutyPositionService::decideSlug(null, true, false, false)
        );
        self::assertSame(
            PersonnelDutyPositionService::SLUG_TRAINING,
            PersonnelDutyPositionService::decideSlug(
                PersonnelDutyPositionService::SLUG_TRAINING,
                true,
                false,
                false
            )
        );
        self::assertSame(
            PersonnelDutyPositionService::SLUG_ACTIVE,
            PersonnelDutyPositionService::decideSlug(
                PersonnelDutyPositionService::SLUG_ACTIVE,
                true,
                false,
                false
            )
        );
    }

    public function testForceActiveAndCompletedIntegrationPromoteToActiveDuty(): void
    {
        self::assertSame(
            PersonnelDutyPositionService::SLUG_ACTIVE,
            PersonnelDutyPositionService::decideSlug(null, false, true, false)
        );
        self::assertSame(
            PersonnelDutyPositionService::SLUG_ACTIVE,
            PersonnelDutyPositionService::decideSlug(
                PersonnelDutyPositionService::SLUG_TRAINING,
                false,
                true,
                true
            )
        );
        self::assertSame(
            PersonnelDutyPositionService::SLUG_ACTIVE,
            PersonnelDutyPositionService::decideSlug(
                PersonnelDutyPositionService::SLUG_ACTIVE,
                false,
                false,
                true
            )
        );
    }

    public function testBackfillKeepsTrainingWhenIntegrationIsOpen(): void
    {
        self::assertSame(
            PersonnelDutyPositionService::SLUG_TRAINING,
            PersonnelDutyPositionService::decideSlug(null, false, false, true)
        );
        self::assertSame(
            PersonnelDutyPositionService::SLUG_ACTIVE,
            PersonnelDutyPositionService::decideSlug(null, false, false, false)
        );
    }

    public function testLabelsAreOrganizerFacing(): void
    {
        self::assertSame('En formation', PersonnelDutyPositionService::labelForSlug(PersonnelDutyPositionService::SLUG_TRAINING));
        self::assertSame('En service actif', PersonnelDutyPositionService::labelForSlug(PersonnelDutyPositionService::SLUG_ACTIVE));
    }
}
