<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakBridgeModulesService;
use App\Services\Cron\Jobs\AtakReportRoutingEscalationsCronJob;
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

    public function testRoutedInboxAndAcknowledgementRoutesAreRegistered(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));

        self::assertIsString($routes);
        self::assertStringContainsString("'/api/atak/reports/routed'", $routes);
        self::assertStringContainsString("'/api/atak/reports/{id}/routing/{routingId}/acknowledge'", $routes);
    }

    public function testEscalationJobHasAStableCronKey(): void
    {
        $reflection = new \ReflectionClass(AtakReportRoutingEscalationsCronJob::class);
        /** @var AtakReportRoutingEscalationsCronJob $job */
        $job = $reflection->newInstanceWithoutConstructor();

        self::assertSame('atak_report_routing_escalations', $job->key());
    }
}
