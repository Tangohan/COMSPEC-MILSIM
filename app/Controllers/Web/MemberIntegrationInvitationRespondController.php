<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\MemberIntegration\MemberIntegrationInvitationService;
use App\Services\MemberIntegration\MemberIntegrationService;
use App\Support\MemberIntegrationCatalog;

final class MemberIntegrationInvitationRespondController
{
    public function __construct(
        private MemberIntegrationInvitationService $invitations,
        private MemberIntegrationService $service,
        private \App\Repositories\MemberIntegrationAppointmentRepository $appointmentRepo,
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        $token = trim((string) $request->query('token', ''));
        $hash = $token !== '' ? MemberIntegrationInvitationService::hashToken($token) : '';
        $raw = $hash !== '' ? $this->appointmentRepo->findInvitationByTokenHash($hash) : null;
        $expired = false;
        if ($raw) {
            $expires = strtotime((string) ($raw['token_expires_at'] ?? ''));
            $expired = !empty($raw['revoked_at'])
                || ($expires !== false && $expires < time())
                || (string) ($raw['appointment_status'] ?? '') === MemberIntegrationCatalog::APPT_CANCELLED;
        }
        $row = ($raw && !$expired) ? [
            'title' => (string) ($raw['appointment_title'] ?? ''),
            'starts_at' => (string) ($raw['starts_at'] ?? ''),
            'location' => (string) ($raw['location'] ?? ''),
        ] : null;

        return Response::view('layout.main', [
            'title' => 'Répondre à l’invitation',
            'content' => 'member_integration.respond',
            'token' => $token,
            'invitation' => $row,
            'generic' => $row === null,
            'rsvpLabels' => MemberIntegrationCatalog::rsvpLabels(),
        ]);
    }

    public function submit(Request $request, array $params = []): Response
    {
        $token = trim((string) $request->input('token', ''));
        if (Session::get('user_id') && !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré. Rechargez la page.');

            return Response::redirect(url('integration/invitation/repondre') . '?token=' . rawurlencode($token));
        }
        $map = [
            'oui' => MemberIntegrationCatalog::RSVP_ACCEPTED,
            'peut-etre' => MemberIntegrationCatalog::RSVP_TENTATIVE,
            'non' => MemberIntegrationCatalog::RSVP_DECLINED,
        ];
        $choice = $map[(string) $request->input('reponse', '')] ?? '';
        $res = $this->invitations->respondWithToken(
            $token,
            $choice,
            (string) $request->input('comment', ''),
            Session::get('user_id') ? (int) Session::get('user_id') : null
        );
        if (!empty($res['ok']) && !empty($res['invitation']['integration_id'])) {
            $tenantId = (int) ($res['invitation']['tenant_id'] ?? 0);
            $this->service->refresh($tenantId, (int) $res['invitation']['integration_id'], null);
        }
        Session::flash(!empty($res['ok']) ? 'success' : 'error', (string) ($res['message'] ?? ''));

        return Response::redirect(url('integration/invitation/repondre') . '?token=' . rawurlencode($token));
    }

    public function calendar(Request $request, array $params = []): Response
    {
        $token = trim((string) $request->query('token', ''));
        $row = $token !== ''
            ? $this->appointmentRepo->findInvitationByTokenHash(MemberIntegrationInvitationService::hashToken($token))
            : null;
        if (!$row || !empty($row['revoked_at'])) {
            return (new Response())->setStatusCode(404)->setBody('Introuvable.');
        }
        $body = $this->invitations->buildIcs((int) $row['tenant_id'], (int) $row['appointment_id']);
        if ($body === null) {
            return (new Response())->setStatusCode(404)->setBody('Introuvable.');
        }
        $resp = new Response();
        $resp->header('Content-Type', 'text/calendar; charset=utf-8');
        $resp->header('Content-Disposition', 'attachment; filename="rendez-vous.ics"');
        $resp->setBody($body);

        return $resp;
    }
}
