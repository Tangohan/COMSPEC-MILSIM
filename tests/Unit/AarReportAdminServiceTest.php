<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Aar\AarReportAdminService as Aar;
use PHPUnit\Framework\TestCase;

/** Back-office comptes rendus : circuit de relecture, suivi des actions, missions sans retour, export. */
final class AarReportAdminServiceTest extends TestCase
{
    public function testTransitions(): void
    {
        self::assertTrue(Aar::canTransition('pending', 'in_review'));
        self::assertTrue(Aar::canTransition('in_review', 'validated'));
        self::assertTrue(Aar::canTransition('validated', 'in_review'));
        self::assertFalse(Aar::canTransition('validated', 'missing'));
        self::assertFalse(Aar::canTransition('missing', 'validated'));
    }

    public function testParseActionLine(): void
    {
        self::assertSame(['label' => 'Mettre à jour la carte', 'owner' => 'Kodiak', 'due_at' => '2026-11-02'], Aar::parseActionLine('Mettre à jour la carte — Kodiak — 02/11/2026'));
        self::assertSame(['label' => 'Revoir le plan - phase 2', 'owner' => null, 'due_at' => null], Aar::parseActionLine('Revoir le plan - phase 2'));
        self::assertSame(['label' => 'Former', 'owner' => 'Doc', 'due_at' => null], Aar::parseActionLine('Former | Doc'));
    }

    public function testActionsRowsAndToggle(): void
    {
        $split = Aar::actionsFromRows([
            ['label' => 'A', 'owner' => 'X', 'due_at' => '2026-10-01', 'status' => 'open'],
            ['label' => '', 'status' => 'open'],
            ['label' => 'B', 'status' => 'closed', 'closed_at' => '2026-10-05'],
            '1',
        ]);
        self::assertSame(1, count($split['open']));
        self::assertSame('2026-10-05', $split['closed'][0]['closed_at']);

        $next = Aar::toggleAction($split['open'], $split['closed'], 'open', 0, '2026-10-20');
        self::assertSame(0, count($next['open']));
        self::assertSame('2026-10-20', $next['closed'][1]['closed_at']);
        $back = Aar::toggleAction($next['open'], $next['closed'], 'closed', 1);
        self::assertSame('A', $back['open'][0]['label']);
        self::assertFalse(isset($back['open'][0]['closed_at']));
        self::assertSame(null, Aar::toggleAction([], [], 'open', 3));
    }

    public function testKpisActionsAndMissingMissions(): void
    {
        $now = strtotime('2026-10-20 12:00:00');
        $reports = [
            ['id' => 1, 'status' => 'pending', 'reported_at' => '2026-10-19 12:00:00', 'mission_ended_at' => '2026-10-18 12:00:00', 'mission_cycle_id' => 3,
                'open_actions' => [['label' => 'En retard', 'due_at' => '2026-10-10'], ['label' => 'À venir', 'due_at' => '2026-10-30']], 'closed_actions' => [['label' => 'Faite']], 'totals' => ['open_actions' => 2]],
            ['id' => 2, 'status' => 'validated', 'reported_at' => '2026-10-10 12:00:00', 'mission_ended_at' => '2026-10-01 12:00:00', 'operation_label' => 'Tempête', 'open_actions' => [], 'closed_actions' => []],
        ];
        $missions = [
            ['id' => 3, 'title' => 'Aigle', 'status' => 'cloturee', 'ended_at' => '2026-10-18 12:00:00'],
            ['id' => 4, 'title' => 'tempête ', 'status' => 'cloturee', 'ended_at' => '2026-10-09 12:00:00'],
            ['id' => 5, 'title' => 'Rempart', 'status' => 'cloturee', 'ended_at' => '2026-10-15 12:00:00'],
            ['id' => 6, 'title' => 'Comète', 'status' => 'en_cours'],
        ];
        $missing = Aar::missionsWithoutReport($missions, $reports, $now);
        self::assertSame([5], array_column($missing, 'id'));
        self::assertTrue($missing[0]['late']);

        $actions = Aar::actions($reports, $now);
        self::assertSame('En retard', $actions[0]['label']);
        self::assertTrue($actions[0]['overdue']);
        self::assertSame('closed', $actions[2]['state']);

        $k = Aar::kpis($reports, $missing, $now);
        self::assertSame(1, $k['to_review']);
        self::assertSame(50, $k['validated_pct']);
        self::assertSame(1, $k['late']);
        self::assertSame(2, $k['actions_open']);
        self::assertSame(1, $k['actions_overdue']);
        self::assertSame(1, $k['missing']);
    }

    public function testFilterAndCsv(): void
    {
        $reports = [
            ['id' => 1, 'reference_code' => 'AAR-0001', 'status' => 'pending', 'author_name' => 'Kodiak', 'operation_label' => 'Aigle', 'reported_at' => date('Y-m-d H:i:s'), 'totals' => ['open_actions' => 1]],
            ['id' => 2, 'reference_code' => 'AAR-0002', 'status' => 'validated', 'author_name' => 'Hunter', 'operation_label' => '=HYPERLINK("x")', 'reported_at' => '2020-01-01 00:00:00', 'totals' => ['open_actions' => 0]],
        ];
        self::assertSame(1, count(Aar::filter($reports, ['q' => 'aigle'])));
        self::assertSame(1, count(Aar::filter($reports, ['period' => 30])));
        self::assertSame(1, count(Aar::filter($reports, ['open_actions' => true])));
        self::assertSame(1, count(Aar::filter($reports, ['author' => 'Hunter', 'status' => 'validated'])));
        $csv = Aar::csv($reports);
        self::assertTrue(str_starts_with($csv, "\xEF\xBB\xBF\"Référence\""));
        self::assertTrue(str_contains($csv, "\"'=HYPERLINK(\"\"x\"\")\""));
    }
}
