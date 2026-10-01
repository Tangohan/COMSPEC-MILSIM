<?php

declare(strict_types=1);

namespace App\Controllers\Web;

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
        $returnUrl = url('mon-service');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($returnUrl);
        }
        $tenantId = (int) Session::get('tenant_id', 0);
        $taskId = (int) ($params['id'] ?? $request->input('task_id', 0));
        if ($tenantId < 1 || $taskId < 1 || !$this->workTasks->acknowledge($tenantId, $taskId)) {
            Session::flash('error', 'Tâche introuvable ou déjà traitée.');

            return Response::redirect($returnUrl);
        }
        Session::flash('success', 'Tâche accusée réception.');

        return Response::redirect($returnUrl);
    }

    public function startTask(Request $request, array $params = []): Response
    {
        $returnUrl = url('mon-service');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($returnUrl);
        }
        $tenantId = (int) Session::get('tenant_id', 0);
        $taskId = (int) ($params['id'] ?? $request->input('task_id', 0));
        if ($tenantId < 1 || $taskId < 1 || !$this->workTasks->start($tenantId, $taskId)) {
            Session::flash('error', 'Impossible de démarrer cette tâche.');

            return Response::redirect($returnUrl);
        }
        Session::flash('success', 'Tâche en cours.');

        return Response::redirect($returnUrl);
    }

    public function completeTask(Request $request, array $params = []): Response
    {
        $returnUrl = url('mon-service');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($returnUrl);
        }
        $tenantId = (int) Session::get('tenant_id', 0);
        $taskId = (int) ($params['id'] ?? $request->input('task_id', 0));
        if ($tenantId < 1 || $taskId < 1 || !$this->workTasks->complete($tenantId, $taskId)) {
            Session::flash('error', 'Impossible de clôturer cette tâche.');

            return Response::redirect($returnUrl);
        }
        Session::flash('success', 'Tâche terminée.');

        return Response::redirect($returnUrl);
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
