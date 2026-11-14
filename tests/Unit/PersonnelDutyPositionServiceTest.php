<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\PersonnelDutyPositionService;
use App\Support\AdministrativePositionCatalog;
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

    public function testManagerPositionsAreNeverOverwrittenAutomatically(): void
    {
        foreach ([PersonnelDutyPositionService::SLUG_INACTIVE, PersonnelDutyPositionService::SLUG_REINFORCEMENT] as $slug) {
            self::assertSame($slug, PersonnelDutyPositionService::decideSlug($slug, true, false, false));
            self::assertSame($slug, PersonnelDutyPositionService::decideSlug($slug, false, false, true));
            self::assertSame($slug, PersonnelDutyPositionService::decideSlug($slug, false, false, false));
        }
        self::assertSame(
            PersonnelDutyPositionService::SLUG_TRAINING,
            PersonnelDutyPositionService::decideSlug('status_unknown', true, false, false)
        );
    }

    public function testCatalogListsFourPositionsWithExplanations(): void
    {
        $slugs = PersonnelDutyPositionService::allSlugs();
        self::assertCount(4, $slugs);
        self::assertSame(PersonnelDutyPositionService::SLUG_ACTIVE, $slugs[0], 'Active duty wins if a member holds two positions.');
        $labels = [];
        foreach (AdministrativePositionCatalog::all() as $row) {
            self::assertContains($row['slug'], $slugs);
            self::assertNotSame('', $row['summary']);
            self::assertNotSame('', $row['detail']);
            $labels[] = $row['label'];
        }
        self::assertSame(['En formation', 'En service actif', 'En service non actif', 'En service statutaire de renforcement'], $labels);
        self::assertSame('En service non actif', PersonnelDutyPositionService::labelForSlug(PersonnelDutyPositionService::SLUG_INACTIVE));
        self::assertSame(PersonnelDutyPositionService::SLUG_REINFORCEMENT, AdministrativePositionCatalog::findByLabel(' en service statutaire de renforcement ')['slug'] ?? null);
        self::assertSame('', PersonnelDutyPositionService::labelForSlug('status_unknown'));
        self::assertSame(['mission', 'service'], array_column(AdministrativePositionCatalog::dutySituations(), 'key'));
    }

    public function testEveryPositionTextHasAnEnglishTranslation(): void
    {
        $dict = require dirname(__DIR__, 2) . '/lang/en/site_auto.php';
        self::assertIsArray($dict);
        foreach (AdministrativePositionCatalog::all() as $row) {
            foreach (['label', 'summary', 'detail', 'mission', 'service', 'strength'] as $key) {
                self::assertArrayHasKey($row[$key], $dict, $row['slug'] . ' ' . $key);
            }
        }
        foreach (AdministrativePositionCatalog::dutySituations() as $row) {
            foreach (['label', 'summary', 'examples'] as $key) {
                self::assertArrayHasKey($row[$key], $dict, $row['key'] . ' ' . $key);
            }
        }
    }

    public function testMandatoryTrainingDurationIsComputedInWholeDays(): void
    {
        $start = 1_700_000_000;
        self::assertSame(14, PersonnelDutyPositionService::remainingDaysFromStart(14, $start, $start));
        self::assertSame(8, PersonnelDutyPositionService::remainingDaysFromStart(14, $start, $start + 6 * 86400));
        self::assertSame(0, PersonnelDutyPositionService::remainingDaysFromStart(14, $start, $start + 20 * 86400));
    }
}
