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
use App\Services\Portal\UnifiedActionDigestService;

final class ActionCenterController
{
    public function __construct(
        private UnifiedActionDigestService $digest,
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

        return Response::view('layout.main', [
            'title' => 'Aujourd’hui — Athena',
            'content' => 'portal.action_center',
            'action_center_digest' => $digestPayload,
        ]);
    }

    public function rsvp(Request $request, array $params = []): Response
    {
        $returnUrl = url('aujourdhui') . '#agenda-et-echeances';
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
