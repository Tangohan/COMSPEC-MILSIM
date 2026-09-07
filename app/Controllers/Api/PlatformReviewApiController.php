<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlatformReviewRepository;
use App\Services\Auth\AuthService;
use App\Support\Api\ApiResponder;
use App\Support\PlatformReviewCatalog;

final class PlatformReviewApiController
{
    public function __construct(
        private AuthService $authService,
        private PlatformReviewRepository $reviews,
    ) {}

    public function state(Request $request, array $params = []): Response
    {
        [$userId, $tenantId, $err] = $this->requireAuth();
        if ($err instanceof Response) {
            return $err;
        }
        unset($request, $params, $tenantId);
        $state = $this->reviews->promptState($userId);

        return ApiResponder::success([
            'ready' => $state['ready'],
            'has_review' => $state['has_review'],
            'prompt' => $state['prompt'],
            'pending_translations' => $this->reviews->countPendingForUser($userId),
            'max_pending_translations' => PlatformReviewCatalog::MAX_PENDING_TRANSLATIONS,
        ]);
    }

    public function saveReview(Request $request, array $params = []): Response
    {
        [$userId, $tenantId, $err] = $this->requireAuth();
        if ($err instanceof Response) {
            return $err;
        }
        unset($params);
        if (!$this->validateCsrf($request)) {
            return ApiResponder::error('csrf_invalid', 'La session a expiré. Rechargez la page.', 403);
        }
        if (!$this->reviews->isReady()) {
            return ApiResponder::error('not_ready', 'L’avis n’est pas encore disponible. Relancez la mise à jour du site.', 503);
        }
        $body = $this->jsonBody($request);
        $score = (int) ($body['score'] ?? -1);
        if ($score < 0 || $score > 10) {
            return ApiResponder::error('validation_failed', 'Choisissez une note de 0 à 10.', 422);
        }
        $ok = $this->reviews->upsertReview(
            $userId,
            $tenantId,
            $score,
            (string) ($body['usage_kind'] ?? ''),
            isset($body['highlights']) ? (string) $body['highlights'] : null,
            isset($body['improvements']) ? (string) $body['improvements'] : null
        );
        if (!$ok) {
            return ApiResponder::error('save_failed', 'L’avis n’a pas pu être enregistré.', 500);
        }

        return ApiResponder::success(['saved' => true]);
    }

    public function snooze(Request $request, array $params = []): Response
    {
        [$userId, $tenantId, $err] = $this->requireAuth();
        if ($err instanceof Response) {
            return $err;
        }
        unset($params);
        if (!$this->validateCsrf($request)) {
            return ApiResponder::error('csrf_invalid', 'La session a expiré. Rechargez la page.', 403);
        }
        if (!$this->reviews->isReady()) {
            return ApiResponder::success(['snoozed' => true]);
        }
        $this->reviews->snooze($userId, $tenantId);

        return ApiResponder::success(['snoozed' => true]);
    }

    public function saveTranslation(Request $request, array $params = []): Response
    {
        [$userId, $tenantId, $err] = $this->requireAuth();
        if ($err instanceof Response) {
            return $err;
        }
        unset($params);
        if (!$this->validateCsrf($request)) {
            return ApiResponder::error('csrf_invalid', 'La session a expiré. Rechargez la page.', 403);
        }
        if (!$this->reviews->isReady()) {
            return ApiResponder::error('not_ready', 'Les propositions de traduction ne sont pas encore disponibles. Relancez la mise à jour du site.', 503);
        }
        if ($this->reviews->countPendingForUser($userId) >= PlatformReviewCatalog::MAX_PENDING_TRANSLATIONS) {
            return ApiResponder::error(
                'too_many',
                'Vous avez déjà plusieurs propositions en attente. Merci d’attendre qu’elles soient relues.',
                429
            );
        }
        $body = $this->jsonBody($request);
        $original = trim((string) ($body['original_text'] ?? ''));
        $proposed = trim((string) ($body['proposed_text'] ?? ''));
        if (mb_strlen($original) < 2 || mb_strlen($proposed) < 2) {
            return ApiResponder::error('validation_failed', 'Indiquez le texte lu et la formulation proposée.', 422);
        }
        $id = $this->reviews->addSuggestion(
            $userId,
            $tenantId,
            (string) ($body['locale'] ?? 'en'),
            (string) ($body['area'] ?? ''),
            $original,
            $proposed,
            isset($body['comment']) ? (string) $body['comment'] : null
        );
        if ($id < 1) {
            return ApiResponder::error('save_failed', 'La proposition n’a pas pu être enregistrée.', 500);
        }

        return ApiResponder::success(['saved' => true, 'id' => $id]);
    }

    /** @return array{0:int,1:?int,2:?Response} */
    private function requireAuth(): array
    {
        $user = $this->authService->user();
        if (!$user) {
            return [0, null, ApiResponder::error('unauthorized', 'Connectez-vous pour participer.', 401)];
        }
        $tenantId = (int) Session::get('tenant_id');

        return [(int) $user['id'], $tenantId > 0 ? $tenantId : null, null];
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
