<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\PersonnelMemberFollowupSnapshot;
use App\Services\Personnel\RoleplayFollowupSettings;
use PHPUnit\Framework\TestCase;

final class PersonnelMemberFollowupSnapshotTest extends TestCase
{
    public function testHiddenWhenFollowupDisabledAndNoPhase(): void
    {
        $cfg = RoleplayFollowupSettings::defaults();
        $out = PersonnelMemberFollowupSnapshot::build([], $cfg, null, '2026-01-01');

        self::assertFalse($out['visible']);
        self::assertFalse($out['show_immersion']);
        self::assertFalse($out['show_parcours']);
        self::assertSame([], $out['deadlines']);
    }

    public function testDeadlinesIncludeBilanAndMarkOverdueInterview(): void
    {
        $cfg = RoleplayFollowupSettings::defaults();
        $cfg['enabled'] = true;
        $out = PersonnelMemberFollowupSnapshot::build(
            [
                'rp_next_interview_date' => '2020-01-01',
                'rp_medical_due_date' => '',
                'rp_service_rotation_date' => '2026-12-31',
                'rp_followup_stage' => 'Tutorat',
                'rp_followup_progress' => 40,
            ],
            $cfg,
            null,
            '2025-01-01'
        );

        self::assertTrue($out['visible']);
        self::assertTrue($out['show_immersion']);
        self::assertSame('Tutorat', $out['stage']);
        self::assertSame(40, $out['progress']);
        $keys = array_column($out['deadlines'], 'key');
        self::assertSame(['interview', 'medical', 'rotation', 'bilan'], $keys);
        $interview = $out['deadlines'][0];
        self::assertTrue($interview['overdue']);
        self::assertTrue($out['attention']);
        self::assertNotSame([], $out['attention_items']);
    }

    public function testPhaseWithoutNextStillVisible(): void
    {
        $cfg = RoleplayFollowupSettings::defaults();
        $out = PersonnelMemberFollowupSnapshot::build([], $cfg, [
            'phase' => ['label' => 'Actif'],
            'next' => null,
            'effect' => 'manual_gate',
            'evaluation' => ['eligible' => false, 'items' => []],
        ], null);

        self::assertTrue($out['visible']);
        self::assertTrue($out['show_parcours']);
        self::assertNotNull($out['phase']);
        self::assertTrue($out['phase']['is_last']);
        self::assertSame('Actif', $out['phase']['label']);
        self::assertFalse($out['attention']);
    }

    public function testProbationActiveUntilEndDate(): void
    {
        $cfg = RoleplayFollowupSettings::defaults();
        $cfg['enabled'] = true;
        $cfg['probation']['duration_days'] = 60;
        $start = (new \DateTimeImmutable('today'))->modify('-10 days')->format('Y-m-d');
        $out = PersonnelMemberFollowupSnapshot::build(
            ['enlistment_date' => $start],
            $cfg,
            null,
            $start
        );

        self::assertNotNull($out['probation']);
        self::assertTrue($out['probation']['active']);
        self::assertNotSame('', $out['probation']['ends_label']);
    }
}
