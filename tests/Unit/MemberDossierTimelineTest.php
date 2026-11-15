<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Effectifs\Dossier\DossierCatalog;
use App\Services\Effectifs\Dossier\DossierStore;
use App\Services\Effectifs\Dossier\MemberDossierTeamBoard;
use App\Services\Effectifs\Dossier\MemberDossierTimeline;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\DossierFixture as F;

/**
 * Fil d'étapes unifié : chaque adaptateur lit sa table, les statuts sont calculés, la communauté est respectée
 * et, sans la migration, le suivi reste lisible en lecture seule.
 */
final class MemberDossierTimelineTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = F::pdo();
        foreach ([4 => 'Cpt Delorme', 7 => 'Sgt Marchand', 9 => 'Lt Vasseur'] as $id => $name) {
            F::user($this->pdo, $id, $name);
        }
        F::richMember($this->pdo, 7);
    }

    private function timeline(?PDO $pdo = null): MemberDossierTimeline
    {
        $pdo ??= $this->pdo;

        return new MemberDossierTimeline($pdo, new DossierStore($pdo), null, static fn (int $t, array $ids): array => array_intersect_key([4 => 'Cpt Delorme', 7 => 'Sgt Marchand', 9 => 'Lt Vasseur'], array_flip($ids)));
    }

    /** @return array<string, array<string, mixed>> étapes par clé */
    private function byKey(): array
    {
        $out = [];
        foreach ($this->timeline()->forUser(F::TENANT, 7)['steps'] as $s) {
            $out[$s['key']] = $s;
        }

        return $out;
    }

    public function testEveryParcoursAdapterContributesItsSteps(): void
    {
        $data = $this->timeline()->forUser(F::TENANT, 7);
        $sources = array_values(array_unique(array_map(static fn (array $s): string => $s['source_type'], $data['steps'])));
        foreach (['enlistment', 'enlistment_retro_staff', 'enlistment_retro_member', 'integration_step', 'qualification', 'enrollment', 'campaign', 'elevation'] as $expected) {
            self::assertContains($expected, $sources, $expected);
        }
        self::assertSame(['recruitment', 'integration', 'formation', 'documents', 'career', 'reliability'], array_keys($data['tracks']));
        self::assertCount(15, $data['steps']);
        self::assertSame(15, $data['summary']['total']);
        self::assertFalse($data['degraded']);
    }

    public function testIntegrationDocumentStepsGoToDocumentsAndOptionalTrainingToFormation(): void
    {
        $steps = $this->byKey();
        self::assertSame('documents', $steps['integration_step:3']['track']);
        self::assertSame('documents', $steps['integration_step:4']['track']);
        self::assertSame('formation', $steps['integration_step:6']['track']);
        self::assertSame('integration', $steps['integration_step:1']['track']);
    }

    public function testStatusesAreComputedFromStateAndDueDate(): void
    {
        $steps = $this->byKey();
        self::assertSame(DossierCatalog::STATUS_DONE, $steps['integration_step:1']['status']);
        self::assertSame(DossierCatalog::STATUS_OVERDUE, $steps['integration_step:4']['status'], 'échéance dépassée');
        self::assertTrue($steps['integration_step:4']['late']);
        self::assertSame(DossierCatalog::STATUS_IN_PROGRESS, $steps['integration_step:3']['status'], 'en attente du membre, échéance à venir');
        self::assertSame(DossierCatalog::STATUS_UPCOMING, $steps['integration_step:8']['status']);
        self::assertSame(DossierCatalog::STATUS_BLOCKED, $steps['integration_step:7']['status'], 'bloquée même avec une échéance dépassée');
        self::assertTrue($steps['integration_step:7']['late'], 'le retard reste signalé à part');
        self::assertSame(DossierCatalog::STATUS_OVERDUE, $steps['enlistment_retro_member:1']['status'], 'bilan du membre dû 30 jours après la décision');
        self::assertSame(DossierCatalog::STATUS_DONE, $steps['enlistment_retro_staff:1']['status']);
    }

    public function testCalculatedStatusPureFunction(): void
    {
        self::assertSame('overdue', DossierCatalog::status('todo', '2026-01-01', '2026-02-01'));
        self::assertSame('upcoming', DossierCatalog::status('todo', '2026-02-01', '2026-02-01'), 'le jour même n’est pas en retard');
        self::assertSame('done', DossierCatalog::status('done', '2020-01-01', '2026-02-01'));
        self::assertSame('skipped', DossierCatalog::status('skipped', '2020-01-01', '2026-02-01'));
        self::assertSame('in_progress', DossierCatalog::status('in_progress', null, '2026-02-01'));
        self::assertNull(DossierCatalog::day('not a date'));
        self::assertNull(DossierCatalog::day('2026-02-31'));
    }

    public function testSummaryGivesProgressNextActionAndLateCount(): void
    {
        $sum = $this->timeline()->forUser(F::TENANT, 7)['summary'];
        self::assertSame(5, $sum['done']);
        self::assertSame(15, $sum['total']);
        self::assertSame(33, $sum['percent']);
        self::assertSame(3, $sum['late']);
        self::assertSame(1, $sum['blocked']);
        // La prochaine action est la plus en retard : le bilan du membre, dû depuis 40 jours.
        self::assertSame('enlistment_retro_member:1', $sum['next']['key']);
        self::assertSame('Le membre', $sum['next']['owner_label']);
    }

    public function testOwnerLabelsUseNamesWhenAUserIsDesignated(): void
    {
        $steps = $this->byKey();
        self::assertSame('Cpt Delorme', $steps['integration_step:2']['owner_label']);
        self::assertSame('Un instructeur', $steps['integration_step:8']['owner_label']);
    }

    public function testSkippedStepsDoNotCountInProgress(): void
    {
        $this->pdo->exec("UPDATE member_integration_steps SET status = 'skipped' WHERE id = 6");
        $sum = $this->timeline()->forUser(F::TENANT, 7)['summary'];
        self::assertSame(14, $sum['total']);
    }

    public function testAnotherCommunityNeverSeesTheMembersSteps(): void
    {
        self::assertSame([], $this->timeline()->forUser(2, 7)['steps']);
        $this->pdo->exec('UPDATE member_integrations SET tenant_id = 2');
        $steps = array_filter($this->timeline()->forUser(F::TENANT, 7)['steps'], static fn (array $s): bool => $s['source_type'] === 'integration_step');
        self::assertSame([], array_values($steps));
    }

    public function testCancelledIntegrationAndOldIntegrationsAreIgnored(): void
    {
        $this->pdo->exec("UPDATE member_integrations SET status = 'cancelled' WHERE id = 1");
        $n = count(array_filter($this->timeline()->forUser(F::TENANT, 7)['steps'], static fn (array $s): bool => $s['source_type'] === 'integration_step'));
        self::assertSame(0, $n);
        $this->pdo->exec("UPDATE member_integrations SET status = 'in_progress' WHERE id = 1");
        $newer = F::integration($this->pdo, 7);
        F::integrationStep($this->pdo, $newer, ['title' => 'Nouveau parcours', 'position' => 1]);
        $titles = array_map(static fn (array $s): string => $s['title'], array_filter($this->timeline()->forUser(F::TENANT, 7)['steps'], static fn (array $s): bool => $s['source_type'] === 'integration_step'));
        self::assertSame(['Nouveau parcours'], array_values($titles), 'seul le parcours le plus récent est suivi');
    }

    public function testDepartureStepsAppearAndDisappearOnReinstatement(): void
    {
        $this->pdo->exec("INSERT INTO member_departures (tenant_id, user_id, departed_at, access_revoked, created_at) VALUES (1, 7, '" . date('Y-m-d', strtotime('-3 days')) . "', 0, '" . date('Y-m-d H:i:s') . "')");
        $dep = array_filter($this->timeline()->forUser(F::TENANT, 7)['steps'], static fn (array $s): bool => $s['track'] === 'departure');
        self::assertCount(3, $dep);
        $this->pdo->exec("UPDATE member_departures SET reinstated_at = '" . date('Y-m-d H:i:s') . "'");
        self::assertCount(0, array_filter($this->timeline()->forUser(F::TENANT, 7)['steps'], static fn (array $s): bool => $s['track'] === 'departure'));
    }

    public function testMissingSourceTableGivesNoStepsInsteadOfAnError(): void
    {
        $this->pdo->exec('DROP TABLE enlistment_retro_feedbacks');
        $this->pdo->exec('DROP TABLE data_campaign_flags');
        $steps = $this->timeline()->forUser(F::TENANT, 7)['steps'];
        self::assertNotSame([], $steps);
    }

    public function testActionsDependOnTheKindOfStep(): void
    {
        $steps = $this->byKey();
        // Étape d'intégration : validation, report, responsable, note ; la facultative peut être ignorée.
        self::assertContains('complete', $steps['integration_step:4']['actions']);
        self::assertContains('postpone', $steps['integration_step:4']['actions']);
        self::assertNotContains('skip', $steps['integration_step:4']['actions'], 'une étape obligatoire ne s’ignore pas');
        self::assertContains('skip', $steps['integration_step:6']['actions']);
        self::assertContains('reopen', $steps['integration_step:1']['actions']);
        // Qualification, campagne, bilan : jamais validés ici.
        foreach (['qualification:1', 'campaign:1', 'enlistment_retro_member:1', 'elevation:1'] as $key) {
            self::assertNotContains('complete', $steps[$key]['actions'], $key);
            self::assertNotContains('skip', $steps[$key]['actions'], $key);
            self::assertContains('postpone', $steps[$key]['actions'], $key);
            self::assertNotSame('', $steps[$key]['locked_reason'], $key);
        }
        self::assertContains('remind', $steps['integration_step:4']['actions'], 'étape du membre : relançable');
        self::assertNotContains('remind', $steps['integration_step:8']['actions'], 'étape d’un instructeur : pas de relance du membre');
    }

    public function testWithoutTheMigrationTheTimelineIsReadOnlyWithNoActions(): void
    {
        $pdo = F::pdo(false);
        F::user($pdo, 7, 'Sgt Marchand');
        F::richMember($pdo, 7);
        $tl = $this->timeline($pdo);
        self::assertTrue($tl->degraded());
        $data = $tl->forUser(F::TENANT, 7);
        self::assertTrue($data['degraded']);
        self::assertNotSame([], $data['steps']);
        foreach ($data['steps'] as $s) {
            self::assertSame([], $s['actions'], $s['key']);
            self::assertFalse($s['editable']);
        }
    }

    public function testFilterByTrackStatusAndText(): void
    {
        $steps = $this->timeline()->forUser(F::TENANT, 7)['steps'];
        self::assertCount(2, MemberDossierTimeline::filter($steps, 'documents'));
        $late = MemberDossierTimeline::filter($steps, '', 'overdue');
        self::assertGreaterThan(1, count($late));
        foreach ($late as $s) {
            self::assertTrue($s['late']);
        }
        self::assertCount(1, MemberDossierTimeline::filter($steps, '', '', 'secourisme'));
        self::assertCount(5, MemberDossierTimeline::filter($steps, '', 'done'));
        self::assertCount(10, MemberDossierTimeline::filter($steps, '', 'open'));
    }

    public function testCsvExportIsEscapedAndNeutralisesFormulas(): void
    {
        $steps = $this->timeline()->forUser(F::TENANT, 7)['steps'];
        $steps[0]['title'] = '=HYPERLINK("http://x","clic")';
        $steps[1]['title'] = 'Titre ; avec "guillemets"' . "\n" . 'et saut de ligne';
        $csv = MemberDossierTimeline::csv($steps);
        self::assertStringStartsWith("\xEF\xBB\xBF\"Parcours\";\"Étape\";\"Statut\"", $csv);
        self::assertStringContainsString('"\'=HYPERLINK', $csv);
        self::assertStringContainsString('"Titre ; avec ""guillemets"" et saut de ligne"', $csv);
        self::assertCount(16, array_filter(explode("\r\n", $csv)));
        self::assertStringContainsString('"En retard"', $csv);
    }

    public function testTeamBoardRowsFilterSortAndTotals(): void
    {
        foreach ([20 => 'Sdt A', 21 => 'Sdt B', 22 => 'Sdt C'] as $id => $n) {
            F::user($this->pdo, $id, $n);
        }
        $iid = F::integration($this->pdo, 20);
        F::integrationStep($this->pdo, $iid, ['title' => 'Charte', 'status' => 'pending', 'due_at' => date('Y-m-d', strtotime('-9 days'))]);
        $iid = F::integration($this->pdo, 21);
        F::integrationStep($this->pdo, $iid, ['title' => 'Fiche', 'status' => 'completed', 'completed_at' => date('Y-m-d H:i:s')]);
        $tl = $this->timeline();
        $board = new MemberDossierTeamBoard($tl, $tl->store());
        $members = [
            ['id' => 7, 'name' => 'Sgt Marchand', 'unit_id' => 1, 'unit_name' => 'Alpha'],
            ['id' => 20, 'name' => 'Sdt A', 'unit_id' => 2, 'unit_name' => 'Bravo'],
            ['id' => 21, 'name' => 'Sdt B', 'unit_id' => 2, 'unit_name' => 'Bravo'],
            ['id' => 22, 'name' => 'Sdt C', 'unit_id' => 1, 'unit_name' => 'Alpha'],
        ];
        $rows = $board->rows(F::TENANT, $members);
        self::assertCount(4, $rows);
        $totals = MemberDossierTeamBoard::totals($rows);
        self::assertSame(4, $totals['members']);
        self::assertSame(2, $totals['late']);
        self::assertSame(1, $totals['complete']);
        $sorted = MemberDossierTeamBoard::sort($rows, 'retard');
        self::assertSame('Sgt Marchand', $sorted[0]['name'], 'le plus de retards en tête');
        self::assertSame(['Sgt Marchand', 'Sdt A'], array_column(MemberDossierTeamBoard::filter($rows, ['etat' => 'retard']), 'name'));
        self::assertSame(['Sdt A', 'Sdt B'], array_column(MemberDossierTeamBoard::filter($rows, ['unit' => 2]), 'name'));
        self::assertSame(['Sgt Marchand'], array_column(MemberDossierTeamBoard::filter($rows, ['track' => 'formation']), 'name'));
        self::assertSame(['Sdt A'], array_column(MemberDossierTeamBoard::filter($rows, ['owner' => 'member', 'q' => 'sdt a']), 'name'));
        $byProgress = MemberDossierTeamBoard::sort($rows, 'progression');
        self::assertSame('Sdt A', $byProgress[0]['name']);
        self::assertSame('Sdt C', end($byProgress)['name'], 'sans étape en dernier');
        $csv = MemberDossierTeamBoard::csv($rows);
        self::assertStringContainsString('"Sgt Marchand";"Alpha";"5";"15"', $csv);
    }
}
