<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;

/**
 * Demande d’habilitation transmise par e-mail aux gestionnaires de la communauté courante (tenant).
 */
final class TenantAccessRequestApiController
{
    /** @var array<string, string> */
    private const AREA_LABELS = [
        'overwatch' => 'Vue commandement (Overwatch)',
        'tacmap' => 'Carte tactique (TACMAP)',
        'atak_c2' => 'Données de mission et liaisons (ATAK / outils C2)',
    ];

    public function __construct(
        private AuthService $authService,
        private UserRepository $userRepository,
        private TenantRepository $tenantRepository,
        private EmailService $emailService,
    ) {}

    public function store(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::json(['error' => 'Non autorisé.'], 401);
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            return Response::json(['error' => 'Communauté non sélectionnée.'], 400);
        }

        $body = $this->jsonBody($request);
        $token = $request->input('_csrf_token')
            ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)
            ?? ($body['_csrf_token'] ?? null);
        if (!is_string($token) || !Csrf::validate($token)) {
            return Response::json(['error' => 'Jeton de sécurité invalide ou expiré. Rechargez la page.'], 403);
        }

        $areaKey = (string) ($body['area'] ?? 'overwatch');
        if (!isset(self::AREA_LABELS[$areaKey])) {
            return Response::json(['error' => 'Zone demandée non reconnue.'], 422);
        }
        $reason = trim((string) ($body['reason'] ?? ''));
        if ($reason === '') {
            return Response::json(['error' => 'Indiquez un court motif pour aider les gestionnaires.'], 422);
        }
        if (mb_strlen($reason) > 2000) {
            return Response::json(['error' => 'Le motif est trop long (2000 caractères maximum).'], 422);
        }

        $recipients = $this->userRepository->listEmailsForTenantAccessDelegation($tenantId);
        if ($recipients === []) {
            return Response::json([
                'error' => 'Aucun gestionnaire joignable par e-mail n’est configuré pour cette communauté. Contactez un administrateur autrement.',
            ], 503);
        }

        $tenant = $this->tenantRepository->findById($tenantId);
        $tenantName = (string) ($tenant['name'] ?? 'Communauté');
        $requesterName = trim((string) ($user['display_name'] ?? ''));
        if ($requesterName === '') {
            $requesterName = trim((string) ($user['callsign'] ?? ''));
        }
        if ($requesterName === '') {
            $requesterName = (string) ($user['email'] ?? 'Membre');
        }
        $requesterEmail = strtolower(trim((string) ($user['email'] ?? '')));
        $areaLabel = self::AREA_LABELS[$areaKey];
        $backOfficeUsersUrl = url('back-office/users');

        $sent = 0;
        foreach ($recipients as $to) {
            $ok = $this->emailService->sendTemplated(
                EmailEvents::TENANT_ACCESS_REQUEST,
                'tenant_access_request',
                $to,
                'Demande d’accès — ' . $tenantName,
                [
                    'tenantName' => $tenantName,
                    'areaLabel' => $areaLabel,
                    'reason' => $reason,
                    'requesterName' => $requesterName,
                    'requesterEmail' => $requesterEmail,
                    'backOfficeUsersUrl' => $backOfficeUsersUrl,
                ],
                $tenantId,
                $requesterEmail !== '' ? $requesterEmail : null,
                ['area' => $areaKey, 'requester_user_id' => (int) ($user['id'] ?? 0)]
            );
            if ($ok) {
                $sent++;
            }
        }
        if ($sent === 0) {
            return Response::json([
                'error' => 'L’envoi des messages a échoué. Réessayez plus tard ou prévenez un gestionnaire.',
            ], 502);
        }

        return Response::json(['ok' => true, 'sent' => $sent]);
    }

    /** @return array<string, mixed> */
    private function jsonBody(Request $request): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (str_contains((string) $contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw ?: '[]', true);

            return is_array($decoded) ? $decoded : [];
        }

        return array_merge($request->all(), $_POST);
    }
}
