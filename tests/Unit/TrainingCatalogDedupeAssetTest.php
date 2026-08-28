<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\TrainingCourseRepository;
use PHPUnit\Framework\TestCase;

final class TrainingCatalogDedupeAssetTest extends TestCase
{
    public function testCommunityCopyHidesSameSlugOnTheWholePlatform(): void
    {
        $rows = [
            [
                'id' => 10,
                'tenant_id' => 1,
                'slug' => 'parcours-portail',
                'title' => 'Parcours portail',
                'lms_scope' => TrainingCourseRepository::LMS_SCOPE_PLATFORM,
            ],
            [
                'id' => 22,
                'tenant_id' => 5,
                'slug' => 'parcours-portail',
                'title' => 'Parcours portail',
                'lms_scope' => TrainingCourseRepository::LMS_SCOPE_TENANT,
            ],
            [
                'id' => 30,
                'tenant_id' => 1,
                'slug' => 'installer-task-force-radio',
                'title' => 'Installer Task Force Radio',
                'lms_scope' => TrainingCourseRepository::LMS_SCOPE_PLATFORM,
            ],
        ];

        $out = TrainingCourseRepository::collapseDuplicateCatalogRows($rows, 5);
        self::assertCount(2, $out);
        self::assertSame(22, (int) $out[0]['id']);
        self::assertSame(30, (int) $out[1]['id']);
    }

    public function testPlatformCopyKeptWhenCommunityHasNoTwin(): void
    {
        $rows = [
            [
                'id' => 10,
                'tenant_id' => 1,
                'slug' => 'parcours-portail',
                'title' => 'Parcours portail',
                'lms_scope' => TrainingCourseRepository::LMS_SCOPE_PLATFORM,
            ],
        ];

        $out = TrainingCourseRepository::collapseDuplicateCatalogRows($rows, 5);
        self::assertCount(1, $out);
        self::assertSame(10, (int) $out[0]['id']);
    }

    public function testDashboardQueryExcludesPlatformTwinWhenCommunityCopyExists(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/TrainingCourseRepository.php');
        self::assertStringContainsString('collapseDuplicateCatalogRows', $src);
        self::assertStringContainsString('NOT EXISTS', $src);
        self::assertStringContainsString("t.slug = c.slug", $src);

        $service = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Training/TrainingService.php');
        self::assertStringContainsString('collapseDuplicateCatalogRows', $service);
    }
}
