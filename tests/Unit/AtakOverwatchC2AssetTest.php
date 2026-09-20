<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakOrderRepository;
use PHPUnit\Framework\TestCase;

final class AtakOverwatchC2AssetTest extends TestCase
{
    public function testC2ToolkitIsWiredOnOverwatchBeta(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/atak-overwatch-beta.php');
        $c2 = (string) file_get_contents($root . '/public/assets/js/atak-overwatch-c2.js');
        $ops = (string) file_get_contents($root . '/public/assets/js/atak-overwatch-ops.js');
        $css = (string) file_get_contents($root . '/public/assets/css/atak-overwatch-beta.css');
        $catalog = (string) file_get_contents($root . '/app/Services/ConfigurationUpdate/ConfigurationUpdateCatalog.php');
        $seed = (string) file_get_contents($root . '/bootstrap/configuration_updates_migration.php');
        $run = (string) file_get_contents($root . '/run-migrations.php');
        $orders = (string) file_get_contents($root . '/app/Repositories/AtakOrderRepository.php');

        self::assertStringContainsString('atak-overwatch-c2.js', $view);
        self::assertStringContainsString('id="ow-comms-search"', $view);
        self::assertStringContainsString('id="ow-c2-alert-radius"', $view);
        self::assertStringContainsString('id="ow-freeze-banner"', $view);
        self::assertStringContainsString('/api/atak/salute', $c2);
        self::assertStringContainsString('openDebrief', $c2);
        self::assertStringContainsString('/api/replay/aar/', $c2);
        self::assertStringContainsString('Positions figées', $c2);
        self::assertStringContainsString('liveWindow', $c2);
        self::assertStringContainsString('delayedSec', $c2);
        self::assertStringContainsString('staleCount === liveCount', $c2);
        self::assertStringContainsString('Math.max(45, pollSec * 3)', $c2);
        self::assertStringNotContainsString('age >= 20', $c2);
        self::assertStringNotContainsString('rxAge >= 12', $c2);
        self::assertStringContainsString('isTrackedAi', $c2);
        self::assertStringContainsString('.ow-freeze-banner', $css);
        self::assertStringContainsString('.ow-scene-load', $css);
        self::assertStringContainsString('list.forEach', $ops);
        self::assertStringContainsString('ATAK_COMMAND_ALERT_RBAC_V1', $catalog);
        self::assertStringContainsString('ATAK_COMMAND_ALERT_RBAC_V1', $seed);
        self::assertStringContainsString('atak_command_alert_permission_migration.php', $run);
        self::assertStringContainsString("'DONE'", $orders);
        self::assertContains('DONE', AtakOrderRepository::STATUSES);
        self::assertTrue((new AtakOrderRepository())->canTransitionStatus('ACK', 'DONE'));
        self::assertTrue((new AtakOrderRepository())->canTransitionStatus('EXEC', 'DONE'));
        self::assertSame('DONE', (new AtakOrderRepository())->normalizeStatus('COMPLETE'));
    }
}
