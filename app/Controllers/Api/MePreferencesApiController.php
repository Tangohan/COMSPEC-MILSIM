<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserUiPreferencesRepository;
use App\Services\Auth\AuthService;
use App\Services\Profile\UserUiPreferencesValidationService;

/**
 * GET/PATCH /api/me/preferences — préférences UI et notifications (JSON).
 */
class MePreferencesApiController
{
    public function __construct(
        private AuthService $authService,
        private UserUiPreferencesRepository $uiPreferencesRepository,
        private UserNotificationPreferencesRepository $notificationPreferencesRepository,
        private UserUiPreferencesValidationService $validationService
    ) {}

    public function handle(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::json(['error' => 'Non autorisé.'], 401);
        }
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::json(['error' => 'Communauté non sélectionnée.'], 400);
        }
        $tenantId = (int) $tenantId;
        $userId = (int) $user['id'];

        if ($request->method() === 'GET') {
            $ui = $this->uiPreferencesRepository->getOrDefaults($userId, $tenantId);
            $notifications = $this->notificationPreferencesRepository->listForUser($userId);

            return Response::json([
                'ui' => $ui,
                'notifications' => $notifications,
                'meta' => [
                    'callsign_source' => 'users.callsign',
                    'deprecated' => ['user_profiles.arma_callsign' => 'Utiliser users.callsign (indicatif plateforme unique).'],
                ],
            ]);
        }

        if ($request->method() === 'PATCH' || $request->method() === 'POST') {
            if (!$this->validateCsrf($request)) {
                return Response::json(['error' => 'Token CSRF invalide.'], 403);
            }
            $body = $this->jsonBody($request);
            $uiInput = $body['ui'] ?? $body;
            if (!is_array($uiInput)) {
                $uiInput = [];
            }

            $v = $this->validationService->validatePatch($uiInput);
            if (!$v['ok']) {
                return Response::json(['error' => 'Validation échouée.', 'details' => $v['errors']], 422);
            }
            if (!empty($v['normalized'])) {
                $this->uiPreferencesRepository->upsert($userId, $tenantId, $v['normalized']);
            }

            if (isset($body['notifications']) && is_array($body['notifications'])) {
                $notifRows = [];
                foreach ($body['notifications'] as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $ch = (string) ($row['channel'] ?? 'in_app');
                    if (!in_array($ch, ['email', 'in_app', 'push'], true)) {
                        return Response::json(['error' => 'notifications[].channel invalide.'], 422);
                    }
                    $notifRows[] = [
                        'channel' => $ch,
                        'event_key' => (string) ($row['event_key'] ?? ''),
                        'enabled' => (bool) ($row['enabled'] ?? true),
                    ];
                }
                $this->notificationPreferencesRepository->replaceMany($userId, $tenantId, $notifRows);
            }

            $ui = $this->uiPreferencesRepository->getOrDefaults($userId, $tenantId);
            $notifications = $this->notificationPreferencesRepository->listForUser($userId);

            return Response::json(['ok' => true, 'ui' => $ui, 'notifications' => $notifications]);
        }

        return Response::json(['error' => 'Méthode non autorisée.'], 405);
    }

    private function validateCsrf(Request $request): bool
    {
        $body = $this->jsonBody($request);
        $token = $request->input('_csrf_token')
            ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)
            ?? ($body['_csrf_token'] ?? null);

        return is_string($token) && Csrf::validate($token);
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
