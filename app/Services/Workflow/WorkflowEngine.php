<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use App\Repositories\MemberNotificationRepository;
use App\Repositories\WorkflowRepository;
use App\Repositories\WorkTaskRepository;

/**
 * Moteur de circuits unique des unités (lot 1 : élévations et mobilités).
 *
 * Il se greffe sur les tables existantes : la demande reste dans sa table (elevation_requests,
 * personnel_mobility_requests) avec son statut, et le moteur porte seulement l'état du circuit, le journal et la tâche
 * en attente (work_tasks, affichée dans Mon service).
 *
 * Circuit : à l'ouverture, le chef de l'unité du membre (chaîne de commandement) reçoit une tâche « avis » ; son avis
 * favorable ou défavorable est journalisé puis la demande passe aux responsables habilités, qui décident sur les écrans
 * existants. Sans chef trouvé, la demande va directement à la décision. Une décision prise avant l'avis le clôt
 * (« décidé sans avis »). Les droits de décision ne changent pas : le chef d'unité donne un avis, il n'accorde rien.
 */
class WorkflowEngine
{
    public const SUBJECT_ELEVATION = 'elevation';
    public const SUBJECT_MOBILITY = 'mobility';

    public const STATE_OPINION = 'opinion_pending';
    public const STATE_DECISION = 'decision_pending';
    public const STATE_CLOSED = 'closed';

    public const TASK_TYPE_OPINION = 'workflow_opinion';
    public const NOTIFY_OPINION_REQUEST = 'workflow_opinion_request';

    /** Statuts métier qui clôturent le circuit, par type d'objet. */
    private const FINAL_STATUSES = [
        self::SUBJECT_ELEVATION => ['approved', 'rejected', 'withdrawn'],
        self::SUBJECT_MOBILITY => ['approved', 'rejected', 'cancelled', 'applied'],
    ];

    private const SUBJECT_LABELS = [
        self::SUBJECT_ELEVATION => ['fr' => 'Demande d’élévation', 'en' => 'Elevation request'],
        self::SUBJECT_MOBILITY => ['fr' => 'Demande de mobilité', 'en' => 'Mobility request'],
    ];

    /** @var (callable(): string)|null */
    private $clock;

    public function __construct(
        private WorkflowRepository $repository,
        private WorkTaskRepository $tasks,
        private ApproverResolver $resolver,
        private MemberNotificationRepository $notifications,
        ?callable $clock = null,
    ) {
        $this->clock = $clock;
    }

    public function ready(): bool
    {
        return $this->repository->tablesReady();
    }

    public static function supports(string $subjectType): bool
    {
        return isset(self::FINAL_STATUSES[$subjectType]);
    }

    /**
     * Ouvre le circuit d'une demande qui vient d'être créée. Rien n'est fait si un circuit existe déjà.
     *
     * @return int identifiant du circuit (0 si rien n'a été ouvert)
     */
    public function open(
        int $tenantId,
        string $subjectType,
        int $subjectId,
        int $memberUserId,
        int $requestedBy,
        string $summary = ''
    ): int {
        if ($tenantId < 1 || $subjectId < 1 || !self::supports($subjectType) || !$this->ready()) {
            return 0;
        }
        $existing = $this->repository->findBySubject($tenantId, $subjectType, $subjectId);
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        $now = $this->now();
        $commander = $this->resolver->commanderFor($tenantId, $memberUserId, $requestedBy);
        $state = $commander !== null ? self::STATE_OPINION : self::STATE_DECISION;
        $instanceId = $this->repository->createInstance($tenantId, $subjectType, $subjectId, [
            'state' => $state,
            'member_user_id' => $memberUserId,
            'unit_id' => $commander['unit_id'] ?? null,
            'assignee_user_id' => $commander['user_id'] ?? null,
            'opened_by' => $requestedBy,
        ], $now);
        if ($instanceId < 1) {
            return 0;
        }
        $this->repository->addEvent($tenantId, $instanceId, 'submitted', null, $state, $requestedBy, '', [], $now);
        if ($commander === null) {
            return $instanceId;
        }

        $label = self::SUBJECT_LABELS[$subjectType]['fr'];
        $taskId = $this->tasks->create($tenantId, [
            'type' => self::TASK_TYPE_OPINION,
            'title' => 'Avis du chef d’unité : ' . mb_strtolower(mb_substr($label, 0, 1)) . mb_substr($label, 1),
            'description' => mb_substr(trim(
                ($commander['member_label'] !== '' ? 'Membre : ' . $commander['member_label'] . '. ' : '') . trim($summary)
            ), 0, 500),
            'created_by' => $requestedBy > 0 ? $requestedBy : null,
            'assigned_user_id' => $commander['user_id'],
            'assigned_unit_id' => $commander['unit_id'] > 0 ? $commander['unit_id'] : null,
            'priority' => 'normal',
            'linked_entity_type' => 'workflow',
            'linked_entity_id' => $instanceId,
        ]);
        $this->repository->updateInstance($tenantId, $instanceId, ['task_id' => $taskId], $now);
        $this->repository->addEvent($tenantId, $instanceId, 'assigned', $state, $state, null, '', [
            'assignee_user_id' => $commander['user_id'],
            'assignee_label' => $commander['label'],
            'role' => 'unit_commander',
        ], $now);
        try {
            $this->notifications->create($tenantId, $commander['user_id'], self::NOTIFY_OPINION_REQUEST, [
                'subject_type' => $subjectType,
                'instance_id' => $instanceId,
                'member_label' => $commander['member_label'],
                'summary' => mb_substr(trim($summary), 0, 300),
            ]);
        } catch (\Throwable $e) {
            error_log('Moteur de circuits : avis au chef d’unité impossible : ' . $e->getMessage());
        }

        return $instanceId;
    }

    /**
     * Avis du chef d'unité. Seule la personne désignée sur le circuit peut le donner, une seule fois.
     *
     * @return array{ok: bool, message: string}
     */
    public function giveOpinion(int $tenantId, int $instanceId, int $actorUserId, bool $favorable, string $comment = ''): array
    {
        if (!$this->ready()) {
            return ['ok' => false, 'message' => 'Le circuit des demandes arrive avec la prochaine mise à jour.'];
        }
        $instance = $this->repository->findById($tenantId, $instanceId);
        if ($instance === null || (int) ($instance['assignee_user_id'] ?? 0) !== $actorUserId || $actorUserId < 1) {
            return ['ok' => false, 'message' => 'Demande introuvable ou avis demandé à quelqu’un d’autre.'];
        }
        if ((string) $instance['state'] !== self::STATE_OPINION) {
            return ['ok' => false, 'message' => 'L’avis n’est plus attendu : la demande a déjà avancé.'];
        }
        $comment = mb_substr(trim($comment), 0, 500);
        if (!$favorable && $comment === '') {
            return ['ok' => false, 'message' => 'Expliquez en une phrase pourquoi l’avis est défavorable.'];
        }
        $now = $this->now();
        $this->repository->addEvent(
            $tenantId,
            $instanceId,
            $favorable ? 'opinion_favorable' : 'opinion_unfavorable',
            self::STATE_OPINION,
            self::STATE_DECISION,
            $actorUserId,
            $comment,
            [],
            $now
        );
        $this->repository->updateInstance($tenantId, $instanceId, ['state' => self::STATE_DECISION, 'task_id' => null], $now);
        $this->closeTask($tenantId, (int) ($instance['task_id'] ?? 0), 'completed');

        return ['ok' => true, 'message' => 'Avis enregistré : les responsables des effectifs le verront au moment de décider.'];
    }

    /**
     * Note un changement de statut fait sur l'objet métier (écrans existants). Ouvre le circuit s'il manque.
     */
    public function recordStatus(
        int $tenantId,
        string $subjectType,
        int $subjectId,
        string $status,
        int $actorUserId,
        string $comment = ''
    ): void {
        if ($tenantId < 1 || $subjectId < 1 || !self::supports($subjectType) || !$this->ready()) {
            return;
        }
        $now = $this->now();
        $instance = $this->repository->findBySubject($tenantId, $subjectType, $subjectId);
        if ($instance === null) {
            $id = $this->repository->createInstance($tenantId, $subjectType, $subjectId, [
                'state' => self::STATE_DECISION,
                'opened_by' => $actorUserId,
            ], $now);
            $instance = ['id' => $id, 'state' => self::STATE_DECISION, 'task_id' => null];
        }
        $instanceId = (int) $instance['id'];
        $from = (string) $instance['state'];
        $final = in_array($status, self::FINAL_STATUSES[$subjectType], true);
        $to = $final ? self::STATE_CLOSED : ($from === self::STATE_CLOSED ? self::STATE_CLOSED : self::STATE_DECISION);
        if ($from === self::STATE_OPINION && $final) {
            $this->repository->addEvent($tenantId, $instanceId, 'opinion_skipped', $from, $to, $actorUserId, '', [], $now);
            $this->closeTask($tenantId, (int) ($instance['task_id'] ?? 0), 'cancelled');
        }
        if ($from === self::STATE_OPINION && !$final) {
            // Mise en examen par le bureau : l'avis du chef reste attendu.
            $to = self::STATE_OPINION;
        }
        $this->repository->addEvent($tenantId, $instanceId, 'status:' . $status, $from, $to, $actorUserId, mb_substr(trim($comment), 0, 500), [], $now);
        $changes = ['state' => $to];
        if ($final && $from !== self::STATE_CLOSED) {
            $changes['closed'] = true;
            $changes['task_id'] = null;
        }
        $this->repository->updateInstance($tenantId, $instanceId, $changes, $now);
    }

    /**
     * Circuit ouvert à un acteur pour donner son avis (page Mon service).
     *
     * @return array<string, mixed>|null
     */
    public function findOpinionRequest(int $tenantId, int $instanceId, int $actorUserId): ?array
    {
        if (!$this->ready() || $actorUserId < 1) {
            return null;
        }
        $instance = $this->repository->findById($tenantId, $instanceId);
        if ($instance === null || (int) ($instance['assignee_user_id'] ?? 0) !== $actorUserId
            || (string) $instance['state'] !== self::STATE_OPINION) {
            return null;
        }

        return $instance;
    }

    /**
     * Résumé du circuit de chaque demande, pour les listes du bureau des effectifs.
     *
     * @param list<int> $subjectIds
     * @return array<int, array{state: string, assignee_label: string, opinion: string, opinion_comment: string, opinion_at: string}>
     */
    public function summaries(int $tenantId, string $subjectType, array $subjectIds): array
    {
        if (!self::supports($subjectType) || !$this->ready()) {
            return [];
        }
        $instances = $this->repository->findManyBySubject($tenantId, $subjectType, $subjectIds);
        if ($instances === []) {
            return [];
        }
        $events = $this->repository->eventsForInstances($tenantId, array_map(static fn (array $i): int => (int) $i['id'], array_values($instances)));
        $out = [];
        foreach ($instances as $subjectId => $instance) {
            $summary = [
                'state' => (string) $instance['state'],
                'assignee_label' => '',
                'opinion' => '',
                'opinion_comment' => '',
                'opinion_at' => '',
            ];
            foreach ($events[(int) $instance['id']] ?? [] as $event) {
                $action = (string) $event['action'];
                if ($action === 'assigned') {
                    $summary['assignee_label'] = (string) ($event['payload']['assignee_label'] ?? '');
                } elseif ($action === 'opinion_favorable' || $action === 'opinion_unfavorable') {
                    $summary['opinion'] = $action === 'opinion_favorable' ? 'favorable' : 'unfavorable';
                    $summary['opinion_comment'] = (string) ($event['comment'] ?? '');
                    $summary['opinion_at'] = (string) ($event['created_at'] ?? '');
                } elseif ($action === 'opinion_skipped') {
                    $summary['opinion'] = 'skipped';
                }
            }
            $out[$subjectId] = $summary;
        }

        return $out;
    }

    public static function subjectLabel(string $subjectType, bool $fr = true): string
    {
        return self::SUBJECT_LABELS[$subjectType][$fr ? 'fr' : 'en'] ?? ($fr ? 'Demande' : 'Request');
    }

    private function closeTask(int $tenantId, int $taskId, string $status): void
    {
        if ($taskId < 1) {
            return;
        }
        try {
            $this->tasks->updateStatus($tenantId, $taskId, $status);
        } catch (\Throwable $e) {
            error_log('Moteur de circuits : tâche #' . $taskId . ' non fermée : ' . $e->getMessage());
        }
    }

    private function now(): string
    {
        return $this->clock !== null ? ($this->clock)() : date('Y-m-d H:i:s');
    }
}
