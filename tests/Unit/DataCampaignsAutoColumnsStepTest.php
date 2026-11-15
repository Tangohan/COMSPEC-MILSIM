<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DataCampaignsAutoColumnsStepTest extends TestCase
{
    public function testStepIsRegisteredAndRepairsMissingColumns(): void
    {
        $root = dirname(__DIR__, 2);
        $registry = (string) file_get_contents($root . '/database/steps/registry.php');
        self::assertStringContainsString("'file' => '4620_data_campaigns_auto_columns.php'", $registry);

        $step = (string) file_get_contents($root . '/database/steps/4620_data_campaigns_auto_columns.php');
        foreach (['auto_created', 'auto_year', 'forced_on', 'uq_data_campaign_auto'] as $needle) {
            self::assertStringContainsString($needle, $step);
        }
        self::assertStringContainsString('addColumnIfMissing', $step);
        self::assertFileExists($root . '/migrations/rollback/data_campaigns_auto_columns_down.sql');
    }
}
