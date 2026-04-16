<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Platform\DeploymentCampaignProcessor;
use PHPUnit\Framework\TestCase;

final class DeploymentCampaignProcessorTest extends TestCase
{
    public function testPickNextRunnableJobReturnsFirstQueuedWhenPredecessorsSucceeded(): void
    {
        $jobs = [
            ['id' => 1, 'status' => 'success', 'step_order' => 1],
            ['id' => 2, 'status' => 'queued', 'step_order' => 2],
            ['id' => 3, 'status' => 'queued', 'step_order' => 3],
        ];
        $next = DeploymentCampaignProcessor::pickNextRunnableJob($jobs);
        self::assertNotNull($next);
        self::assertSame(2, (int) ($next['id'] ?? 0));
    }

    public function testPickNextRunnableJobSkipsWhenEarlierStepNotSucceeded(): void
    {
        $jobs = [
            ['id' => 1, 'status' => 'queued', 'step_order' => 1],
            ['id' => 2, 'status' => 'queued', 'step_order' => 2],
        ];
        $next = DeploymentCampaignProcessor::pickNextRunnableJob($jobs);
        self::assertNotNull($next);
        self::assertSame(1, (int) ($next['id'] ?? 0));
    }

    public function testPickNextRunnableJobReturnsNullWhenNoQueued(): void
    {
        $jobs = [
            ['id' => 1, 'status' => 'success', 'step_order' => 1],
            ['id' => 2, 'status' => 'success', 'step_order' => 2],
        ];
        self::assertNull(DeploymentCampaignProcessor::pickNextRunnableJob($jobs));
    }
}
