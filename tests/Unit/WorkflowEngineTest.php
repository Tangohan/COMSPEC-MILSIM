<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\MemberNotificationRepository;
use App\Repositories\WorkflowRepository;
use App\Repositories\WorkTaskRepository;
use App\Services\Workflow\ApproverResolver;
use App\Services\Workflow\WorkflowEngine;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Moteur de circuits unique : avis du chef d'unité puis décision des responsables, sur une base SQLite jetable.
 */
final class WorkflowEngineTest extends TestCase
{
    private const TENANT = 7;
    private const MEMBER = 41;
    private const COMMANDER = 12;

    private PDO $pdo;

    /** @var list<array{0: int, 1: array<string, mixed>}> */
    private array $createdTasks = [];

    /** @var list<array{0: int, 1: string}> */
    private array $taskStatuses = [];

    /** @var list<array{0: int, 1: string}> */
    private array $notified = [];

    /** @var list<array<string, mixed>> */
    private array $payloads = [];

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE workflow_instances (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, subject_type TEXT NOT NULL, subject_id INTEGER NOT NULL,
            state TEXT NOT NULL, member_user_id INTEGER, unit_id INTEGER, assignee_user_id INTEGER, task_id INTEGER,
            opened_by INTEGER, created_at TEXT, updated_at TEXT, closed_at TEXT, UNIQUE (tenant_id, subject_type, subject_id))');
        $this->pdo->exec('CREATE TABLE workflow_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT, instance_id INTEGER NOT NULL, tenant_id INTEGER NOT NULL, action TEXT NOT NULL,
            from_state TEXT, to_state TEXT, actor_user_id INTEGER, comment TEXT, payload_json TEXT, created_at TEXT)');
        $this->createdTasks = [];
        $this->taskStatuses = [];
        $this->notified = [];
        $this->payloads = [];
    }

    private function engine(?array $commander): WorkflowEngine
    {
        $tasks = $this->createMock(WorkTaskRepository::class);
        $tasks->method('create')->willReturnCallback(function (int $tenantId, array $data): int {
            $this->createdTasks[] = [$tenantId, $data];

            return 500 + count($this->createdTasks);
        });
        $tasks->method('updateStatus')->willReturnCallback(function (int $tenantId, int $taskId, string $status): bool {
            $this->taskStatuses[] = [$taskId, $status];

            return true;
        });
        $resolver = $this->createMock(ApproverResolver::class);
        $resolver->method('commanderFor')->willReturn($commander);
        $notifications = $this->createMock(MemberNotificationRepository::class);
        $notifications->method('create')->willReturnCallback(function (int $tenantId, int $userId, string $type, array $payload = []): int {
            $this->notified[] = [$userId, $type];
            $this->payloads[] = $payload;

            return 1;
        });

        return new WorkflowEngine(
            new WorkflowRepository($this->pdo),
            $tasks,
            $resolver,
            $notifications,
            static fn (): string => '2026-11-14 12:00:00'
        );
    }

    private static function commander(): array
    {
        return ['user_id' => self::COMMANDER, 'label' => 'Cne Martin', 'unit_id' => 3, 'member_label' => 'Sgt Durand'];
    }

    /** @return list<string> */
    private function actions(): array
    {
        return array_map('strval', $this->pdo->query('SELECT action FROM workflow_events ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testOpeningSendsTheRequestToTheUnitCommanderForAnOpinion(): void
    {
        $engine = $this->engine(self::commander());

        $id = $engine->open(self::TENANT, WorkflowEngine::SUBJECT_MOBILITY, 9, self::MEMBER, self::MEMBER, 'Changement d’unité');

        self::assertGreaterThan(0, $id);
        self::assertCount(1, $this->createdTasks);
        $task = $this->createdTasks[0][1];
        self::assertSame(WorkflowEngine::TASK_TYPE_OPINION, $task['type']);
        self::assertSame(self::COMMANDER, $task['assigned_user_id']);
        self::assertSame('workflow', $task['linked_entity_type']);
        self::assertSame($id, $task['linked_entity_id']);
        self::assertStringContainsString('Sgt Durand', (string) $task['description']);
        self::assertSame([[self::COMMANDER, WorkflowEngine::NOTIFY_OPINION_REQUEST]], $this->notified);
        self::assertSame(['submitted', 'assigned'], $this->actions());

        // Une seconde ouverture (double envoi) ne crée ni circuit ni tâche.
        self::assertSame($id, $engine->open(self::TENANT, WorkflowEngine::SUBJECT_MOBILITY, 9, self::MEMBER, self::MEMBER));
        self::assertCount(1, $this->createdTasks);
    }

    public function testWithoutCommandChainTheRequestGoesStraightToDecision(): void
    {
        $engine = $this->engine(null);

        $id = $engine->open(self::TENANT, WorkflowEngine::SUBJECT_ELEVATION, 4, self::MEMBER, self::MEMBER);

        self::assertGreaterThan(0, $id);
        self::assertSame([], $this->createdTasks);
        self::assertSame([], $this->notified);
        $summary = $engine->summaries(self::TENANT, WorkflowEngine::SUBJECT_ELEVATION, [4])[4];
        self::assertSame(WorkflowEngine::STATE_DECISION, $summary['state']);
        self::assertSame('', $summary['opinion']);
    }

    public function testOnlyTheDesignatedCommanderGivesTheOpinionOnce(): void
    {
        $engine = $this->engine(self::commander());
        $id = $engine->open(self::TENANT, WorkflowEngine::SUBJECT_ELEVATION, 4, self::MEMBER, self::MEMBER);

        self::assertFalse($engine->giveOpinion(self::TENANT, $id, 99, true)['ok'], 'quelqu’un d’autre');
        self::assertFalse($engine->giveOpinion(self::TENANT + 1, $id, self::COMMANDER, true)['ok'], 'autre communauté');
        self::assertFalse($engine->giveOpinion(self::TENANT, $id, self::COMMANDER, false, '  ')['ok'], 'défavorable sans motif');

        $ok = $engine->giveOpinion(self::TENANT, $id, self::COMMANDER, false, 'Pas encore prêt.');
        self::assertTrue($ok['ok']);
        self::assertSame([[501, 'completed']], $this->taskStatuses);
        self::assertFalse($engine->giveOpinion(self::TENANT, $id, self::COMMANDER, true)['ok'], 'avis déjà donné');

        $summary = $engine->summaries(self::TENANT, WorkflowEngine::SUBJECT_ELEVATION, [4])[4];
        self::assertSame('unfavorable', $summary['opinion']);
        self::assertSame('Pas encore prêt.', $summary['opinion_comment']);
        self::assertSame('Cne Martin', $summary['assignee_label']);
        self::assertSame(WorkflowEngine::STATE_DECISION, $summary['state']);
    }

    public function testDecisionBeforeTheOpinionClosesTheCircuitAndCancelsTheTask(): void
    {
        $engine = $this->engine(self::commander());
        $id = $engine->open(self::TENANT, WorkflowEngine::SUBJECT_ELEVATION, 4, self::MEMBER, self::MEMBER);

        // Mise en examen : l'avis reste attendu.
        $engine->recordStatus(self::TENANT, WorkflowEngine::SUBJECT_ELEVATION, 4, 'in_review', 2);
        self::assertSame(WorkflowEngine::STATE_OPINION, $engine->summaries(self::TENANT, WorkflowEngine::SUBJECT_ELEVATION, [4])[4]['state']);

        $engine->recordStatus(self::TENANT, WorkflowEngine::SUBJECT_ELEVATION, 4, 'approved', 2, 'Accordé');

        $summary = $engine->summaries(self::TENANT, WorkflowEngine::SUBJECT_ELEVATION, [4])[4];
        self::assertSame(WorkflowEngine::STATE_CLOSED, $summary['state']);
        self::assertSame('skipped', $summary['opinion']);
        self::assertSame([[501, 'cancelled']], $this->taskStatuses);
        self::assertNull($engine->findOpinionRequest(self::TENANT, $id, self::COMMANDER));
        self::assertSame(['submitted', 'assigned', 'status:in_review', 'opinion_skipped', 'status:approved'], $this->actions());
    }

    public function testStatusOnARequestWithoutCircuitOpensAClosedOneForTheJournal(): void
    {
        $engine = $this->engine(self::commander());

        $engine->recordStatus(self::TENANT, WorkflowEngine::SUBJECT_MOBILITY, 12, 'approved', 2);
        $engine->recordStatus(self::TENANT, WorkflowEngine::SUBJECT_MOBILITY, 12, 'applied', 2);

        self::assertSame([], $this->createdTasks);
        $closed = $this->pdo->query('SELECT state, closed_at FROM workflow_instances')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(WorkflowEngine::STATE_CLOSED, $closed['state']);
        self::assertSame('2026-11-14 12:00:00', $closed['closed_at']);
        self::assertSame(['status:approved', 'status:applied'], $this->actions());
    }

    public function testUnknownSubjectsAreIgnored(): void
    {
        $engine = $this->engine(self::commander());

        self::assertSame(0, $engine->open(self::TENANT, 'inconnu', 1, self::MEMBER, self::MEMBER));
        $engine->recordStatus(self::TENANT, 'inconnu', 1, 'sent', 2);

        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM workflow_instances')->fetchColumn());
    }

    public function testCorrectionsJoinTheJournalWithoutCommanderOpinion(): void
    {
        $engine = $this->engine(self::commander());

        $id = $engine->open(self::TENANT, WorkflowEngine::SUBJECT_CORRECTION, 5, self::MEMBER, 2, 'Grade erroné');
        self::assertGreaterThan(0, $id);
        self::assertSame([], $this->createdTasks, 'pas d’avis du chef pour une correction');
        $engine->recordStatus(self::TENANT, WorkflowEngine::SUBJECT_CORRECTION, 5, 'approved', 3, 'OK');

        self::assertSame(WorkflowEngine::STATE_CLOSED, $engine->summaries(self::TENANT, WorkflowEngine::SUBJECT_CORRECTION, [5])[5]['state']);
        self::assertSame([], $this->notified, 'la correction prévient déjà le membre elle-même');
        self::assertSame(['submitted', 'status:approved'], $this->actions());
    }

    public function testAarAuthorIsToldWhenTheReportIsValidatedOrSentBack(): void
    {
        $engine = $this->engine(self::commander());
        $engine->open(self::TENANT, WorkflowEngine::SUBJECT_AAR, 8, self::MEMBER, self::MEMBER, 'Op Granite');
        self::assertSame([], $this->createdTasks);

        $engine->recordStatus(self::TENANT, WorkflowEngine::SUBJECT_AAR, 8, 'in_review', 2);
        self::assertSame([], $this->notified, 'la mise en relecture ne dérange pas l’auteur');

        $engine->recordStatus(self::TENANT, WorkflowEngine::SUBJECT_AAR, 8, 'validated', 2, 'Bon travail');
        self::assertSame([[self::MEMBER, WorkflowEngine::NOTIFY_DECISION]], $this->notified);
        self::assertSame(WorkflowEngine::STATE_CLOSED, $engine->summaries(self::TENANT, WorkflowEngine::SUBJECT_AAR, [8])[8]['state']);

        $notice = WorkflowEngine::decisionNotice($this->payloads[0]);
        self::assertSame('Votre compte rendu de mission est validé', $notice['title']);
        self::assertSame('Bon travail', $notice['detail']);
        self::assertSame('back-office/atak/comptes-rendus', $notice['path']);

        // Renvoyé à l'auteur : le circuit se rouvre et l'auteur est prévenu.
        $engine->recordStatus(self::TENANT, WorkflowEngine::SUBJECT_AAR, 8, 'pending', 2);
        self::assertSame(WorkflowEngine::STATE_DECISION, $engine->summaries(self::TENANT, WorkflowEngine::SUBJECT_AAR, [8])[8]['state']);
        self::assertCount(2, $this->notified);

        // L'auteur qui valide lui-même ne se notifie pas.
        $engine->recordStatus(self::TENANT, WorkflowEngine::SUBJECT_AAR, 8, 'validated', self::MEMBER);
        self::assertCount(2, $this->notified);
    }

    public function testMobilityDecisionNoticeGoesToTheMember(): void
    {
        $engine = $this->engine(null);
        $engine->open(self::TENANT, WorkflowEngine::SUBJECT_MOBILITY, 3, self::MEMBER, self::MEMBER);
        $engine->recordStatus(self::TENANT, WorkflowEngine::SUBJECT_MOBILITY, 3, 'applied', 2);

        self::assertSame([[self::MEMBER, WorkflowEngine::NOTIFY_DECISION]], $this->notified);
        $notice = WorkflowEngine::decisionNotice($this->payloads[0], false);
        self::assertSame('Your transfer is done', $notice['title']);
        self::assertSame('personnel/mon-espace-rh', $notice['path']);
    }

    public function testCourrierCircuitFollowsItsStepsAndTellsTheAuthor(): void
    {
        $engine = $this->engine(self::commander());

        $engine->recordStatus(self::TENANT, WorkflowEngine::SUBJECT_COURRIER, 21, 'pending_validation', self::MEMBER, '', self::MEMBER);
        self::assertSame([], $this->createdTasks, 'pas d’avis du chef pour un courrier');
        self::assertSame([], $this->notified, 'l’auteur qui soumet ne se notifie pas');

        $engine->recordStatus(self::TENANT, WorkflowEngine::SUBJECT_COURRIER, 21, 'rejected', 2, 'Référence manquante', self::MEMBER);
        self::assertSame(WorkflowEngine::STATE_DECISION, $engine->summaries(self::TENANT, WorkflowEngine::SUBJECT_COURRIER, [21])[21]['state']);
        $notice = WorkflowEngine::decisionNotice($this->payloads[0]);
        self::assertSame('Votre courrier est refusé', $notice['title']);
        self::assertSame('Référence manquante', $notice['detail']);
        self::assertSame('courrier/editor/21', $notice['path']);

        foreach (['draft', 'pending_validation', 'validated', 'signed', 'sent'] as $status) {
            $engine->recordStatus(self::TENANT, WorkflowEngine::SUBJECT_COURRIER, 21, $status, 2);
        }
        self::assertSame(WorkflowEngine::STATE_CLOSED, $engine->summaries(self::TENANT, WorkflowEngine::SUBJECT_COURRIER, [21])[21]['state']);
        self::assertSame([[self::MEMBER, WorkflowEngine::NOTIFY_DECISION], [self::MEMBER, WorkflowEngine::NOTIFY_DECISION]], $this->notified);
        self::assertSame('Votre courrier est validé', WorkflowEngine::decisionNotice($this->payloads[1])['title']);
    }
}
