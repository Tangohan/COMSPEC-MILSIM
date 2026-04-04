<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Repositories\ForumCategoryRepository;
use App\Repositories\ForumPostRepository;
use App\Repositories\ForumReportRepository;
use App\Repositories\ForumTopicRepository;
use App\Repositories\ForumVoteRepository;
/**
 * API REST structurée (/api/forum/topics, /api/forum/posts, …) — JSON + CSRF sur mutations.
 */
final class ForumRestController
{
    public function __construct(
        private ForumCategoryRepository $categoryRepository,
        private ForumTopicRepository $topicRepository,
        private ForumPostRepository $postRepository,
        private ForumReportRepository $reportRepository,
        private ForumVoteRepository $voteRepository
    ) {}

    public function listTopics(Request $request, array $params = []): Response
    {
        $res = $this->requireAuth();
        if ($res !== null) {
            return $res;
        }
        $tenantId = (int) Session::get('tenant_id');
        $categoryId = (int) $request->query('category_id', 0);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $sort = (string) $request->query('sort', 'activity');
        $filter = $request->query('filter') !== null ? (string) $request->query('filter') : null;
        $userId = (int) Session::get('user_id');
        $q = trim((string) $request->query('q', ''));

        if ($categoryId > 0) {
            $cat = $this->categoryRepository->findById($categoryId, $tenantId);
            if (!$cat) {
                return Response::json(['success' => false, 'error' => 'Catégorie introuvable'], 404);
            }
            $topics = $this->topicRepository->listByCategory($categoryId, $tenantId, $page, $perPage, $sort, $filter, $userId, false);
            $total = $this->topicRepository->countByCategory($categoryId, $tenantId, $filter, $userId, false);
        } else {
            $topics = $q !== ''
                ? $this->topicRepository->search($tenantId, $q, $perPage)
                : $this->topicRepository->getRecentForIndex($tenantId, $perPage);
            $total = count($topics);
        }

        return Response::json([
            'success' => true,
            'data' => ['topics' => $topics, 'page' => $page, 'total' => $total],
        ]);
    }

    public function showTopic(Request $request, array $params = []): Response
    {
        $res = $this->requireAuth();
        if ($res !== null) {
            return $res;
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $topic = $this->topicRepository->findById($id, $tenantId);
        if (!$topic) {
            return Response::json(['success' => false, 'error' => 'Sujet introuvable'], 404);
        }

        return Response::json(['success' => true, 'data' => ['topic' => $topic]]);
    }

    public function listPosts(Request $request, array $params = []): Response
    {
        $res = $this->requireAuth();
        if ($res !== null) {
            return $res;
        }
        $tenantId = (int) Session::get('tenant_id');
        $topicId = (int) ($params['topicId'] ?? 0);
        $topic = $this->topicRepository->findById($topicId, $tenantId);
        if (!$topic) {
            return Response::json(['success' => false, 'error' => 'Sujet introuvable'], 404);
        }
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));
        $isModo = function_exists('can') && can('forum.moderate');
        $posts = $this->postRepository->listByTopicPaginated($topicId, $page, $perPage, $isModo);
        $count = $this->postRepository->countByTopic($topicId, $isModo);

        return Response::json([
            'success' => true,
            'data' => [
                'posts' => $posts,
                'page' => $page,
                'total' => $count,
            ],
        ]);
    }

    public function votePost(Request $request, array $params = []): Response
    {
        $res = $this->requireAuth();
        if ($res !== null) {
            return $res;
        }
        $input = $this->jsonBody($request);
        if (!Csrf::validate((string) ($input['csrf_token'] ?? $request->input('_csrf_token', '')))) {
            return Response::json(['success' => false, 'error' => 'Jeton CSRF invalide'], 403);
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $postId = (int) ($params['postId'] ?? 0);
        $value = (int) ($input['value'] ?? 0);
        $post = $this->postRepository->findById($postId, $tenantId);
        if (!$post) {
            return Response::json(['success' => false, 'error' => 'Message introuvable'], 404);
        }
        if ($value === 0) {
            $this->voteRepository->removeVote($postId, $userId);
        } else {
            $this->voteRepository->setVote($tenantId, $postId, $userId, $value);
        }
        $sum = $this->voteRepository->sumForPost($postId);

        return Response::json(['success' => true, 'data' => ['score' => $sum, 'user_vote' => $value === 0 ? null : $value]]);
    }

    public function report(Request $request, array $params = []): Response
    {
        $res = $this->requireAuth();
        if ($res !== null) {
            return $res;
        }
        $input = $this->jsonBody($request);
        if (!Csrf::validate((string) ($input['csrf_token'] ?? $request->input('_csrf_token', '')))) {
            return Response::json(['success' => false, 'error' => 'Jeton CSRF invalide'], 403);
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $targetType = (string) ($input['target_type'] ?? 'post');
        $targetId = (int) ($input['target_id'] ?? 0);
        $reportType = preg_match('/^(spam|abuse|illegal|other)$/', (string) ($input['report_type'] ?? 'other')) ? $input['report_type'] : 'other';
        $comment = trim((string) ($input['comment'] ?? ''));
        $reason = trim((string) ($input['reason'] ?? 'Signalement'));
        $postId = null;
        $topicId = null;
        if ($targetType === 'post' && $targetId > 0) {
            $post = $this->postRepository->findById($targetId, $tenantId);
            if ($post) {
                $postId = $targetId;
                $topicId = (int) $post['topic_id'];
            }
        } elseif ($targetType === 'topic' && $targetId > 0) {
            $topic = $this->topicRepository->findById($targetId, $tenantId);
            if ($topic) {
                $topicId = $targetId;
            }
        }
        if ($postId === null && $topicId === null) {
            return Response::json(['success' => false, 'error' => 'Cible invalide'], 400);
        }
        $this->reportRepository->create($tenantId, $userId, $postId, $topicId, $reason !== '' ? $reason : 'Signalement', $reportType, $comment !== '' ? $comment : null);

        return Response::json(['success' => true]);
    }

    public function search(Request $request, array $params = []): Response
    {
        $res = $this->requireAuth();
        if ($res !== null) {
            return $res;
        }
        $tenantId = (int) Session::get('tenant_id');
        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 2) {
            return Response::json(['success' => true, 'data' => ['topics' => []]]);
        }
        $topics = $this->topicRepository->search($tenantId, $q, 40);

        return Response::json(['success' => true, 'data' => ['topics' => $topics]]);
    }

    private function requireAuth(): ?Response
    {
        if (!Session::get('tenant_id') || !Session::get('user_id')) {
            return Response::json(['success' => false, 'error' => 'Non authentifié'], 401);
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function jsonBody(Request $request): array
    {
        $raw = (string) file_get_contents('php://input');
        if ($raw === '') {
            return $request->all();
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : $request->all();
    }
}
