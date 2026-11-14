<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use App\Repositories\MemberNotificationRepository;
use App\Repositories\WorkflowRepository;
use App\Repositories\WorkTaskRepository;

/**
 * Moteur de circuits unique des unités : élévations, mobilités, corrections de dossier, comptes rendus et courrier.
 *
 * Il se greffe sur les tables existantes : la demande reste dans sa table (elevation_requests,
 * personnel_mobility_requests, personnel_correction_requests, aar_reports, courrier_documents) avec son statut, et le moteur porte seulement l'état du circuit, le journal et la tâche
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
    public const SUBJECT_CORRECTION = 'correction';
    public const SUBJECT_AAR = 'aar';
    public const SUBJECT_COURRIER = 'courrier';

    public const STATE_OPINION = 'opinion_pending';
    public const STATE_DECISION = 'decision_pending';
    public const STATE_CLOSED = 'closed';

    public const TASK_TYPE_OPINION = 'workflow_opinion';
    public const NOTIFY_OPINION_REQUEST = 'workflow_opinion_request';
    public const NOTIFY_DECISION = 'workflow_decision';

    /** Statuts métier qui clôturent le circuit, par type d'objet. */
    private const FINAL_STATUSES = [
        self::SUBJECT_ELEVATION => ['approved', 'rejected', 'withdrawn'],
        self::SUBJECT_MOBILITY => ['approved', 'rejected', 'cancelled', 'applied'],
        self::SUBJECT_CORRECTION => ['approved', 'rejected', 'cancelled'],
        self::SUBJECT_AAR => ['validated'],
        // Un courrier refusé repasse en brouillon : seuls l'envoi et l'archivage clôturent son circuit.
        self::SUBJECT_COURRIER => ['sent', 'archived'],
    ];

    /**
     * Statuts dont le membre concerné est averti par le moteur (avis unique dans Activité). Les élévations et les
     * corrections ne sont pas listées : elles ont déjà leur propre avis (cloche des accès, e-mails des corrections).
     */
    private const MEMBER_NOTICES = [
        self::SUBJECT_MOBILITY => ['approved', 'rejected', 'applied'],
        self::SUBJECT_AAR => ['validated', 'pending'],
        self::SUBJECT_COURRIER => ['validated', 'rejected'],
    ];

    private const SUBJECT_LABELS = [
        self::SUBJECT_ELEVATION => ['fr' => 'Demande d’élévation', 'en' => 'Elevation request'],
        self::SUBJECT_MOBILITY => ['fr' => 'Demande de mobilité', 'en' => 'Mobility request'],
        self::SUBJECT_CORRECTION => ['fr' => 'Correction de dossier', 'en' => 'Record correction'],
        self::SUBJECT_AAR => ['fr' => 'Compte rendu de mission', 'en' => 'After-action report'],
        self::SUBJECT_COURRIER => ['fr' => 'Courrier', 'en' => 'Mail'],
    ];

    /** @var (callable(): string)|null */
    private $clock;

    public function __construct(
        private WorkflowRepository $repository,
        private WorkTaskRepository $tasks,
        private ApproverResolver $resolver,
        private MemberNotificationRepository $notifications,
        ?callable $clock = null,
        private ?WorkflowCircuits $circuits = null,
    ) {
        $this->clock = $clock;
    }

    /** Étapes d'avis en vigueur pour ce type de demande (circuit de la communauté ou circuit par défaut). */
    private function stepsFor(int $tenantId, string $subjectType): array
    {
        if ($this->circuits !== null) {
            try {
                return $this->circuits->stepsFor($tenantId, $subjectType);
            } catch (\Throwable $e) {
                error_log('Moteur de circuits : circuit de la communauté illisible : ' . $e->getMessage());
            }
        }

        return WorkflowCircuits::defaultSteps($subjectType);
    }

    /**
     * Première étape à partir de $from qui trouve quelqu'un (une étape sans titulaire est sautée).
     *
     * @param list<array<string, mixed>> $steps
     * @param list<int> $alreadyAsked
     * @return array{0: int, 1: array{user_id: int, label: string, unit_id: int, member_label: string}, 2: string}|null
     */
    private function nextApprover(int $tenantId, array $steps, int $from, int $memberUserId, int $requestedBy, array $alreadyAsked): ?array
    {
        for ($i = $from, $n = count($steps); $i < $n; $i++) {
            $kind = (string) ($steps[$i]['kind'] ?? '');
            $approver = $kind === WorkflowCircuits::KIND_COMMANDER && $alreadyAsked === []
                ? $this->resolver->commanderFor($tenantId, $memberUserId, $requestedBy)
                : $this->resolver->resolveStep($tenantId, $steps[$i], $memberUserId, $requestedBy, $alreadyAsked);
            if ($approver !== null) {
                return [$i, $approver, $kind];
            }
        }

        return null;
    }

    /**
     * Tâche « avis » dans Mon service, événement et notification de la personne sollicitée.
     *
     * @param array{user_id: int, label: string, unit_id: int, member_label: string} $approver
     */
    private function askOpinion(
        int $tenantId,
        int $instanceId,
        string $subjectType,
        array $approver,
        string $kind,
        int $step,
        int $requestedBy,
        string $summary,
        string $now
    ): void {
        $label = self::SUBJECT_LABELS[$subjectType]['fr'];
        $who = match ($kind) {
            WorkflowCircuits::KIND_UPPER => 'Avis de l’échelon supérieur',
            WorkflowCircuits::KIND_USER => 'Avis demandé',
            default => 'Avis du chef d’unité',
        };
        $taskId = $this->tasks->create($tenantId, [
            'type' => self::TASK_TYPE_OPINION,
            'title' => $who . ' : ' . mb_strtolower(mb_substr($label, 0, 1)) . mb_substr($label, 1),
            'description' => mb_substr(trim(
                ($approver['member_label'] !== '' ? 'Membre : ' . $approver['member_label'] . '. ' : '') . trim($summary)
            ), 0, 500),
            'created_by' => $requestedBy > 0 ? $requestedBy : null,
            'assigned_user_id' => $approver['user_id'],
            'assigned_unit_id' => $approver['unit_id'] > 0 ? $approver['unit_id'] : null,
            'priority' => 'normal',
            'linked_entity_type' => 'workflow',
            'linked_entity_id' => $instanceId,
        ]);
        $this->repository->updateInstance($tenantId, $instanceId, [
            'state' => self::STATE_OPINION,
            'assignee_user_id' => $approver['user_id'],
            'task_id' => $taskId,
        ], $now);
        $this->repository->addEvent($tenantId, $instanceId, 'assigned', self::STATE_OPINION, self::STATE_OPINION, null, '', [
            'assignee_user_id' => $approver['user_id'],
            'assignee_label' => $approver['label'],
            'role' => $kind === WorkflowCircuits::KIND_COMMANDER ? 'unit_commander' : $kind,
            'step' => $step,
            'summary' => mb_substr(trim($summary), 0, 300),
        ], $now);
        try {
            $this->notifications->create($tenantId, $approver['user_id'], self::NOTIFY_OPINION_REQUEST, [
                'subject_type' => $subjectType,
                'instance_id' => $instanceId,
                'member_label' => $approver['member_label'],
                'summary' => mb_substr(trim($summary), 0, 300),
            ]);
        } catch (\Throwable $e) {
            error_log('Moteur de circuits : demande d’avis impossible : ' . $e->getMessage());
        }
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
        $steps = $this->stepsFor($tenantId, $subjectType);
        $next = $steps === [] ? null : $this->nextApprover($tenantId, $steps, 0, $memberUserId, $requestedBy, []);
        $state = $next !== null ? self::STATE_OPINION : self::STATE_DECISION;
        $instanceId = $this->repository->createInstance($tenantId, $subjectType, $subjectId, [
            'state' => $state,
            'member_user_id' => $memberUserId,
            'unit_id' => $next[1]['unit_id'] ?? null,
            'assignee_user_id' => $next[1]['user_id'] ?? null,
            'opened_by' => $requestedBy,
        ], $now);
        if ($instanceId < 1) {
            return 0;
        }
        $this->repository->addEvent($tenantId, $instanceId, 'submitted', null, $state, $requestedBy, '', [], $now);
        if ($next !== null) {
            $this->askOpinion($tenantId, $instanceId, $subjectType, $next[1], $next[2], $next[0], $requestedBy, $summary, $now);
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
        // Étape en cours et personnes déjà sollicitées, lues dans le journal du circuit.
        $step = 0;
        $asked = [];
        $summary = '';
        foreach ($this->repository->eventsForInstances($tenantId, [$instanceId])[$instanceId] ?? [] as $event) {
            if ((string) $event['action'] === 'assigned') {
                $step = (int) ($event['payload']['step'] ?? 0);
                $asked[] = (int) ($event['payload']['assignee_user_id'] ?? 0);
                $summary = (string) ($event['payload']['summary'] ?? $summary);
            }
        }
        $subjectType = (string) $instance['subject_type'];
        $memberUserId = (int) ($instance['member_user_id'] ?? 0);
        $requestedBy = (int) ($instance['opened_by'] ?? 0);
        $steps = $this->stepsFor($tenantId, $subjectType);
        $next = $this->nextApprover($tenantId, $steps, $step + 1, $memberUserId, $requestedBy, array_values(array_filter($asked)));
        $to = $next !== null ? self::STATE_OPINION : self::STATE_DECISION;
        $this->repository->addEvent(
            $tenantId,
            $instanceId,
            $favorable ? 'opinion_favorable' : 'opinion_unfavorable',
            self::STATE_OPINION,
            $to,
            $actorUserId,
            $comment,
            ['step' => $step],
            $now
        );
        $this->repository->updateInstance($tenantId, $instanceId, ['state' => self::STATE_DECISION, 'task_id' => null], $now);
        $this->closeTask($tenantId, (int) ($instance['task_id'] ?? 0), 'completed');
        if ($next !== null) {
            $this->askOpinion($tenantId, $instanceId, $subjectType, $next[1], $next[2], $next[0], $requestedBy, $summary, $now);

            return ['ok' => true, 'message' => 'Avis enregistré : la demande passe à l’étape suivante du circuit (' . ($next[1]['label'] !== '' ? $next[1]['label'] : 'avis suivant') . ').'];
        }

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
        string $comment = '',
        int $memberUserId = 0
    ): void {
        if ($tenantId < 1 || $subjectId < 1 || !self::supports($subjectType) || !$this->ready()) {
            return;
        }
        $now = $this->now();
        $instance = $this->repository->findBySubject($tenantId, $subjectType, $subjectId);
        if ($instance === null) {
            $id = $this->repository->createInstance($tenantId, $subjectType, $subjectId, [
                'state' => self::STATE_DECISION,
                'member_user_id' => $memberUserId,
                'opened_by' => $actorUserId,
            ], $now);
            $instance = ['id' => $id, 'state' => self::STATE_DECISION, 'task_id' => null, 'member_user_id' => $memberUserId];
        }
        $instanceId = (int) $instance['id'];
        $from = (string) $instance['state'];
        $final = in_array($status, self::FINAL_STATUSES[$subjectType], true);
        // Un statut non final rouvre un circuit clos (compte rendu validé repassé en relecture, par exemple).
        $to = $final ? self::STATE_CLOSED : self::STATE_DECISION;
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

        $memberId = (int) ($instance['member_user_id'] ?? 0) ?: $memberUserId;
        if ($memberId > 0 && $memberId !== $actorUserId && in_array($status, self::MEMBER_NOTICES[$subjectType] ?? [], true)) {
            try {
                $this->notifications->create($tenantId, $memberId, self::NOTIFY_DECISION, [
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'status' => $status,
                    'comment' => mb_substr(trim($comment), 0, 300),
                ]);
            } catch (\Throwable $e) {
                error_log('Moteur de circuits : avis au membre impossible : ' . $e->getMessage());
            }
        }
    }

    /**
     * Texte de l'avis de décision envoyé au membre (Activité).
     *
     * @param array<string, mixed> $payload
     * @return array{title: string, detail: string, path: string}
     */
    public static function decisionNotice(array $payload, bool $fr = true): array
    {
        $type = (string) ($payload['subject_type'] ?? '');
        $status = (string) ($payload['status'] ?? '');
        $comment = trim((string) ($payload['comment'] ?? ''));
        $title = match ($type . ':' . $status) {
            'mobility:approved' => $fr ? 'Votre demande de mobilité est acceptée' : 'Your mobility request was approved',
            'mobility:applied' => $fr ? 'Votre mutation est effectuée' : 'Your transfer is done',
            'mobility:rejected' => $fr ? 'Votre demande de mobilité est refusée' : 'Your mobility request was declined',
            'aar:validated' => $fr ? 'Votre compte rendu de mission est validé' : 'Your after-action report was approved',
            'aar:pending' => $fr ? 'Votre compte rendu de mission est à reprendre' : 'Your after-action report needs changes',
            'courrier:validated' => $fr ? 'Votre courrier est validé' : 'Your mail item was approved',
            'courrier:rejected' => $fr ? 'Votre courrier est refusé' : 'Your mail item was declined',
            default => self::subjectLabel($type, $fr),
        };
        $subjectId = (int) ($payload['subject_id'] ?? 0);
        $path = match ($type) {
            self::SUBJECT_AAR => 'back-office/atak/comptes-rendus',
            self::SUBJECT_COURRIER => $subjectId > 0 ? 'courrier/editor/' . $subjectId : 'courrier',
            default => 'personnel/mon-espace-rh',
        };

        return ['title' => $title, 'detail' => $comment, 'path' => $path];
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
     * @return array<int, array{state: string, assignee_label: string, opinion: string, opinion_comment: string, opinion_at: string, opinions: list<array{opinion: string, comment: string, at: string, label: string, role: string}>, assignee_role: string}>
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
                'opinions' => [],
                'assignee_role' => '',
            ];
            $role = '';
            foreach ($events[(int) $instance['id']] ?? [] as $event) {
                $action = (string) $event['action'];
                if ($action === 'assigned') {
                    $summary['assignee_label'] = (string) ($event['payload']['assignee_label'] ?? '');
                    $role = (string) ($event['payload']['role'] ?? 'unit_commander');
                    $summary['assignee_role'] = $role;
                } elseif ($action === 'opinion_favorable' || $action === 'opinion_unfavorable') {
                    $summary['opinion'] = $action === 'opinion_favorable' ? 'favorable' : 'unfavorable';
                    $summary['opinion_comment'] = (string) ($event['comment'] ?? '');
                    $summary['opinion_at'] = (string) ($event['created_at'] ?? '');
                    $summary['opinions'][] = [
                        'opinion' => $summary['opinion'],
                        'comment' => $summary['opinion_comment'],
                        'at' => $summary['opinion_at'],
                        'label' => $summary['assignee_label'],
                        'role' => $role,
                    ];
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
