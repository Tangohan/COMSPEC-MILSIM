<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\MemberIntegrationAppointmentRepository;
use App\Repositories\MemberIntegrationRepository;
use App\Repositories\TrainingCompetencyRepository;
use App\Repositories\UserRepository;
use App\Services\MemberIntegration\MemberIntegrationInvitationService;
use App\Services\MemberIntegration\MemberIntegrationService;
use App\Support\MemberIntegrationCatalog;

final class MemberIntegrationController
{
    public function __construct(
        private MemberIntegrationRepository $integrations,
        private MemberIntegrationAppointmentRepository $appointments,
        private MemberIntegrationService $service,
        private MemberIntegrationInvitationService $invitations,
        private TrainingCompetencyRepository $matrices,
        private UserRepository $users,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        $row = $this->integrations->findActiveForUser($tenantId, $userId);
        if ($row) {
            $this->service->refresh($tenantId, (int) $row['id'], $userId);
            $row = $this->integrations->findForTenant($tenantId, (int) $row['id']) ?? $row;
        }
        $user = $this->users->findById($userId, $tenantId) ?? [];
        $steps = $row ? $this->integrations->listSteps($tenantId, (int) $row['id']) : [];
        $visibleSteps = array_values(array_filter($steps, static fn (array $s): bool => !empty($s['is_member_visible'])));
        $events = $row
            ? $this->integrations->listEvents($tenantId, (int) $row['id'], MemberIntegrationCatalog::VISIBILITY_MEMBER)
            : [];
        $referents = $row ? $this->integrations->listReferents($tenantId, (int) $row['id']) : [];
        $dossier = $this->service->dossierSnapshot($userId, $user, $tenantId);

        return Response::view('layout.main', [
            'title' => 'Mon intégration',
            'content' => 'member_integration.index',
            'integration' => $row,
            'steps' => $visibleSteps,
            'events' => $events,
            'referents' => $referents,
            'pendingInvites' => $this->appointments->listPendingForUser($tenantId, $userId),
            'upcoming' => $this->appointments->listUpcomingForUser($tenantId, $userId),
            'groups' => $this->matrices->listAssignmentsForUser($tenantId, $userId),
            'dossier' => $dossier,
            'statusLabels' => MemberIntegrationCatalog::statusLabels(),
            'rsvpLabels' => MemberIntegrationCatalog::rsvpLabels(),
        ]);
    }

    public function calendar(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $id = (int) ($params['id'] ?? 0);
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        $inv = $this->appointments->findInvitationForAppointmentUser($tenantId, $id, $userId);
        if (!$inv) {
            return (new Response())->setStatusCode(404)->setBody('Introuvable.');
        }
        $body = $this->invitations->buildIcs($tenantId, $id);
        if ($body === null) {
            return (new Response())->setStatusCode(404)->setBody('Introuvable.');
        }
        $resp = new Response();
        $resp->header('Content-Type', 'text/calendar; charset=utf-8');
        $resp->header('Content-Disposition', 'attachment; filename="rendez-vous.ics"');
        $resp->setBody($body);

        return $resp;
    }

    public function respondLogged(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1 || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré. Rechargez la page.');

            return Response::redirect(url('mon-integration'));
        }
        $appointmentId = (int) $request->input('appointment_id', 0);
        $inv = $this->appointments->findInvitationForAppointmentUser($tenantId, $appointmentId, $userId);
        if (!$inv) {
            Session::flash('error', 'Invitation introuvable.');

            return Response::redirect(url('mon-integration'));
        }
        $map = [
            'oui' => MemberIntegrationCatalog::RSVP_ACCEPTED,
            'peut-etre' => MemberIntegrationCatalog::RSVP_TENTATIVE,
            'non' => MemberIntegrationCatalog::RSVP_DECLINED,
        ];
        $choice = $map[(string) $request->input('reponse', '')] ?? '';
        if ($choice === '') {
            Session::flash('error', 'Choisissez Oui, Peut-être ou Non.');

            return Response::redirect(url('mon-integration'));
        }
        $this->appointments->updateInvitation($tenantId, (int) $inv['id'], [
            'status' => $choice,
            'responded_at' => date('Y-m-d H:i:s'),
            'response_comment' => trim((string) $request->input('comment', '')) ?: null,
        ]);
        $this->appointments->addInvitationHistory(
            $tenantId,
            (int) $inv['id'],
            $userId,
            (string) ($inv['status'] ?? ''),
            $choice,
            (string) $request->input('comment', '')
        );
        Session::flash('success', 'Votre réponse a été enregistrée.');

        return Response::redirect(url('mon-integration'));
    }
}
