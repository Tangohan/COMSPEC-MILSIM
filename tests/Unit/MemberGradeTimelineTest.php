<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Effectifs\MemberGradeTimeline;
use PHPUnit\Framework\TestCase;

/** Frise des grades : historique enregistré + reconstitution de l’existant sans inventer. */
final class MemberGradeTimelineTest extends TestCase
{
    private const LABELS = [1 => 'Recrue', 2 => 'Private (PVT)', 3 => 'Private First Class (PFC)'];

    public function testRecordedChangesKeepGradeBeforeAndAfter(): void
    {
        $out = MemberGradeTimeline::build([
            ['changed_at' => '2026-08-12 10:00:00', 'from_grade_id' => 2, 'to_grade_id' => 3, 'from_label' => 'Private (PVT)', 'to_label' => 'Private First Class (PFC)', 'source' => 'elevation', 'actor_display_name' => 'Cpt. Reyes'],
        ], [], 3, self::LABELS);

        self::assertCount(1, $out['entries']);
        $e = $out['entries'][0];
        self::assertTrue($e['from_known']);
        self::assertSame('Private (PVT)', $e['from_label']);
        self::assertSame('elevation', $e['source']);
        self::assertSame('Cpt. Reyes', $e['actor_label']);
        self::assertSame('2026-08-12 10:00:00', $out['current_since']);
    }

    public function testOldTracesGiveOnlyTheDateWhenThePreviousGradeIsUnknown(): void
    {
        $derived = [
            ['date' => '2026-06-02 09:00:00', 'to_grade_id' => 2, 'source' => 'manual'],
            ['date' => '2026-05-24 09:00:00', 'to_grade_id' => 1, 'source' => 'manual'],
            ['date' => '2026-06-03 09:00:00', 'to_grade_id' => 2, 'source' => 'manual'],
            // Postérieure au premier changement enregistré : déjà comptée, ignorée.
            ['date' => '2026-08-12 10:00:00', 'to_grade_id' => 3, 'source' => 'elevation'],
        ];
        $recorded = [['changed_at' => '2026-08-12 10:00:00', 'from_grade_id' => 2, 'to_grade_id' => 3, 'source' => 'elevation']];

        $out = MemberGradeTimeline::build($recorded, $derived, 3, self::LABELS);
        $entries = array_reverse($out['entries']);

        self::assertCount(3, $entries, 'doublons et traces déjà enregistrées retirés');
        self::assertFalse($entries[0]['from_known'], 'premier grade connu : le grade précédent n’est pas inventé');
        self::assertSame('', $entries[0]['from_label']);
        self::assertSame('Recrue', $entries[0]['to_label']);
        self::assertTrue($entries[1]['reconstructed']);
        self::assertTrue($entries[1]['from_known']);
        self::assertSame('Recrue', $entries[1]['from_label']);
        self::assertFalse($entries[2]['reconstructed']);
        self::assertSame('Private First Class (PFC)', $entries[2]['to_label']);
    }

    public function testCurrentSinceIsUnknownWhenTheLastChangeDoesNotLeadToTheCurrentGrade(): void
    {
        $out = MemberGradeTimeline::build([], [['date' => '2026-01-01', 'to_grade_id' => 1, 'source' => 'manual']], 3, self::LABELS);

        self::assertNull($out['current_since']);
        self::assertSame('Grade retiré du référentiel', MemberGradeTimeline::build([], [['date' => '2026-01-01', 'to_grade_id' => 50, 'source' => 'manual']], null, self::LABELS)['entries'][0]['to_label']);
    }
}
