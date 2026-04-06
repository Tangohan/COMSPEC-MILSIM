<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Repositories\ForumCategoryRepository;
use App\Repositories\ForumTopicRepository;
use App\Repositories\ForumPostRepository;
use App\Repositories\ForumReportRepository;
use App\Repositories\UserRepository;
use App\Repositories\ForumVoteRepository;
use App\Repositories\ForumNotificationRepository;
use App\Repositories\UserForumStatsRepository;
use App\Repositories\UserProfileDisplaySettingsRepository;
use App\Services\Forum\ForumModerationEngine;
use App\Services\Forum\ForumPostAttachmentService;
use App\Support\ForumReportReason;

class ForumApiController
{
    public function __construct(
        private ForumCategoryRepository $categoryRepository,
        private ForumTopicRepository $topicRepository,
        private ForumPostRepository $postRepository,
        private ForumReportRepository $reportRepository,
        private UserRepository $userRepository,
        private ForumVoteRepository $voteRepository,
        private ForumModerationEngine $moderationEngine,
        private ForumNotificationRepository $notificationRepository,
        private ForumPostAttachmentService $postAttachmentService,
        private UserForumStatsRepository $userForumStatsRepository,
        private UserProfileDisplaySettingsRepository $userProfileDisplaySettingsRepository
    ) {}

    public function handle(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::json(['success' => false, 'error' => 'Non authentifié'], 401);
        }

        if ($request->method() === 'GET') {
            $action = $request->query('action', '');
            if ($action === 'mention_search') {
                return $this->mentionSearch($request, $tenantId);
            }
            return Response::json(['success' => false, 'error' => 'Action inconnue'], 400);
        }

        $input = $this->getJsonInput($request);
        $action = $input['action'] ?? $request->input('action', '');
        $csrf = $input['csrf_token'] ?? $request->input('_csrf_token', '');
        if (!Csrf::validate($csrf)) {
            return Response::json(['success' => false, 'error' => 'Jeton CSRF invalide'], 403);
        }

        return match ($action) {
            'subscribe' => $this->subscribe($input, $userId, $tenantId),
            'unsubscribe' => $this->unsubscribe($input, $userId, $tenantId),
            'create_topic' => $this->createTopic($input, $userId, $tenantId),
            'create_post' => $this->createPost($input, $userId, $tenantId),
            'edit_post' => $this->editPost($input, $userId, $tenantId),
            'delete_post' => $this->deletePost($input, $userId, $tenantId),
            'react' => $this->react($input, $userId),
            'report' => $this->report($input, $userId, $tenantId),
            'save_profile_settings' => $this->saveProfileSettings($input, $userId),
            'mark_best_answer' => $this->markBestAnswer($input, $userId, $tenantId),
            'save_draft' => $this->saveDraft($input, $userId, $tenantId),
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

    private function mentionSearch(Request $request, int $tenantId): Response
    {
        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 2) {
            return Response::json(['success' => true, 'users' => []]);
        }
        $users = $this->userRepository->searchForMention($tenantId, $q, 10);
        return Response::json(['success' => true, 'users' => $users]);
    }

    private function subscribe(array $input, int $userId, int $tenantId): Response
    {
        $type = $input['type'] ?? '';
        $targetId = (int) ($input['target_id'] ?? 0);
        if ($type === 'category' && $targetId > 0) {
            $cat = $this->categoryRepository->findById($targetId, $tenantId);
            if ($cat) {
                $this->categoryRepository->subscribeCategory($userId, $targetId);
                return Response::json(['success' => true]);
            }
        }
        if ($type === 'topic' && $targetId > 0) {
            $topic = $this->topicRepository->findById($targetId, $tenantId);
            if ($topic) {
                $this->topicRepository->subscribe($userId, $targetId);
                return Response::json(['success' => true]);
            }
        }
        return Response::json(['success' => false, 'error' => 'Cible invalide'], 400);
    }

    private function unsubscribe(array $input, int $userId, int $tenantId): Response
    {
        $type = $input['type'] ?? '';
        $targetId = (int) ($input['target_id'] ?? 0);
        if ($type === 'category' && $targetId > 0) {
            $this->categoryRepository->unsubscribeCategory($userId, $targetId);
            return Response::json(['success' => true]);
        }
        if ($type === 'topic' && $targetId > 0) {
            $this->topicRepository->unsubscribe($userId, $targetId);
            return Response::json(['success' => true]);
        }
        return Response::json(['success' => false, 'error' => 'Cible invalide'], 400);
    }

    private function createTopic(array $input, int $userId, int $tenantId): Response
    {
        if (!function_exists('can') || !can('forum.create_topic')) {
            return Response::json(['success' => false, 'error' => 'Non autorisé'], 403);
        }
        $categoryId = (int) ($input['category_id'] ?? 0);
        $title = trim((string) ($input['title'] ?? ''));
        $content = trim((string) ($input['content'] ?? ''));
        $category = $this->categoryRepository->findById($categoryId, $tenantId);
        if (!$category) {
            return Response::json(['success' => false, 'error' => 'Catégorie invalide'], 400);
        }
        if (strlen($title) < 3 || strlen($title) > 255) {
            return Response::json(['success' => false, 'error' => 'Titre entre 3 et 255 caractères'], 400);
        }
        $maxLen = (int) forum_get_setting('forum_max_post_length', 10000);
        $attachmentIds = $input['attachment_ids'] ?? [];
        if (!is_array($attachmentIds)) {
            $attachmentIds = [];
        }
        if ((strlen($content) < 5 && $attachmentIds === []) || strlen($content) > $maxLen) {
            return Response::json(['success' => false, 'error' => 'Contenu entre 5 et ' . $maxLen . ' caractères (ou joindre des fichiers)'], 400);
        }
        $slug = $this->slugify($title) ?: 'sujet-' . time();
        $slug = $slug . '-' . substr(uniqid('', true), -6);
        $topicId = $this->topicRepository->create($tenantId, $categoryId, $userId, $title, $slug);
        $firstPostId = $this->postRepository->create($tenantId, $topicId, $userId, $content);
        $this->topicRepository->touchUpdatedAt($topicId);
        $this->userForumStatsRepository->incrementPostCount((int) $tenantId, (int) $userId);
        $this->postAttachmentService->attachToPost((int) $tenantId, $firstPostId, (int) $userId, $attachmentIds);
        $this->moderationEngine->analyze($tenantId, $userId, $firstPostId, $content);

        return Response::json(['success' => true, 'topic_id' => $topicId]);
    }

    private function createPost(array $input, int $userId, int $tenantId): Response
    {
        if (!function_exists('can') || !can('forum.reply')) {
            return Response::json(['success' => false, 'error' => 'Non autorisé'], 403);
        }
        $topicId = (int) ($input['topic_id'] ?? 0);
        $content = trim((string) ($input['content'] ?? ''));
        $topic = $this->topicRepository->findById($topicId, $tenantId);
        if (!$topic) {
            return Response::json(['success' => false, 'error' => 'Sujet introuvable'], 404);
        }
        if ($topic['is_locked']) {
            return Response::json(['success' => false, 'error' => 'Sujet verrouillé'], 400);
        }
        $maxLen = (int) forum_get_setting('forum_max_post_length', 10000);
        $attachmentIds = $input['attachment_ids'] ?? [];
        if (!is_array($attachmentIds)) {
            $attachmentIds = [];
        }
        $hasBody = strlen($content) >= 1;
        $hasFiles = $attachmentIds !== [];
        if ((!$hasBody && !$hasFiles) || strlen($content) > $maxLen) {
            return Response::json(['success' => false, 'error' => 'Message vide ou trop long (texte ou pièces jointes requis)'], 400);
        }
        $parentPostId = isset($input['parent_post_id']) ? (int) $input['parent_post_id'] : null;
        if ($parentPostId !== null && $parentPostId <= 0) {
            $parentPostId = null;
        }
        $postId = $this->postRepository->create($tenantId, $topicId, $userId, $content, $parentPostId);
        $this->topicRepository->touchUpdatedAt($topicId);
        $this->userForumStatsRepository->incrementPostCount((int) $tenantId, (int) $userId);
        $this->postAttachmentService->attachToPost((int) $tenantId, $postId, (int) $userId, $attachmentIds);
        $this->moderationEngine->analyze($tenantId, $userId, $postId, $content);
        $this->notifyTopicParticipantsApi($tenantId, $topicId, $userId, (string) ($topic['title'] ?? 'Sujet'));

        return Response::json(['success' => true, 'post_id' => $postId]);
    }

    private function markBestAnswer(array $input, int $userId, int $tenantId): Response
    {
        $topicId = (int) ($input['topic_id'] ?? 0);
        $postId = (int) ($input['post_id'] ?? 0);
        $topic = $this->topicRepository->findById($topicId, $tenantId);
        if (!$topic) {
            return Response::json(['success' => false, 'error' => 'Sujet introuvable'], 404);
        }
        $isAuthor = (int) $topic['user_id'] === $userId;
        $isMod = function_exists('forum_user_can_moderate') && forum_user_can_moderate();
        if (!$isAuthor && !$isMod) {
            return Response::json(['success' => false, 'error' => 'Non autorisé'], 403);
        }
        $post = $this->postRepository->findById($postId, $tenantId);
        if (!$post || (int) $post['topic_id'] !== $topicId) {
            return Response::json(['success' => false, 'error' => 'Message invalide'], 400);
        }
        $this->topicRepository->setBestAnswer($topicId, $tenantId, $postId);

        return Response::json(['success' => true]);
    }

    private function saveDraft(array $input, int $userId, int $tenantId): Response
    {
        // Brouillon principal côté client (localStorage) ; endpoint pour sync futur.
        return Response::json(['success' => true, 'server' => false]);
    }

    private function editPost(array $input, int $userId, int $tenantId): Response
    {
        $postId = (int) ($input['post_id'] ?? 0);
        $content = trim((string) ($input['content'] ?? ''));
        $post = $this->postRepository->findById($postId, $tenantId);
        if (!$post) {
            return Response::json(['success' => false, 'error' => 'Message introuvable'], 404);
        }
        $isModo = function_exists('forum_viewer_is_moderator') && forum_viewer_is_moderator();
        if ((int) $post['user_id'] !== $userId && !$isModo) {
            return Response::json(['success' => false, 'error' => 'Non autorisé'], 403);
        }
        $maxLen = (int) forum_get_setting('forum_max_post_length', 10000);
        if (strlen($content) < 1 || strlen($content) > $maxLen) {
            return Response::json(['success' => false, 'error' => 'Contenu invalide'], 400);
        }
        $this->postRepository->update($postId, $tenantId, $content);
        $this->topicRepository->touchUpdatedAt((int) $post['topic_id']);
        return Response::json(['success' => true]);
    }

    private function deletePost(array $input, int $userId, int $tenantId): Response
    {
        $postId = (int) ($input['post_id'] ?? 0);
        $post = $this->postRepository->findById($postId, $tenantId);
        if (!$post) {
            return Response::json(['success' => false, 'error' => 'Message introuvable'], 404);
        }
        $isModo = function_exists('forum_viewer_is_moderator') && forum_viewer_is_moderator();
        if ((int) $post['user_id'] !== $userId && !$isModo) {
            return Response::json(['success' => false, 'error' => 'Non autorisé'], 403);
        }
        $topicId = (int) $post['topic_id'];
        $this->postRepository->delete($postId, $tenantId);
        $this->topicRepository->touchUpdatedAt($topicId);
        return Response::json(['success' => true]);
    }

    private function react(array $input, int $userId): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $postId = (int) ($input['post_id'] ?? 0);
        $value = (int) ($input['value'] ?? 0);
        if ($postId <= 0) {
            return Response::json(['success' => false, 'error' => 'Message invalide'], 400);
        }
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

        return Response::json(['success' => true, 'score' => $sum]);
    }

    private function report(array $input, int $userId, int $tenantId): Response
    {
        $targetType = strtolower(trim((string) ($input['target_type'] ?? '')));
        $targetId = (int) ($input['target_id'] ?? 0);
        $topicContext = (int) ($input['topic_id'] ?? 0);
        $postContext = (int) ($input['post_id'] ?? 0);
        $reportedUrl = trim((string) ($input['reported_url'] ?? ''));
        $category = trim((string) ($input['reason'] ?? $input['reason_category'] ?? 'other'));
        $details = trim((string) ($input['details'] ?? $input['comment'] ?? ''));

        $normalized = ForumReportReason::fromCategory($category !== '' ? $category : 'other', $details);
        $reportType = $normalized['report_type'];
        $reasonText = $normalized['reason'];
        $comment = $normalized['comment'];

        $postId = null;
        $topicId = null;
        $urlForDb = null;

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
        } elseif ($targetType === 'url') {
            if ($reportedUrl === '' || strlen($reportedUrl) > 2048) {
                return Response::json(['success' => false, 'error' => 'URL invalide ou trop longue'], 400);
            }
            $schemeTest = parse_url($reportedUrl, PHP_URL_SCHEME);
            if (!is_string($schemeTest) || !in_array(strtolower($schemeTest), ['http', 'https'], true)) {
                return Response::json(['success' => false, 'error' => 'URL invalide (http ou https uniquement)'], 400);
            }
            $tid = $topicContext > 0 ? $topicContext : $targetId;
            if ($tid <= 0) {
                return Response::json(['success' => false, 'error' => 'Sujet de référence manquant'], 400);
            }
            $topic = $this->topicRepository->findById($tid, $tenantId);
            if (!$topic) {
                return Response::json(['success' => false, 'error' => 'Sujet introuvable'], 404);
            }
            $topicId = $tid;
            if ($postContext > 0) {
                $post = $this->postRepository->findById($postContext, $tenantId);
                if ($post && (int) $post['topic_id'] === $topicId) {
                    $postId = $postContext;
                }
            }
            $urlForDb = $reportedUrl;
            $reasonText = 'Lien signalé : ' . $reportedUrl . "\n" . $reasonText;
        } else {
            return Response::json(['success' => false, 'error' => 'Type de cible invalide'], 400);
        }

        if ($topicId === null) {
            return Response::json(['success' => false, 'error' => 'Cible invalide'], 400);
        }

        $this->reportRepository->create($tenantId, $userId, $postId, $topicId, $reasonText !== '' ? $reasonText : 'Signalement', $reportType, $comment, $urlForDb);

        return Response::json(['success' => true]);
    }

    private function saveProfileSettings(array $input, int $userId): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::json(['success' => false, 'error' => 'Session invalide'], 400);
        }
        if (!$this->userProfileDisplaySettingsRepository->tableExists()) {
            return Response::json(['success' => true]);
        }
        $raw = $input['forum_visible_role_id'] ?? null;
        $forumVisibleRoleId = null;
        if ($raw !== null && $raw !== '' && (string) $raw !== '0') {
            $forumVisibleRoleId = (int) $raw;
            if ($forumVisibleRoleId < 1) {
                $forumVisibleRoleId = null;
            } else {
                $allowed = false;
                foreach ($this->userRepository->listOrganizationRoleIdsForUser($userId) as $rid) {
                    if ((int) $rid === $forumVisibleRoleId) {
                        $allowed = true;
                        break;
                    }
                }
                if (!$allowed) {
                    return Response::json(['success' => false, 'error' => 'Rôle non autorisé pour votre compte'], 400);
                }
            }
        }
        $this->userProfileDisplaySettingsRepository->upsert($userId, ['forum_visible_role_id' => $forumVisibleRoleId]);

        return Response::json(['success' => true]);
    }

    private function slugify(string $text): string
    {
        $text = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $text);
        $text = preg_replace('/[\s-]+/', '-', trim($text));
        return strtolower($text);
    }

    private function notifyTopicParticipantsApi(int $tenantId, int $topicId, int $authorUserId, string $title): void
    {
        if (!$this->notificationRepository->tableExists()) {
            return;
        }
        try {
            $pdo = \App\Core\Database::getPdo();
            $stmt = $pdo->prepare(
                'SELECT DISTINCT user_id FROM forum_posts WHERE topic_id = ? AND user_id IS NOT NULL AND user_id != ?'
            );
            $stmt->execute([$topicId, $authorUserId]);
            $ids = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'user_id');
            $subStmt = $pdo->prepare('SELECT user_id FROM forum_topic_subscriptions WHERE topic_id = ? AND user_id != ?');
            $subStmt->execute([$topicId, $authorUserId]);
            foreach ($subStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $ids[] = (int) $r['user_id'];
            }
            $ids = array_unique(array_map('intval', $ids));
            foreach ($ids as $uid) {
                if ($uid <= 0) {
                    continue;
                }
                $this->notificationRepository->create($tenantId, $uid, 'topic_reply', [
                    'topic_id' => $topicId,
                    'title' => $title,
                ]);
            }
        } catch (\Throwable) {
            // ignore
        }
    }
}
