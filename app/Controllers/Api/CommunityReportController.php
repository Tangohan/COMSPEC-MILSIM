<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Community\CommunityReportNotificationService;
use App\Services\Community\CommunityReportService;

/**
 * Signalements contenus hors forum (auth requise, sans sanction forum).
 */
final class CommunityReportController
{
    public function __construct(
        private CommunityReportService $communityReportService,
        private CommunityReportNotificationService $communityReportNotificationService,
    ) {}

    public function submit(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::json(['success' => false, 'error' => 'Connexion requise pour envoyer un signalement.'], 401);
        }

        $input = [];
        $ct = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
        if (str_contains($ct, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode((string) $raw, true);
            $input = is_array($decoded) ? $decoded : [];
        } else {
            $input = $request->all();
        }

        $token = (string) ($input['csrf_token'] ?? $request->input('_csrf_token', ''));
        if (!Csrf::validate($token)) {
            return Response::json(['success' => false, 'error' => 'Session expirée. Rechargez la page puis réessayez.'], 403);
        }

        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $targetType = strtolower(trim((string) ($input['target_type'] ?? '')));

        if ($targetType === 'site_support_request') {
            $gate = Gate::getInstance();
            if (!$gate->allows('admin.organization') && !$gate->allows('admin.access')) {
                return Response::json([
                    'success' => false,
                    'error' => 'Seuls les organisateurs de la communauté peuvent contacter l’administration du site.',
                ], 403);
            }
        }

        $result = $this->communityReportService->submit($tenantId, $userId, $targetType, $input, $host);
        if (!$result['ok']) {
            return Response::json(['success' => false, 'error' => $result['error']], 400);
        }

        try {
            $this->communityReportNotificationService->notifyReportCreated(
                $tenantId,
                (int) ($result['report_id'] ?? 0),
                $userId,
                (string) ($result['reason_preview'] ?? ''),
                $targetType
            );
        } catch (\Throwable) {
        }

        return Response::json(['success' => true]);
    }
}
