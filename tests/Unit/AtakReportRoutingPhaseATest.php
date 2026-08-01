<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakBridgeModulesService;
use PHPUnit\Framework\TestCase;

final class AtakReportRoutingPhaseATest extends TestCase
{
    public function testReportRoutingIsAnAdministrableAtakModule(): void
    {
        $catalog = (new AtakBridgeModulesService())->catalog();
        $modules = array_column($catalog, null, 'id');

        self::assertArrayHasKey('report_routing', $modules);
        self::assertSame('Routage des rapports tactiques', $modules['report_routing']['label']);
        self::assertStringContainsString('sans masquer', $modules['report_routing']['description']);
    }

    public function testIdempotencyMigrationProtectsEachRuleAndRecipientTuple(): void
    {
        $migration = file_get_contents(base_path(
            'migrations/2026_08_01_001_atak_report_routing_idempotency.sql'
        ));

        self::assertIsString($migration);
        self::assertStringContainsString('uq_report_rule_recipient', $migration);
        self::assertStringContainsString('report_id,', $migration);
        self::assertStringContainsString('routing_rule_id,', $migration);
        self::assertStringContainsString('routed_to_type,', $migration);
        self::assertStringContainsString('routed_to_identifier', $migration);
    }
}
