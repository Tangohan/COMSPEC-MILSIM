<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Authorization\DashboardPinsAccess;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Repositories\ForumTopicRepository;
use App\Repositories\ForumPostRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;

class ForumModerationApiController
{
    public function __construct(
        private ForumTopicRepository $topicRepository,
        private ForumPostRepository $postRepository,
        private AuditService $auditService
    ) {}

    public function handle(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::json(['success' => false, 'error' => 'Non authentifié'], 401);
        }

        $input = $this->getJsonInput($request);
        $csrf = $input['csrf_token'] ?? $request->input('_csrf_token', '');
        if (!Csrf::validate($csrf)) {
            return Response::json(['success' => false, 'error' => 'Jeton CSRF invalide'], 403);
        }

        $action = (string) ($input['action'] ?? $request->input('action', ''));
        $topicId = isset($input['topic_id']) ? (int) $input['topic_id'] : 0;
        $postId = isset($input['post_id']) ? (int) $input['post_id'] : 0;

        $isDashboardPinAction = in_array($action, ['pin_dashboard', 'unpin_dashboard'], true);
        $isModo = function_exists('forum_viewer_is_moderator') && forum_viewer_is_moderator();
        $canManageDashPins = DashboardPinsAccess::canManage();

        if (!$isModo && !($isDashboardPinAction && $canManageDashPins)) {
            return Response::json(['success' => false, 'error' => 'Non autorisé'], 403);
        }

        $this->auditService->log(
            AuditAction::FORUM_MODERATION,
            (int) $tenantId,
            (int) $userId,
            'forum_moderation',
            $topicId > 0 ? $topicId : $postId,
            null,
            (string) $action
        );

        return match ($action) {
            'lock_topic' => $this->setTopicLock($topicId, $tenantId, true),
            'unlock_topic' => $this->setTopicLock($topicId, $tenantId, false),
            'pin_topic' => $this->setTopicPin($topicId, $tenantId, true),
            'unpin_topic' => $this->setTopicPin($topicId, $tenantId, false),
            'pin_dashboard' => $this->setTopicDashboardPin($topicId, (int) $tenantId, true),
            'unpin_dashboard' => $this->setTopicDashboardPin($topicId, (int) $tenantId, false),
            'toggle_official' => $this->toggleOfficial($topicId, $tenantId),
            'hide_topic' => $this->setTopicHidden($topicId, $tenantId, true),
            'unhide_topic' => $this->setTopicHidden($topicId, $tenantId, false),
            'hide_post' => $this->setPostHidden($postId, $tenantId, true),
            'unhide_post' => $this->setPostHidden($postId, $tenantId, false),
            default => Response::json(['success' => false, 'error' => 'Action inconnue'], 400),
        };
    }

    private function getJsonInput(Request $request): array
    {
        $raw = $request->method() === 'POST' ? (string) file_get_contents('php://input') : '';
        if ($raw === '') {
            return $request->all();
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : $request->all();
    }

    private function setTopicLock(int $topicId, int $tenantId, bool $locked): Response
    {
        if ($topicId <= 0) {
            return Response::json(['success' => false, 'error' => 'Sujet manquant'], 400);
        }
        $topic = $this->topicRepository->findById($topicId, $tenantId);
        if (!$topic) {
            return Response::json(['success' => false, 'error' => 'Sujet introuvable'], 404);
        }
        if ($locked) {
            $this->topicRepository->update($topicId, $tenantId, ['is_locked' => 1]);
        } else {
            try {
                $this->topicRepository->update($topicId, $tenantId, ['is_locked' => 0, 'suppress_auto_lock' => 1]);
            } catch (\Throwable) {
                $this->topicRepository->update($topicId, $tenantId, ['is_locked' => 0]);
            }
        }
        return Response::json(['success' => true]);
    }

    private function toggleOfficial(int $topicId, int $tenantId): Response
    {
        if ($topicId <= 0) {
            return Response::json(['success' => false, 'error' => 'Sujet manquant'], 400);
        }
        $topic = $this->topicRepository->findById($topicId, $tenantId);
        if (!$topic) {
            return Response::json(['success' => false, 'error' => 'Sujet introuvable'], 404);
        }
        $next = empty($topic['is_official']) ? 1 : 0;
        $this->topicRepository->update($topicId, $tenantId, ['is_official' => $next]);

        return Response::json(['success' => true, 'is_official' => $next]);
    }

    private function setTopicPin(int $topicId, int $tenantId, bool $pinned): Response
    {
        if ($topicId <= 0) {
            return Response::json(['success' => false, 'error' => 'Sujet manquant'], 400);
        }
        $topic = $this->topicRepository->findById($topicId, $tenantId);
        if (!$topic) {
            return Response::json(['success' => false, 'error' => 'Sujet introuvable'], 404);
        }
        $this->topicRepository->update($topicId, $tenantId, ['is_pinned' => $pinned ? 1 : 0]);
        return Response::json(['success' => true]);
    }

    private function setTopicDashboardPin(int $topicId, int $tenantId, bool $pinned): Response
    {
        if ($topicId <= 0) {
            return Response::json(['success' => false, 'error' => 'Sujet manquant'], 400);
        }
        $topic = $this->topicRepository->findById($topicId, $tenantId);
        if (!$topic) {
            return Response::json(['success' => false, 'error' => 'Sujet introuvable'], 404);
        }

        $scope = strtolower(trim((string) ($topic['category_scope'] ?? 'general')));
        // Messages de la plateforme globale : hors périmètre communication communauté.
        if (in_array($scope, ['platform', 'global'], true)) {
            return Response::json([
                'success' => false,
                'error' => 'Seuls les messages de votre communauté peuvent être épinglés au tableau de bord.',
            ], 422);
        }

        if ($pinned) {
            $already = !empty($topic['pin_on_dashboard']);
            if (!$already) {
                $count = $this->topicRepository->countPinnedOnDashboardForTenant($tenantId);
                if ($count >= ForumTopicRepository::MAX_DASHBOARD_PINS) {
                    return Response::json([
                        'success' => false,
                        'error' => 'Nombre maximum de messages épinglés atteint (' . ForumTopicRepository::MAX_DASHBOARD_PINS . '). Retirez-en un avant d’en ajouter.',
                    ], 422);
                }
            }
        }

        $ok = $this->topicRepository->update($topicId, $tenantId, ['pin_on_dashboard' => $pinned ? 1 : 0]);
        if (!$ok) {
            return Response::json([
                'success' => false,
                'error' => 'Impossible d’enregistrer l’épinglage pour le moment.',
            ], 500);
        }

        return Response::json([
            'success' => true,
            'pin_on_dashboard' => $pinned ? 1 : 0,
            'message' => $pinned
                ? 'Message épinglé sur le tableau de bord.'
                : 'Message retiré du tableau de bord.',
        ]);
    }

    private function setTopicHidden(int $topicId, int $tenantId, bool $hidden): Response
    {
        if ($topicId <= 0) {
            return Response::json(['success' => false, 'error' => 'Sujet manquant'], 400);
        }
        $topic = $this->topicRepository->findById($topicId, $tenantId);
        if (!$topic) {
            return Response::json(['success' => false, 'error' => 'Sujet introuvable'], 404);
        }
        $this->topicRepository->update($topicId, $tenantId, ['is_hidden' => $hidden ? 1 : 0]);
        return Response::json(['success' => true]);
    }

    private function setPostHidden(int $postId, int $tenantId, bool $hidden): Response
    {
        if ($postId <= 0) {
            return Response::json(['success' => false, 'error' => 'Message manquant'], 400);
        }
        $post = $this->postRepository->findById($postId, $tenantId);
        if (!$post) {
            return Response::json(['success' => false, 'error' => 'Message introuvable'], 404);
        }
        $this->postRepository->setHidden($postId, $tenantId, $hidden);
        return Response::json(['success' => true]);
    }
}
