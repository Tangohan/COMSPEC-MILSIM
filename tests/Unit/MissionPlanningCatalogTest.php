<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MissionPlanningLabels;
use App\Support\MissionPlanningTemplate;
use PHPUnit\Framework\TestCase;

final class MissionPlanningCatalogTest extends TestCase
{
    public function testStatusLabelsNeverExposeRawEnums(): void
    {
        self::assertSame('Brouillon', MissionPlanningLabels::status('draft'));
        self::assertSame('En session', MissionPlanningLabels::status('live'));
        self::assertSame('Confirmé', MissionPlanningLabels::presence('confirmed'));
        self::assertSame('Remplaçant détecté', MissionPlanningLabels::presence('mismatch'));
        self::assertSame('Affecté à l’avance', MissionPlanningLabels::mode('preassigned'));
        self::assertSame('Organisation prévue', MissionPlanningLabels::toVersion('planned'));
        self::assertSame('Vacant', MissionPlanningLabels::personLabel(null));
    }

    public function testDefaultTaskForceHasHqManeuverAirAndSupport(): void
    {
        $tf = MissionPlanningTemplate::defaultTaskForce();
        $codes = array_column($tf, 'code');
        self::assertSame(['HQ', 'ALPHA', 'BRAVO', 'AIR', 'SUPPORT'], $codes);

        $slots = 0;
        foreach ($tf as $el) {
            self::assertNotSame('', $el['label']);
            self::assertGreaterThan(0, $el['auth']);
            $slots += count($el['slots']);
            foreach ($el['slots'] as $slot) {
                self::assertNotSame('', $slot['callsign']);
                self::assertNotSame('', $slot['function']);
            }
        }
        self::assertSame(25, $slots);
    }

    public function testDefaultControlMeasuresAndTimelineAreNamedForTheMap(): void
    {
        $measures = MissionPlanningTemplate::defaultControlMeasures();
        self::assertCount(9, $measures);
        $codes = array_column($measures, 'code');
        self::assertContains('LD', $codes);
        self::assertContains('OBJ EAGLE', $codes);
        self::assertContains('AXIS RED', $codes);
        self::assertSame('Prévu', MissionPlanningLabels::drawState('planned'));
        self::assertSame('En cours', MissionPlanningLabels::drawState('current'));
        self::assertSame('Terminé', MissionPlanningLabels::drawState('completed'));
        self::assertSame('Modifié en session', MissionPlanningLabels::drawState('modified'));
        self::assertSame('État-major', MissionPlanningLabels::elementKind('hq'));
        self::assertSame('Manœuvre', MissionPlanningLabels::elementKind('maneuver'));
        self::assertSame('Air', MissionPlanningLabels::elementKind('air'));
        self::assertSame('Soutien', MissionPlanningLabels::elementKind('support'));
        self::assertSame('En liaison', MissionPlanningLabels::armaLink('linked'));
        self::assertSame('Assaut', MissionPlanningLabels::phase('ASSAULT'));
        $tl = MissionPlanningTemplate::defaultTimeline();
        self::assertGreaterThanOrEqual(4, count($tl));
    }
}
