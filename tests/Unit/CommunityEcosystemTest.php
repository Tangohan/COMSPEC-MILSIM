<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Ecosystem\CommunityStatsService;
use PHPUnit\Framework\TestCase;

final class CommunityEcosystemTest extends TestCase
{
    public function testMediansAndRanksIgnoreMissingValues(): void
    {
        $rows = [
            ['id' => 1, 'members' => 10, 'rating' => null],
            ['id' => 2, 'members' => 30, 'rating' => 4.0],
            ['id' => 3, 'members' => 20, 'rating' => 3.0],
            ['id' => 4, 'members' => 40, 'rating' => null],
        ];
        $medians = CommunityStatsService::medians($rows);
        self::assertSame(25.0, $medians['members']);
        self::assertSame(3.5, $medians['rating']);
        self::assertNull($medians['sse_records']);
        self::assertSame(3, CommunityStatsService::rank($rows, 'members', 3));
        self::assertSame(1, CommunityStatsService::rank($rows, 'rating', 2));
        self::assertNull(CommunityStatsService::rank($rows, 'rating', 1));
    }

    public function testHubIsWiredWithRoutesMigrationAndSafeguards(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Web/HubController.php');
        $migrations = (string) file_get_contents($root . '/run-migrations.php');
        $migration = (string) file_get_contents($root . '/bootstrap/community_ecosystem_migration.php');

        foreach (["'/hub/comparer'", "'/hub/c/{slug}'", "'/hub/c/{slug}/recommander'", "'/hub/avis'", "'/hub/bourse'", "'/hub/bourse/{id}/repondre'"] as $route) {
            self::assertStringContainsString($route, $routes);
        }
        self::assertStringContainsString('run_community_ecosystem_migration', $migrations);
        foreach (['tenant_recommendations', 'cooperation_reviews', 'cooperation_board_posts', 'cooperation_board_responses'] as $table) {
            self::assertStringContainsString($table, $migration);
        }
        // Pas d’auto-recommandation, avis réservé aux partenaires d’une coopération clôturée, CSRF partout.
        self::assertStringContainsString('Vous ne pouvez pas recommander votre propre communauté', $ctrl);
        self::assertStringContainsString('canReview', $ctrl);
        self::assertSame(substr_count($ctrl, 'public function') - 4, substr_count($ctrl, 'Csrf::validate'));
    }
}
