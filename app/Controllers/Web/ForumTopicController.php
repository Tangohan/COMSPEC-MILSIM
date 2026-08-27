<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Core\Gate;
use App\Repositories\ForumTopicRepository;
use App\Repositories\ForumPostRepository;
use App\Repositories\ForumCategoryRepository;
use App\Repositories\ForumVoteRepository;
use App\Repositories\ForumPostReactionRepository;
use App\Repositories\TrainingCertificateRepository;
use App\Repositories\ForumAttachmentRepository;
use App\Repositories\ForumNotificationRepository;
use App\Repositories\UserForumStatsRepository;
use App\Repositories\ForumAuthorIdentityRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Repositories\RoleRepository;
use App\Repositories\SiteRoleAssignmentRepository;
use App\Repositories\UserProfileDisplaySettingsRepository;
use App\Services\Forum\ForumPostAttachmentService;
use App\Services\Profile\ProfilePublicIdentityService;

class ForumTopicController
{
    public function __construct(
        private ForumTopicRepository $topicRepository,
        private ForumPostRepository $postRepository,
        private ForumCategoryRepository $categoryRepository,
        private ProfilePublicIdentityService $profilePublicIdentityService,
        private ForumAuthorIdentityRepository $forumAuthorIdentityRepository,
        private ForumVoteRepository $voteRepository,
        private ForumPostReactionRepository $forumPostReactionRepository,
        private TrainingCertificateRepository $trainingCertificateRepository,
        private ForumAttachmentRepository $forumAttachmentRepository,
        private ForumNotificationRepository $forumNotificationRepository,
        private UserForumStatsRepository $userForumStatsRepository,
        private TenantRepository $tenantRepository,
        private ForumPostAttachmentService $forumPostAttachmentService,
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
        private UserProfileDisplaySettingsRepository $userProfileDisplaySettingsRepository,
        private SiteRoleAssignmentRepository $siteRoleAssignmentRepository,
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            Session::flash('error', 'Connectez-vous pour continuer.');
            return Response::redirect(url('login'));
        }

        if (function_exists('forum_disabled_for_member_response')) {
            $blocked = forum_disabled_for_member_response((int) $tenantId);
            if ($blocked !== null) {
                return $blocked;
            }
        }

        $id = (int) ($params['id'] ?? $request->query('topic_id', 0));
        if ($id <= 0) {
            return (new Response())->setStatusCode(404)->setBody('Sujet non trouvé.');
        }
        $topic = $this->topicRepository->findById($id, $tenantId);
        if (!$topic) {
            return (new Response())->setStatusCode(404)->setBody('Sujet non trouvé.');
        }
        if ($this->topicRepository->applyAutoLockIfStale($id, (int) $tenantId)) {
            $topic = $this->topicRepository->findById($id, $tenantId);
            if (!$topic) {
                return (new Response())->setStatusCode(404)->setBody('Sujet non trouvé.');
            }
        }
        $topicEnriched = $this->profilePublicIdentityService->enrichTopicRowsWithPublicNames(
            [$topic],
            $this->forumAuthorIdentityRepository,
            (int) $tenantId
        );
        $topic = $topicEnriched[0];

        $mandatoryReadStatus = null;
        if ((int) ($topic['mandatory_read'] ?? 0) === 1) {
            $this->topicRepository->touchMandatoryReadSeen((int) $tenantId, $id, (int) $userId);
            $mandatoryReadStatus = $this->topicRepository->getMandatoryReadStatus((int) $tenantId, $id, (int) $userId);
        }

        $isModo = function_exists('forum_viewer_is_moderator') && forum_viewer_is_moderator();
        $canPinOnDashboard = $isModo
            || Gate::getInstance()->allows('dashboard.pins.manage')
            || Gate::getInstance()->allows('admin.organization')
            || Gate::getInstance()->allows('admin.access');
        if (!empty($topic['is_hidden']) && !$isModo) {
            return (new Response())->setStatusCode(404)->setBody('Sujet non trouvé.');
        }

        $category = $this->categoryRepository->findById((int) $topic['category_id'], $tenantId);
        $categoryScope = (string) ($topic['category_scope'] ?? $category['scope'] ?? 'general');
        if (function_exists('forum_organization_scope_accessible_for_current_viewer')
            && !forum_organization_scope_accessible_for_current_viewer((int) $tenantId, $categoryScope)) {
            $resp = new Response();
            $resp->setStatusCode(403)->header('Content-Type', 'text/html; charset=utf-8');
            $resp->setBody('<html><body style="background:#050505;color:#a3a3a3;font-family:sans-serif;padding:2rem;text-align:center;"><h1>Accès refusé</h1><p>Ce canal unité n’est pas ouvert aux membres pour le moment.</p><p style="margin-top:1rem"><a style="color:#6ee7b7" href="' . htmlspecialchars(url('forum'), ENT_QUOTES, 'UTF-8') . '">Retour au brief</a></p></body></html>');

            return $resp;
        }
        if ($category && function_exists('forum_can_read') && !forum_can_read($userId, $category)) {
            $resp = new Response();
            $resp->setStatusCode(403)->header('Content-Type', 'text/html; charset=utf-8');
            $resp->setBody('<html><body style="background:#050505;color:#a3a3a3;font-family:sans-serif;padding:2rem;text-align:center;"><h1>Accès refusé</h1><p>Sujet privé dans la caverne.</p></body></html>');
            return $resp;
        }

        $this->topicRepository->incrementViewCount($id);
        $page = max(1, (int) $request->query('page', 1));
        $fcTopic = forum_config_for_tenant((int) $tenantId);
        $perPage = function_exists('forum_pagination_limit')
            ? forum_pagination_limit($fcTopic, 'forum_posts_per_page', 20, 1, 200)
            : 20;
        $postCount = $this->postRepository->countByTopic($id, $isModo);
        $totalPages = max(1, (int) ceil($postCount / $perPage));
        $posts = $this->postRepository->listByTopicPaginated($id, $page, $perPage, $isModo);
        $postIds = array_values(array_filter(array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $posts), static fn (int $pid): bool => $pid > 0));
        $reactionCountMap = $this->forumPostReactionRepository->countByKeysForPosts($postIds);
        $userReactionMap = $this->forumPostReactionRepository->getUserReactionsForPosts((int) $userId, $postIds);
        $authorIds = array_values(array_unique(array_filter(array_map(static fn (array $row): int => (int) ($row['user_id'] ?? 0), $posts), static fn (int $uid): bool => $uid > 0)));
        $certTitleMap = $this->trainingCertificateRepository->latestValidCertifiedCourseTitlesForUsers((int) $tenantId, $authorIds);
        foreach ($posts as $i => $p) {
            // Forum global (scope=platform) : carte auteur allégée pour tout le monde, y compris modérateurs.
            // L’identité sensible reste visible dans le panneau « Modération — identité réelle ».
            if ($categoryScope === 'platform') {
                $p = $this->profilePublicIdentityService->filterAuthorCardForPlatformForum($p);
            }
            $p = $this->profilePublicIdentityService->filterAuthorFieldsForForumViewer($p, $isModo);
            $enriched = $this->profilePublicIdentityService->enrichFromForumPostRow($p);
            $p['author_display_resolved'] = $enriched['public_display_name'];
            if ($isModo) {
                $p['mod_legal_full_name'] = $enriched['legal_full_name'];
                $p['mod_author_email'] = $enriched['author_email'];
                $p['mod_author_user_id'] = $enriched['author_user_id'];
            }
            $p['vote_score'] = $this->voteRepository->sumForPost((int) $p['id']);
            $p['vote_user_value'] = $this->voteRepository->getUserVote((int) $p['id'], (int) $userId);
            $p['attachments'] = $this->forumAttachmentRepository->listForPost((int) $p['id'], (int) $tenantId);
            $pid = (int) $p['id'];
            $p['reaction_counts'] = $reactionCountMap[$pid] ?? [];
            $p['user_reaction_key'] = $userReactionMap[$pid] ?? null;
            $au = (int) ($p['user_id'] ?? 0);
            $p['author_cert_course_title'] = $certTitleMap[$au] ?? null;
            $posts[$i] = $p;
        }
        $byAuthorDisplay = [];
        foreach ($posts as $p) {
            $byAuthorDisplay[(int) $p['id']] = (string) ($p['author_display_resolved'] ?? '');
        }
        foreach ($posts as $i => $p) {
            $ppid = isset($p['parent_post_id']) ? (int) $p['parent_post_id'] : 0;
            $posts[$i]['parent_author_display'] = ($ppid > 0 && isset($byAuthorDisplay[$ppid]))
                ? $byAuthorDisplay[$ppid]
                : null;
        }
        $isSubscribed = $this->topicRepository->isSubscribed($userId, $id);

        $canReply = function_exists('can') && can('forum.reply') && empty($topic['is_locked']) && empty($topic['is_archived']);
        $firstPost = $this->postRepository->getFirstPostOfTopic($id);
        $firstPostId = $firstPost ? (int) $firstPost['id'] : null;
        $forumCfg = forum_config_for_tenant((int) $tenantId);
        $moderationTutorialHtml = (string) ($forumCfg['moderation_tutorial_html'] ?? '');
        $csrfToken = \App\Core\Csrf::token();
        $tenantRow = $this->tenantRepository->findById((int) $tenantId);
        $tenantDisplayName = $tenantRow ? trim((string) ($tenantRow['name'] ?? '')) : '';
        $forumMaxPostLen = (int) ($forumCfg['forum_max_post_length'] ?? forum_get_setting('forum_max_post_length', 10000));

        $topicAuthorIsStaff = function_exists('forum_user_can_moderate_for_user_id')
            && forum_user_can_moderate_for_user_id((int) ($topic['user_id'] ?? 0), (int) $tenantId);
        $topicTrendLevel = function_exists('forum_topic_trend_level') ? forum_topic_trend_level($topic + ['post_count' => $postCount]) : null;
        $topicCreatedTs = strtotime((string) ($topic['created_at'] ?? ''));
        $topicStaleNotice = $topicCreatedTs !== false && $topicCreatedTs < strtotime('-3 months');
        $topicAutoLockedNotice = !empty($topic['auto_locked_at']);

        $userEmail = (string) Session::get('email', '');
        $forumOrgRoleChoices = function_exists('forum_build_visible_role_choices')
            ? forum_build_visible_role_choices(
                (int) $userId,
                (int) $tenantId,
                $userEmail,
                $this->userRepository,
                $this->roleRepository,
                $this->siteRoleAssignmentRepository
            )
            : [];
        $displaySettings = $this->userProfileDisplaySettingsRepository->getOrDefaults((int) $userId);
        $forumVisibleRoleCurrent = isset($displaySettings['forum_visible_role_id']) && $displaySettings['forum_visible_role_id'] !== null && $displaySettings['forum_visible_role_id'] !== ''
            ? (int) $displaySettings['forum_visible_role_id'] : 0;

        return Response::view('layout.forum', [
            'content' => 'forum.topic',
            'title' => $topic['title'],
            'forumConfig' => $forumCfg,
            'categoryScope' => $categoryScope,
            'tenantDisplayName' => $tenantDisplayName,
            'forumMaxPostLen' => $forumMaxPostLen,
            'topic' => $topic,
            'posts' => $posts,
            'page' => $page,
            'totalPages' => $totalPages,
            'postCount' => $postCount,
            'isSubscribed' => $isSubscribed,
            'canReply' => $canReply,
            'isModo' => $isModo,
            'canPinOnDashboard' => $canPinOnDashboard,
            'firstPostId' => $firstPostId,
            'moderationTutorialHtml' => $moderationTutorialHtml,
            'csrfToken' => $csrfToken,
            'topicAuthorIsStaff' => $topicAuthorIsStaff,
            'topicTrendLevel' => $topicTrendLevel,
            'topicStaleNotice' => $topicStaleNotice,
            'topicAutoLockedNotice' => $topicAutoLockedNotice,
            'forumOrgRoleChoices' => $forumOrgRoleChoices,
            'forumVisibleRoleCurrent' => $forumVisibleRoleCurrent,
            'mandatoryReadStatus' => $mandatoryReadStatus,
        ]);
    }

    public function reply(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            Session::flash('error', 'Connectez-vous pour continuer.');
            return Response::redirect(url('login'));
        }

        if (function_exists('forum_disabled_for_member_response')) {
            $blocked = forum_disabled_for_member_response((int) $tenantId);
            if ($blocked !== null) {
                return $blocked;
            }
        }

        if (!function_exists('can') || !can('forum.reply')) {
            Session::flash('error', 'Vous n\'êtes pas autorisé à répondre.');
            return Response::redirect(url('forum'));
        }

        $id = (int) ($params['id'] ?? 0);
        $topic = $this->topicRepository->findById($id, $tenantId);
        if (!$topic) {
            Session::flash('error', 'Sujet non trouvé.');
            return Response::redirect(url('forum'));
        }

        $catReply = $this->categoryRepository->findById((int) $topic['category_id'], (int) $tenantId);
        $scopeReply = (string) ($topic['category_scope'] ?? $catReply['scope'] ?? 'general');
        if (function_exists('forum_organization_scope_accessible_for_current_viewer')
            && !forum_organization_scope_accessible_for_current_viewer((int) $tenantId, $scopeReply)) {
            Session::flash('error', 'Ce canal unité n’accepte pas de nouveaux messages pour le moment.');
            return Response::redirect(url('forum/topic/' . $id));
        }

        if ($topic['is_locked']) {
            Session::flash('error', 'Ce sujet est verrouillé.');
            return Response::redirect(url('forum/topic/' . $id));
        }

        if ($request->method() !== 'POST') {
            return Response::redirect(url('forum/topic/' . $id));
        }

        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            return Response::redirect(url('forum/topic/' . $id));
        }

        $body = trim((string) $request->input('body', ''));
        $attachmentRaw = $request->input('attachment_ids', '[]');
        if (is_string($attachmentRaw)) {
            $attachmentIds = json_decode($attachmentRaw, true);
            if (!is_array($attachmentIds)) {
                $attachmentIds = [];
            }
        } else {
            $attachmentIds = is_array($attachmentRaw) ? $attachmentRaw : [];
        }
        $fc = forum_config_for_tenant((int) $tenantId);
        $maxLen = (int) ($fc['forum_max_post_length'] ?? forum_get_setting('forum_max_post_length', 10000));
        $maxLen = max(500, min(200000, $maxLen));
        $hasBody = strlen($body) >= 1;
        $hasFiles = $attachmentIds !== [];
        if ((!$hasBody && !$hasFiles) || strlen($body) > $maxLen) {
            Session::flash('error', 'Message vide ou trop long.');
            return Response::redirect(url('forum/topic/' . $id));
        }
        if (function_exists('forum_validate_post_text_limits')) {
            $err = forum_validate_post_text_limits((int) $tenantId, $body, $hasFiles, $maxLen);
            if ($err !== null) {
                Session::flash('error', $err);
                return Response::redirect(url('forum/topic/' . $id));
            }
        }
        if (function_exists('forum_cooldown_remaining_seconds')) {
            $wait = forum_cooldown_remaining_seconds((int) $tenantId, (int) $userId);
            if ($wait > 0) {
                Session::flash('error', 'Merci d’attendre encore ' . $wait . ' seconde(s) avant d’envoyer un autre message.');
                return Response::redirect(url('forum/topic/' . $id));
            }
        }

        $postId = $this->postRepository->create($tenantId, $id, $userId, $body);
        $this->topicRepository->touchUpdatedAt($id);
        $this->userForumStatsRepository->incrementPostCount((int) $tenantId, (int) $userId);
        $this->forumPostAttachmentService->attachToPost((int) $tenantId, $postId, (int) $userId, $attachmentIds);
        if (function_exists('forum_after_post_moderation')) {
            forum_after_post_moderation((int) $tenantId, (int) $userId, $postId, $body);
        }
        $this->notifyTopicParticipants((int) $tenantId, $id, (int) $userId, $topic);

        Session::flash('success', 'Réponse publiée.');
        return Response::redirect(url('forum/topic/' . $id));
    }

    private function notifyTopicParticipants(int $tenantId, int $topicId, int $authorUserId, array $topicRow): void
    {
        if (!$this->forumNotificationRepository->tableExists()) {
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
            $title = (string) ($topicRow['title'] ?? 'Sujet');
            foreach ($ids as $uid) {
                if ($uid <= 0) {
                    continue;
                }
                $this->forumNotificationRepository->create($tenantId, $uid, 'topic_reply', [
                    'topic_id' => $topicId,
                    'title' => $title,
                ]);
            }
        } catch (\Throwable) {
            // ignore
        }
    }

    public function subscribe(Request $request, array $params = []): Response
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = Session::get('tenant_id');
        $topic = $this->topicRepository->findById($id, $tenantId);
        if ($topic) {
            $this->topicRepository->subscribe($userId, $id);
            Session::flash('success', 'Vous suivez ce sujet.');
        }
        return Response::redirect(url('forum/topic/' . $id));
    }

    public function unsubscribe(Request $request, array $params = []): Response
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = Session::get('tenant_id');
        $topic = $this->topicRepository->findById($id, $tenantId);
        if ($topic) {
            $this->topicRepository->unsubscribe($userId, $id);
            Session::flash('success', 'Vous ne suivez plus ce sujet.');
        }
        return Response::redirect(url('forum/topic/' . $id));
    }
}
