<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MemberIntegration\MemberIntegrationProgressService;
use App\Support\MemberIntegrationCatalog;
use PHPUnit\Framework\TestCase;

final class MemberIntegrationProgressServiceTest extends TestCase
{
    public function testPercentUsesRequiredStepsOnlyAndOptionalDoesNotBlock(): void
    {
        $svc = new MemberIntegrationProgressService();
        $out = $svc->compute([
            ['is_required' => 1, 'status' => MemberIntegrationCatalog::STEP_COMPLETED, 'due_at' => null],
            ['is_required' => 1, 'status' => MemberIntegrationCatalog::STEP_PENDING, 'due_at' => null],
            ['is_required' => 0, 'status' => MemberIntegrationCatalog::STEP_PENDING, 'due_at' => null],
        ]);
        self::assertSame(50, $out['progress_percent']);
        self::assertSame(2, $out['required_total']);
        self::assertSame(1, $out['required_completed']);
        self::assertFalse($out['can_complete']);
        self::assertSame(MemberIntegrationCatalog::STATUS_IN_PROGRESS, $out['status']);
    }

    public function testOptionalSkipAllowsClosureWhenRequiredDone(): void
    {
        $svc = new MemberIntegrationProgressService();
        $out = $svc->compute([
            ['is_required' => 1, 'status' => MemberIntegrationCatalog::STEP_COMPLETED],
            ['is_required' => 0, 'status' => MemberIntegrationCatalog::STEP_SKIPPED],
        ]);
        self::assertSame(100, $out['progress_percent']);
        self::assertTrue($out['can_complete']);
        self::assertSame(MemberIntegrationCatalog::STATUS_COMPLETED, $out['status']);
    }

    public function testBlockedRequiredRaisesGlobalBlocked(): void
    {
        $svc = new MemberIntegrationProgressService();
        $out = $svc->compute([
            ['is_required' => 1, 'status' => MemberIntegrationCatalog::STEP_BLOCKED],
            ['is_required' => 1, 'status' => MemberIntegrationCatalog::STEP_COMPLETED],
        ]);
        self::assertTrue($out['blocked']);
        self::assertFalse($out['can_complete']);
        self::assertSame(MemberIntegrationCatalog::STATUS_BLOCKED, $out['status']);
    }

    public function testOverdueDoesNotClose(): void
    {
        $svc = new MemberIntegrationProgressService();
        $out = $svc->compute([
            ['is_required' => 1, 'status' => MemberIntegrationCatalog::STEP_PENDING, 'due_at' => '2020-01-01 00:00:00'],
        ], '2026-09-01 12:00:00');
        self::assertSame(1, $out['overdue_count']);
        self::assertFalse($out['can_complete']);
        self::assertSame(0, $out['progress_percent']);
    }
}
