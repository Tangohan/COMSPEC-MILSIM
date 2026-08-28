<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlatformUxFeedbackRepository;
use App\Services\Auth\AuthService;
use App\Support\Api\ApiResponder;

final class PlatformUxFeedbackApiController
{
    /** @var list<string> */
    private const ISSUE_OPTIONS = [
        'navigation',
        'labels',
        'performance',
        'mobile',
        'accessibility',
        'missing_info',
        'workflow',
        'visual_noise',
    ];

    public function __construct(
        private AuthService $authService,
        private PlatformUxFeedbackRepository $feedbackRepository
    ) {}

    public function state(Request $request, array $params = []): Response
    {
        [$tenantId, $userId, $err] = $this->requireAuth();
        if ($err instanceof Response) {
            return $err;
        }
        if (!$this->feedbackRepository->isReady()) {
            return ApiResponder::success(['ready' => false, 'rating' => null, 'survey_done' => false]);
        }
        $pageKey = $this->normalizePageKey((string) $request->query('page_key', ''));
        if ($pageKey === '') {
            return ApiResponder::error('validation_failed', 'page_key requis.', 422);
        }
        $state = $this->feedbackRepository->stateForPage($tenantId, $userId, $pageKey);

        return ApiResponder::success([
            'ready' => true,
            'page_key' => $pageKey,
            'rating' => $state['rating'],
            'survey_done' => $state['survey_done'],
            'issue_options' => self::ISSUE_OPTIONS,
        ]);
    }

    public function saveRating(Request $request, array $params = []): Response
    {
        return $this->persist($request, 'rating');
    }

    public function saveSurvey(Request $request, array $params = []): Response
    {
        return $this->persist($request, 'survey');
    }

    private function persist(Request $request, string $mode): Response
    {
        [$tenantId, $userId, $err] = $this->requireAuth();
        if ($err instanceof Response) {
            return $err;
        }
        if (!$this->validateCsrf($request)) {
            return ApiResponder::error('csrf_invalid', 'Token CSRF invalide.', 403);
        }
        if (!$this->feedbackRepository->isReady()) {
            return ApiResponder::error('not_ready', 'Module retour UI non activé (migration en attente).', 503);
        }
        $body = $this->jsonBody($request);
        $pageKey = $this->normalizePageKey((string) ($body['page_key'] ?? ''));
        if ($pageKey === '') {
            return ApiResponder::error('validation_failed', 'page_key requis.', 422);
        }
        $pagePath = mb_substr(trim((string) ($body['page_path'] ?? '')), 0, 500);
        $pageTitle = mb_substr(trim((string) ($body['page_title'] ?? '')), 0, 255);

        if ($mode === 'rating') {
            $rating = (int) ($body['rating'] ?? 0);
            if ($rating < 1 || $rating > 5) {
                return ApiResponder::error('validation_failed', 'Note entre 1 et 5 requise.', 422);
            }
            $comment = isset($body['comment']) ? (string) $body['comment'] : null;
            $ok = $this->feedbackRepository->upsertPageRating($tenantId, $userId, $pageKey, $pagePath, $pageTitle, $rating, $comment);
            if (!$ok) {
                return ApiResponder::error('save_failed', 'Enregistrement impossible.', 500);
            }

            return ApiResponder::success(['saved' => true, 'rating' => $rating]);
        }

        $issuesRaw = $body['issues'] ?? [];
        $issues = [];
        if (is_array($issuesRaw)) {
            foreach ($issuesRaw as $item) {
                $slug = trim((string) $item);
                if ($slug !== '' && in_array($slug, self::ISSUE_OPTIONS, true)) {
                    $issues[] = $slug;
                }
            }
        }
        $wouldRecommend = null;
        if (array_key_exists('would_recommend', $body)) {
            $wouldRecommend = filter_var($body['would_recommend'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        $ok = $this->feedbackRepository->upsertSurvey(
            $tenantId,
            $userId,
            $pageKey,
            $pagePath,
            $pageTitle,
            (int) ($body['ease_rating'] ?? 0),
            (int) ($body['clarity_rating'] ?? 0),
            (int) ($body['design_rating'] ?? 0),
            (int) ($body['usefulness_rating'] ?? 0),
            $issues,
            isset($body['improvement_text']) ? (string) $body['improvement_text'] : null,
            $wouldRecommend
        );
        if (!$ok) {
            return ApiResponder::error('save_failed', 'Enregistrement impossible.', 500);
        }

        return ApiResponder::success(['saved' => true]);
    }

    /** @return array{0:int,1:int,2:?Response} */
    private function requireAuth(): array
    {
        $user = $this->authService->user();
        if (!$user) {
            return [0, 0, ApiResponder::error('unauthorized', 'Non autorisé.', 401)];
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return [0, 0, ApiResponder::error('tenant_missing', 'Communauté non sélectionnée.', 400)];
        }

        return [$tenantId, (int) $user['id'], null];
    }

    private function normalizePageKey(string $raw): string
    {
        $raw = trim(strtolower($raw));
        $raw = preg_replace('#/+#', '/', $raw) ?? $raw;
        $raw = trim($raw, '/');

        return mb_substr($raw, 0, 255);
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
