<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Cron\CronSchedule;
use App\Services\Platform\AthenaHeritageNarration;
use App\Services\Platform\AthenaHeritageService;
use App\Support\AthenaHeritageMajorVersions;
use PHPUnit\Framework\TestCase;

final class AthenaHeritageAssetTest extends TestCase
{
    public function testMajorVersionHelpers(): void
    {
        self::assertTrue(AthenaHeritageMajorVersions::isMajorStep('1.0.0'));
        self::assertTrue(AthenaHeritageMajorVersions::isMajorStep('2.0.0'));
        self::assertFalse(AthenaHeritageMajorVersions::isMajorStep('1.6.15'));
        self::assertFalse(AthenaHeritageMajorVersions::isMajorStep('2026.08d'));
        self::assertSame('1.0.0', AthenaHeritageMajorVersions::normalizeMajor('1.6.15'));
        self::assertSame(['1.0.0'], AthenaHeritageMajorVersions::reachedMajors('1.6.15'));
        self::assertSame(['1.0.0', '2.0.0'], AthenaHeritageMajorVersions::reachedMajors('2.1.0'));
    }

    public function testNarrationUsesVerifiedPlaceholders(): void
    {
        $text = AthenaHeritageNarration::render('site.hist_narr_players', ['value' => '250']);
        self::assertStringContainsString('250', $text);
        self::assertStringContainsString('ATHENA', $text);
        self::assertStringNotContainsString('{value}', $text);
        self::assertStringNotContainsString(':value', $text);
    }

    public function testNextGoalPicksFirstUnreachedPlayerThreshold(): void
    {
        $repo = $this->createMock(\App\Repositories\AthenaHeritageRepository::class);
        $service = new AthenaHeritageService($repo);
        $goal = $service->computeNextGoal([
            'operators_total' => 186,
            'distance_km' => 10,
            'playtime_hours' => 100,
        ]);
        self::assertNotNull($goal);
        self::assertSame('operators', $goal['metric']);
        self::assertSame(250, $goal['target']);
        self::assertSame(186, $goal['current']);
        self::assertSame(64, $goal['remaining']);
        self::assertGreaterThan(70.0, $goal['progress_pct']);
        self::assertLessThan(75.0, $goal['progress_pct']);
    }

    public function testCronWiringAndPublicAssets(): void
    {
        self::assertSame(1440, CronSchedule::intervalMinutes('athena_heritage_snapshot'));

        $root = dirname(__DIR__, 2);
        $migration = (string) file_get_contents($root . '/migrations/20261027220000_athena_heritage_timeline.sql');
        self::assertStringContainsString('athena_heritage_snapshots', $migration);
        self::assertStringContainsString('athena_heritage_milestones', $migration);
        self::assertStringContainsString('athena_heritage_version_points', $migration);
        self::assertStringContainsString('UNIQUE KEY uq_athena_heritage_milestones_key', $migration);

        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("/notre-histoire", $routes);
        self::assertStringContainsString("'history'", $routes);

        $container = (string) file_get_contents($root . '/app/Core/Container.php');
        self::assertStringContainsString('AthenaHeritageSnapshotCronJob', $container);
        self::assertStringContainsString('AthenaHeritageService', $container);

        $controller = (string) file_get_contents($root . '/app/Controllers/Web/SitePagesController.php');
        self::assertStringContainsString('function history', $controller);
        self::assertStringContainsString('site.hist_meta_title', $controller);
        self::assertStringContainsString("'history'", $controller);

        self::assertFileExists($root . '/views/site/history.php');
        self::assertFileExists($root . '/public/assets/css/site-history.css');
        self::assertFileExists($root . '/public/assets/js/site-history.js');
        self::assertFileExists($root . '/app/Services/Cron/Jobs/AthenaHeritageSnapshotCronJob.php');

        $view = (string) file_get_contents($root . '/views/site/history.php');
        self::assertStringContainsString('site-history-hero', $view);
        self::assertStringContainsString('site-history-hero__facts', $view);
        self::assertStringContainsString('site-sse-block', $view);
        self::assertStringContainsString('hi-display', $view);
        self::assertStringContainsString('hi-cta hi-cta-solid', $view);
        self::assertStringContainsString('fog-team.jpg', $view);
        // PHP caste '365'/'90' en int : rawurlencode exige string (sinon 500).
        self::assertStringContainsString('$periodKey = (string) $key', $view);
        self::assertStringContainsString('rawurlencode($periodKey)', $view);

        $css = (string) file_get_contents($root . '/public/assets/css/site-history.css');
        self::assertStringContainsString('.site-history-hero', $css);
        self::assertStringContainsString('site-history-hero__scrim', $css);
        self::assertStringContainsString('site-history__node-excerpt', $css);

        $js = (string) file_get_contents($root . '/public/assets/js/site-history.js');
        self::assertStringContainsString('Points visibles', $js);
        self::assertStringContainsString('ctx.arc', $js);

        $repoSrc = (string) file_get_contents($root . '/app/Repositories/AthenaHeritageRepository.php');
        self::assertStringContainsString('function ensureSchema', $repoSrc);
        self::assertStringContainsString('function reconstructGrowthSeries', $repoSrc);
        self::assertStringContainsString('function backfillSnapshotsFromGrowth', $repoSrc);

        $serviceSrc = (string) file_get_contents($root . '/app/Services/Platform/AthenaHeritageService.php');
        self::assertStringContainsString('virtualMilestonesFromLive', $serviceSrc);
        self::assertStringContainsString('reconstructGrowthSeries', $serviceSrc);
        self::assertStringContainsString('ensureSchema', $serviceSrc);

        $fr = (string) file_get_contents($root . '/lang/fr/site.php');
        $en = (string) file_get_contents($root . '/lang/en/site.php');
        self::assertStringContainsString("'hist_meta_title'", $fr);
        self::assertStringContainsString("'hist_meta_title'", $en);
        self::assertStringContainsString("'history'", $fr);
        self::assertStringContainsString("'history'", $en);
        self::assertStringContainsString("'hist_pill'", $fr);
        self::assertStringContainsString("'hist_cta_timeline'", $en);

        $nav = (string) file_get_contents($root . '/views/partials/home_nav_drawer.php');
        self::assertStringContainsString('notre-histoire', $nav);
    }
}
