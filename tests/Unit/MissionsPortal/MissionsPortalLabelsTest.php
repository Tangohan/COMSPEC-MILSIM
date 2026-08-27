<?php

declare(strict_types=1);

namespace Tests\Unit\MissionsPortal;

use App\Support\MissionsPortalLabels;
use PHPUnit\Framework\TestCase;

final class MissionsPortalLabelsTest extends TestCase
{
    public function testPlanProgressMapsStepsWithoutRawEnums(): void
    {
        $draft = MissionsPortalLabels::planProgress('draft');
        self::assertSame(1, $draft['filled']);
        self::assertSame(4, $draft['total']);
        self::assertSame('Brouillon', $draft['label']);
        self::assertSame('Publié', $draft['next_label']);
        self::assertSame('prep', $draft['tone']);

        $live = MissionsPortalLabels::planProgress('live');
        self::assertSame(3, $live['filled']);
        self::assertSame('En session', $live['label']);
        self::assertSame('Clôturé', $live['next_label']);
        self::assertSame('live', $live['tone']);

        $closed = MissionsPortalLabels::planProgress('closed');
        self::assertSame(4, $closed['filled']);
        self::assertNull($closed['next_label']);
        self::assertSame('done', $closed['tone']);
    }

    public function testCycleProgressUsesThreePhases(): void
    {
        $prep = MissionsPortalLabels::cycleProgress('preparation');
        self::assertSame(1, $prep['filled']);
        self::assertSame(3, $prep['total']);
        self::assertSame('Préparation', $prep['label']);
        self::assertSame('En cours', $prep['next_label']);

        $done = MissionsPortalLabels::cycleProgress('cloturee');
        self::assertSame(3, $done['filled']);
        self::assertSame('Clôturée', $done['label']);
        self::assertNull($done['next_label']);
        self::assertSame('done', $done['tone']);
    }

    public function testAtakAndGatewayLabelsStayHuman(): void
    {
        self::assertSame('En liaison', MissionsPortalLabels::atakUnitStatus('linked'));
        self::assertSame('Liaison différée', MissionsPortalLabels::atakUnitStatus('delayed'));
        self::assertSame('Hors liaison', MissionsPortalLabels::atakUnitStatus('offline'));
        self::assertSame('Active', MissionsPortalLabels::gatewayStatus('active'));
        self::assertSame('En attente de validation', MissionsPortalLabels::gatewayStatus('pending_validation'));
        self::assertSame('Opérationnel', MissionsPortalLabels::takServerLabel(true));
        self::assertSame('Maintenance', MissionsPortalLabels::takServerLabel(false));
    }
}
