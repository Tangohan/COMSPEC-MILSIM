<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CommunityEventRepository;
use App\Repositories\UserRepository;
use App\Services\Attendance\CommunityEventAttendanceService;
use App\Services\Platform\FeatureGateService;
use App\Services\Portal\MemberServiceContextService;
use App\Services\Portal\UnifiedActionDigestService;
use App\Services\Workflow\WorkflowEngine;
use App\Services\Workflow\WorkTaskService;

final class ActionCenterController
{
    public function __construct(
        private UnifiedActionDigestService $digest,
        private MemberServiceContextService $memberService,
        private WorkTaskService $workTasks,
        private UserRepository $userRepository,
        private CommunityEventRepository $eventRepository,
        private CommunityEventAttendanceService $attendance,
        private FeatureGateService $featureGate,
        private ?WorkflowEngine $workflow = null,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        $userRow = $this->userRepository->findById($userId);
        $email = trim((string) ($userRow['email'] ?? Session::get('email') ?? ''));

        $roleSlug = $this->userRepository->getRoleSlugForUser($userId) ?? '';
        $staffSlugs = ['recruiter', 'community_owner', 'hr', 'tenant_admin'];
        $showStaffRecruitment = $gate->allows('admin.organization') || $gate->allows('admin.access')
            || in_array($roleSlug, $staffSlugs, true);

        $digestPayload = $this->digest->buildActionCenter(
            $tenantId,
            $userId,
            $email,
            $gate,
            $showStaffRecruitment
        );
        $serviceContext = $this->memberService->build($tenantId, $userId, $roleSlug);
        $taskCount = count($serviceContext['tasks'] ?? []);
        $digestPayload['total_attention'] = (int) ($digestPayload['total_attention'] ?? 0) + $taskCount;

        return Response::view('layout.main', [
            'title' => 'Mon service — Athena',
            'content' => 'portal.action_center',
            'action_center_digest' => $digestPayload,
            'mon_service' => $serviceContext,
        ]);
    }

    public function acknowledgeTask(Request $request, array $params = []): Response
    {
        return $this->changeTask($request, $params, 'acknowledged', 'Tâche accusée réception.', 'Tâche introuvable ou déjà traitée.');
    }

    public function startTask(Request $request, array $params = []): Response
    {
        return $this->changeTask($request, $params, 'in_progress', 'Tâche en cours.', 'Impossible de démarrer cette tâche.');
    }

    public function completeTask(Request $request, array $params = []): Response
    {
        return $this->changeTask($request, $params, 'completed', 'Tâche terminée.', 'Impossible de clôturer cette tâche.');
    }

    /**
     * Avis du chef d'unité sur une demande (élévation, mobilité) : favorable ou défavorable, avec un commentaire.
     */
    public function giveOpinion(Request $request, array $params = []): Response
    {
        $returnUrl = url('mon-service');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($returnUrl);
        }
        $tenantId = (int) Session::get('tenant_id', 0);
        $userId = (int) Session::get('user_id', 0);
        $instanceId = (int) ($params['id'] ?? 0);
        $choice = (string) $request->input('avis', '');
        if ($tenantId < 1 || $userId < 1 || $instanceId < 1 || !in_array($choice, ['favorable', 'defavorable'], true)) {
            Session::flash('error', 'Choisissez un avis favorable ou défavorable.');

            return Response::redirect($returnUrl);
        }
        $result = $this->workflow()->giveOpinion(
            $tenantId,
            $instanceId,
            $userId,
            $choice === 'favorable',
            (string) $request->input('commentaire', '')
        );
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect($returnUrl);
    }

    /**
     * Seules les tâches de « Mon service » de la personne connectée (à son nom, à son billet ou à sa permanence)
     * peuvent changer d'état. Une tâche d'avis se clôt en donnant l'avis, pas par « Terminer ».
     *
     * @param array<string, mixed> $params
     */
    private function changeTask(Request $request, array $params, string $status, string $success, string $failure): Response
    {
        $returnUrl = url('mon-service');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($returnUrl);
        }
        $tenantId = (int) Session::get('tenant_id', 0);
        $userId = (int) Session::get('user_id', 0);
        $taskId = (int) ($params['id'] ?? $request->input('task_id', 0));
        $task = $tenantId > 0 && $userId > 0 && $taskId > 0 ? $this->ownTask($tenantId, $userId, $taskId) : null;
        $isOpinion = $task !== null && (string) ($task['type'] ?? '') === WorkflowEngine::TASK_TYPE_OPINION;
        $ok = $task !== null && !($isOpinion && $status === 'completed') && match ($status) {
            'acknowledged' => $this->workTasks->acknowledge($tenantId, $taskId),
            'in_progress' => $this->workTasks->start($tenantId, $taskId),
            default => $this->workTasks->complete($tenantId, $taskId),
        };
        Session::flash($ok ? 'success' : 'error', $ok ? $success : $failure);

        return Response::redirect($returnUrl);
    }

    /** @return array<string, mixed>|null */
    private function ownTask(int $tenantId, int $userId, int $taskId): ?array
    {
        $roleSlug = $this->userRepository->getRoleSlugForUser($userId) ?? '';
        $context = $this->memberService->build($tenantId, $userId, $roleSlug);
        foreach (is_array($context['tasks'] ?? null) ? $context['tasks'] : [] as $task) {
            if (is_array($task) && (int) ($task['id'] ?? 0) === $taskId) {
                return $task;
            }
        }

        return null;
    }

    private function workflow(): WorkflowEngine
    {
        if ($this->workflow === null) {
            $engine = Container::get(WorkflowEngine::class);
            assert($engine instanceof WorkflowEngine);
            $this->workflow = $engine;
        }

        return $this->workflow;
    }

    public function rsvp(Request $request, array $params = []): Response
    {
        $returnUrl = url('mon-service') . '#agenda-et-echeances';
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($returnUrl);
        }

        $tenantId = (int) Session::get('tenant_id', 0);
        $userId = (int) Session::get('user_id', 0);
        $eventId = (int) $request->input('event_id', 0);
        $status = trim((string) $request->input('status', ''));
        if ($tenantId < 1 || $userId < 1 || !$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            Session::flash('error', 'Le module événements n’est pas accessible.');

            return Response::redirect($returnUrl);
        }
        if (!in_array($status, ['yes', 'no', 'maybe'], true)) {
            Session::flash('error', 'Réponse de participation invalide.');

            return Response::redirect($returnUrl);
        }
        if (!$this->eventRepository->belongsToTenant($eventId, $tenantId)) {
            Session::flash('error', 'Événement introuvable.');

            return Response::redirect($returnUrl);
        }

        $result = $this->attendance->setRsvpWithNotifications($eventId, $userId, $tenantId, $status);
        if (!($result['ok'] ?? false)) {
            Session::flash('error', $result['error'] ?? 'Impossible d’enregistrer la participation.');

            return Response::redirect($returnUrl);
        }
        Session::flash('success', 'Participation enregistrée.');

        return Response::redirect($returnUrl);
    }
}
