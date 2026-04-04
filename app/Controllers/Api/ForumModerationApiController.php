<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Repositories\ForumTopicRepository;
use App\Repositories\ForumPostRepository;

class ForumModerationApiController
{
    public function __construct(
        private ForumTopicRepository $topicRepository,
        private ForumPostRepository $postRepository
    ) {}

    public function handle(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::json(['success' => false, 'error' => 'Non authentifié'], 401);
        }
        if (!function_exists('can') || !can('forum.moderate')) {
            return Response::json(['success' => false, 'error' => 'Non autorisé'], 403);
        }

        $input = $this->getJsonInput($request);
        $csrf = $input['csrf_token'] ?? $request->input('_csrf_token', '');
        if (!Csrf::validate($csrf)) {
            return Response::json(['success' => false, 'error' => 'Jeton CSRF invalide'], 403);
        }

        $action = $input['action'] ?? $request->input('action', '');
        $topicId = isset($input['topic_id']) ? (int) $input['topic_id'] : 0;
        $postId = isset($input['post_id']) ? (int) $input['post_id'] : 0;

        return match ($action) {
            'lock_topic' => $this->setTopicLock($topicId, $tenantId, true),
            'unlock_topic' => $this->setTopicLock($topicId, $tenantId, false),
            'pin_topic' => $this->setTopicPin($topicId, $tenantId, true),
            'unpin_topic' => $this->setTopicPin($topicId, $tenantId, false),
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
            return Response::json(['success' => false, 'error' => 'topic_id requis'], 400);
        }
        $topic = $this->topicRepository->findById($topicId, $tenantId);
        if (!$topic) {
            return Response::json(['success' => false, 'error' => 'Sujet introuvable'], 404);
        }
        $this->topicRepository->update($topicId, $tenantId, ['is_locked' => $locked ? 1 : 0]);
        return Response::json(['success' => true]);
    }

    private function setTopicPin(int $topicId, int $tenantId, bool $pinned): Response
    {
        if ($topicId <= 0) {
            return Response::json(['success' => false, 'error' => 'topic_id requis'], 400);
        }
        $topic = $this->topicRepository->findById($topicId, $tenantId);
        if (!$topic) {
            return Response::json(['success' => false, 'error' => 'Sujet introuvable'], 404);
        }
        $this->topicRepository->update($topicId, $tenantId, ['is_pinned' => $pinned ? 1 : 0]);
        return Response::json(['success' => true]);
    }

    private function setTopicHidden(int $topicId, int $tenantId, bool $hidden): Response
    {
        if ($topicId <= 0) {
            return Response::json(['success' => false, 'error' => 'topic_id requis'], 400);
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
            return Response::json(['success' => false, 'error' => 'post_id requis'], 400);
        }
        $post = $this->postRepository->findById($postId, $tenantId);
        if (!$post) {
            return Response::json(['success' => false, 'error' => 'Message introuvable'], 404);
        }
        $this->postRepository->setHidden($postId, $tenantId, $hidden);
        return Response::json(['success' => true]);
    }
}
