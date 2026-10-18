<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\EnlistmentNextAction;
use App\Support\EnlistmentPipelineBoard;
use PHPUnit\Framework\TestCase;

final class EnlistmentNextActionTest extends TestCase
{
    private const NOW = 1791979200; // 2026-10-14 12:00:00 UTC
    private const SLA = 72;

    private static function at(float $hoursAgo): string
    {
        return date('Y-m-d H:i:s', (int) (self::NOW - $hoursAgo * 3600));
    }

    /** @param array<string, mixed> $row */
    private static function action(array $row, int $viewer = 7, ?string $retroDone = null): ?array
    {
        return EnlistmentNextAction::for($row, self::SLA, $viewer, self::NOW, $retroDone);
    }

    public function testUnassignedDossierMustBeTakenAndCanBeClaimed(): void
    {
        $a = self::action(['status' => 'submitted', 'created_at' => self::at(5), 'updated_at' => self::at(5)]);
        self::assertSame('take', $a['column']);
        self::assertSame('Prendre en charge', $a['label']);
        self::assertSame('nobody', $a['owner']);
        self::assertTrue($a['canAssign']);
        self::assertSame('ok', $a['urgency']);
        self::assertSame('coordination-dossier', $a['anchor']);
    }

    public function testUrgencyFollowsTheAlertThreshold(): void
    {
        $late = self::action(['status' => 'submitted', 'reviewed_by' => 9, 'created_at' => self::at(100), 'updated_at' => self::at(80)]);
        self::assertSame('instruct', $late['column']);
        self::assertSame('late', $late['urgency']);
        self::assertSame('En retard', $late['urgencyLabel']);
        self::assertSame('other', $late['owner']);
        self::assertFalse($late['canAssign']);

        $watch = self::action(['status' => 'submitted', 'reviewed_by' => 7, 'created_at' => self::at(60), 'updated_at' => self::at(60)]);
        self::assertSame('watch', $watch['urgency']);
        self::assertSame('you', $watch['owner']);
        self::assertSame('Vous', $watch['ownerLabel']);
    }

    public function testInterviewBeforeAndAfterItsDate(): void
    {
        $base = ['status' => 'submitted', 'pipeline_stage' => 'interview_scheduled', 'reviewed_by' => 7, 'created_at' => self::at(50), 'updated_at' => self::at(10)];

        $upcoming = self::action($base + ['discord_interview_at' => date('Y-m-d H:i:s', self::NOW + 5 * 3600)]);
        self::assertSame('interview', $upcoming['column']);
        self::assertSame('Tenir l’entretien', $upcoming['label']);
        self::assertSame('soon', $upcoming['urgency']);
        self::assertSame('discord', $upcoming['anchor']);

        // Entretien passé : il faut conclure, sur le formulaire de décision — et non « dans les délais ».
        $past = self::action($base + ['discord_interview_at' => self::at(30)]);
        self::assertSame('Conclure l’entretien', $past['label']);
        self::assertSame('watch', $past['urgency']);
        self::assertSame('instruction-dossier', $past['anchor']);
        self::assertSame('late', self::action($base + ['discord_interview_at' => self::at(100)])['urgency']);

        $undated = self::action($base);
        self::assertSame('Fixer la date de l’entretien', $undated['label']);
    }

    public function testAcceptedDossiersGoToIntegrationThenRetro(): void
    {
        self::assertSame('integration-membre', self::action(['status' => 'reviewed', 'pipeline_stage' => 'acceptance_onboarding', 'submitter_user_id' => 3, 'reviewed_by' => 7])['anchor']);
        $link = self::action(['status' => 'reviewed', 'pipeline_stage' => 'accepted', 'reviewed_by' => 7]);
        self::assertSame('integrate', $link['column']);
        self::assertSame('rattachement-membre', $link['anchor']);

        $integrated = ['status' => 'reviewed', 'pipeline_stage' => 'accepted', 'submitter_user_id' => 3, 'reviewed_by' => 7];
        $due = self::action($integrated + ['reviewed_at' => self::at(24 * 35)]);
        self::assertSame('retro', $due['column']);
        self::assertSame('retro', $due['key']);
        self::assertSame('bilan-recrutement', $due['anchor']);

        $waiting = self::action($integrated + ['reviewed_at' => self::at(24 * 5)]);
        self::assertNull($waiting['column']);
        self::assertSame('integrated', $waiting['key']);

        $done = self::action($integrated + ['reviewed_at' => self::at(24 * 35)], 7, self::at(24));
        self::assertNull($done['column']);
        self::assertSame('done', $done['key']);
    }

    public function testClosedDossiersHaveNoAction(): void
    {
        foreach (['rejected', 'blocked', 'cancelled'] as $st) {
            self::assertNull(self::action(['status' => $st]));
        }
    }

    public function testColumnsSortMostUrgentFirstAndSkipOffBoardRows(): void
    {
        $rows = EnlistmentNextAction::annotate([
            ['id' => 1, 'status' => 'submitted', 'created_at' => self::at(10), 'updated_at' => self::at(10)],
            ['id' => 2, 'status' => 'submitted', 'created_at' => self::at(90), 'updated_at' => self::at(90)],
            ['id' => 3, 'status' => 'rejected'],
            ['id' => 4, 'status' => 'submitted', 'pipeline_stage' => 'on_hold', 'reviewed_by' => 7, 'created_at' => self::at(10), 'updated_at' => self::at(10)],
        ], self::SLA, 7, self::NOW);
        $cols = EnlistmentNextAction::columns($rows);
        self::assertSame(array_keys(EnlistmentNextAction::COLUMNS), array_column($cols, 'key'));
        self::assertSame([2, 1], array_column($cols[0]['cards'], 'id'));
        self::assertSame(1, $cols[0]['late']);
        self::assertSame([4], array_column($cols[3]['cards'], 'id'));
        self::assertSame(3, array_sum(array_map(static fn (array $c): int => count($c['cards']), $cols)));
    }

    public function testBoardFiltersSearchAndCounts(): void
    {
        $rows = [
            ['id' => 11, 'first_name' => 'Hugo', 'last_name' => 'Lambert', 'email' => 'h@x.fr', 'status' => 'submitted', 'created_at' => self::at(90), 'updated_at' => self::at(90)],
            ['id' => 12, 'first_name' => 'Nora', 'last_name' => 'Belkacem', 'email' => 'n@x.fr', 'status' => 'submitted', 'reviewed_by' => 7, 'created_at' => self::at(5), 'updated_at' => self::at(5)],
            ['id' => 13, 'first_name' => 'Paul', 'last_name' => 'Mercier', 'status' => 'rejected'],
        ];
        $all = EnlistmentPipelineBoard::viewVars($rows, [], 7, self::SLA, self::NOW);
        self::assertSame('tableau', $all['pipelineView']);
        self::assertSame(['mine' => 1, 'libre' => 1, 'retard' => 1, 'active' => 2, 'interviews' => 0, 'retro' => 0], $all['pipelineCounts']);
        self::assertSame(1, $all['pipelineOffBoard']);

        self::assertSame([12], array_column(EnlistmentPipelineBoard::viewVars($rows, ['filtre' => 'mine'], 7, self::SLA, self::NOW)['enlistments'], 'id'));
        self::assertSame([11], array_column(EnlistmentPipelineBoard::viewVars($rows, ['filtre' => 'retard'], 7, self::SLA, self::NOW)['enlistments'], 'id'));
        self::assertSame([11], array_column(EnlistmentPipelineBoard::viewVars($rows, ['filtre' => 'libre'], 7, self::SLA, self::NOW)['enlistments'], 'id'));
        self::assertSame([13], array_column(EnlistmentPipelineBoard::viewVars($rows, ['q' => 'merc'], 7, self::SLA, self::NOW)['enlistments'], 'id'));
        self::assertSame([12], array_column(EnlistmentPipelineBoard::viewVars($rows, ['q' => '#12'], 7, self::SLA, self::NOW)['enlistments'], 'id'));
        // Filtre inconnu ignoré ; « mes dossiers » sans utilisateur connecté ne renvoie rien.
        self::assertSame(3, count(EnlistmentPipelineBoard::viewVars($rows, ['filtre' => 'x'], 7, self::SLA, self::NOW)['enlistments']));
        self::assertSame([], EnlistmentPipelineBoard::viewVars($rows, ['filtre' => 'mine'], 0, self::SLA, self::NOW)['enlistments']);
    }

    public function testViewDefaultsAndLinks(): void
    {
        self::assertSame('tableau', EnlistmentPipelineBoard::view([]));
        self::assertSame('liste', EnlistmentPipelineBoard::view(['status' => 'rejected']));
        self::assertSame('liste', EnlistmentPipelineBoard::view(['stage' => 'on_hold']));
        self::assertSame('tableau', EnlistmentPipelineBoard::view(['vue' => 'tableau', 'status' => 'rejected']));

        $base = '/app/back-office/recruitments';
        self::assertSame($base, EnlistmentPipelineBoard::link($base, ['vue' => 'tableau']));
        self::assertSame($base . '?filtre=mine', EnlistmentPipelineBoard::link($base, ['vue' => 'tableau'], ['filtre' => 'mine']));
        self::assertSame($base . '?vue=liste&q=hugo', EnlistmentPipelineBoard::link($base, ['vue' => 'tableau', 'q' => 'hugo'], ['vue' => 'liste']));
        self::assertSame($base . '?vue=liste', EnlistmentPipelineBoard::link($base, ['vue' => 'liste', 'filtre' => 'mine'], ['filtre' => null]));
    }

    public function testViewsUseThePipelineAndKeepTheirTools(): void
    {
        $root = dirname(__DIR__, 2);
        $index = (string) file_get_contents($root . '/views/admin/recruitments/index.php');
        foreach (['rp-board', 'pipeline_card.php', 'Liste complète', 'messages-prefaits', 'back-office/recruitments/equipe', 'enlistment_sla_hours', 'recruitment-sheets__table', 'Prochaine action'] as $needle) {
            self::assertStringContainsString($needle, $index);
        }
        $card = (string) file_get_contents($root . '/views/admin/recruitments/partials/pipeline_card.php');
        self::assertStringContainsString('return_qs', $card);
        self::assertStringContainsString('Csrf::field()', $card);

        $show = (string) file_get_contents($root . '/views/admin/recruitments/show.php');
        self::assertStringContainsString('dossier_pilot_bar.php', $show);
        $bar = (string) file_get_contents($root . '/views/admin/recruitments/partials/dossier_pilot_bar.php');
        self::assertStringContainsString('id="recap-dossier"', $bar);
        self::assertStringContainsString('return_to', $bar);

        $head = (string) file_get_contents($root . '/views/admin/recruitment_workspace/partials/recruitment_lms_head.php');
        self::assertStringContainsString('recruitment_pipeline.css', $head);
    }
}
