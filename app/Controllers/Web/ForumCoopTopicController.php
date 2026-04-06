<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ForumAttachmentRepository;
use App\Repositories\ForumAuthorIdentityRepository;
use App\Repositories\ForumCategoryRepository;
use App\Repositories\ForumPostRepository;
use App\Repositories\ForumTopicRepository;
use App\Repositories\ForumVoteRepository;
use App\Repositories\InterteamMissionRepository;
use App\Repositories\TenantRepository;
use App\Services\Profile\ProfilePublicIdentityService;

/**
 * Lecture d’un sujet hébergé par une autre communauté, via mission inter-unités active.
 */
class ForumCoopTopicController
{
    public function __construct(
        private ForumTopicRepository $topicRepository,
        private ForumPostRepository $postRepository,
        private ForumCategoryRepository $categoryRepository,
        private ProfilePublicIdentityService $profilePublicIdentityService,
        private ForumAuthorIdentityRepository $forumAuthorIdentityRepository,
        private ForumVoteRepository $voteRepository,
        private ForumAttachmentRepository $forumAttachmentRepository,
        private TenantRepository $tenantRepository,
        private InterteamMissionRepository $interteamMissionRepository
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        $consumerTenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$consumerTenantId || !$userId) {
            Session::flash('error', 'Authentification requise.');

            return Response::redirect(url('login'));
        }

        if (function_exists('forum_disabled_for_member_response')) {
            $blocked = forum_disabled_for_member_response((int) $consumerTenantId);
            if ($blocked !== null) {
                return $blocked;
            }
        }

        $missionSlug = trim((string) ($params['missionSlug'] ?? ''));
        $id = (int) ($params['id'] ?? 0);
        if ($missionSlug === '' || $id <= 0 || !$this->interteamMissionRepository->tableExists()) {
            return (new Response())->setStatusCode(404)->setBody('Sujet non trouvé.');
        }

        $grant = $this->interteamMissionRepository->findTopicGrantForConsumer(
            $missionSlug,
            $id,
            (int) $consumerTenantId
        );
        if ($grant === null) {
            return (new Response())->setStatusCode(404)->setBody('Sujet non trouvé.');
        }

        $homeTenantId = (int) $grant['home_tenant_id'];
        $mission = $this->interteamMissionRepository->findBySlug($missionSlug);
        $missionTitle = $mission ? trim((string) ($mission['title'] ?? '')) : '';

        $topic = $this->topicRepository->findById($id, $homeTenantId);
        if (!$topic) {
            return (new Response())->setStatusCode(404)->setBody('Sujet non trouvé.');
        }
        if ($this->topicRepository->applyAutoLockIfStale($id, $homeTenantId)) {
            $topic = $this->topicRepository->findById($id, $homeTenantId);
            if (!$topic) {
                return (new Response())->setStatusCode(404)->setBody('Sujet non trouvé.');
            }
        }

        $topicEnriched = $this->profilePublicIdentityService->enrichTopicRowsWithPublicNames(
            [$topic],
            $this->forumAuthorIdentityRepository,
            $homeTenantId
        );
        $topic = $topicEnriched[0];

        $isModo = false;
        if (!empty($topic['is_hidden'])) {
            return (new Response())->setStatusCode(404)->setBody('Sujet non trouvé.');
        }

        $category = $this->categoryRepository->findById((int) $topic['category_id'], $homeTenantId);
        $categoryScope = (string) ($topic['category_scope'] ?? $category['scope'] ?? 'general');
        if ($category && function_exists('forum_can_read') && !forum_can_read((int) $userId, $category)) {
            $resp = new Response();
            $resp->setStatusCode(403)->header('Content-Type', 'text/html; charset=utf-8');
            $resp->setBody('<html><body style="background:#050505;color:#a3a3a3;font-family:sans-serif;padding:2rem;text-align:center;"><h1>Accès refusé</h1><p>Ce contenu n’est pas disponible pour votre profil.</p></body></html>');

            return $resp;
        }

        $this->topicRepository->incrementViewCount($id);
        $page = max(1, (int) $request->query('page', 1));
        $fcTopic = forum_config_for_tenant((int) $consumerTenantId);
        $perPage = function_exists('forum_pagination_limit')
            ? forum_pagination_limit($fcTopic, 'forum_posts_per_page', 20, 1, 200)
            : 20;
        $postCount = $this->postRepository->countByTopic($id, $isModo);
        $totalPages = max(1, (int) ceil($postCount / $perPage));
        $posts = $this->postRepository->listByTopicPaginated($id, $page, $perPage, $isModo);
        foreach ($posts as $i => $p) {
            if ($categoryScope === 'platform') {
                $p = $this->profilePublicIdentityService->filterAuthorCardForPlatformForum($p);
            }
            $p = $this->profilePublicIdentityService->filterAuthorFieldsForForumViewer($p, $isModo);
            $enriched = $this->profilePublicIdentityService->enrichFromForumPostRow($p);
            $p['author_display_resolved'] = $enriched['public_display_name'];
            $p['vote_score'] = $this->voteRepository->sumForPost((int) $p['id']);
            $p['vote_user_value'] = $this->voteRepository->getUserVote((int) $p['id'], (int) $userId);
            $p['attachments'] = $this->forumAttachmentRepository->listForPost((int) $p['id'], $homeTenantId);
            $posts[$i] = $p;
        }

        $firstPost = $this->postRepository->getFirstPostOfTopic($id);
        $firstPostId = $firstPost ? (int) $firstPost['id'] : null;
        $csrfToken = \App\Core\Csrf::token();
        $homeRow = $this->tenantRepository->findById($homeTenantId);
        $tenantDisplayName = $homeRow ? trim((string) ($homeRow['name'] ?? '')) : '';
        $forumMaxPostLen = (int) ($fcTopic['forum_max_post_length'] ?? forum_get_setting('forum_max_post_length', 10000));

        $topicAuthorIsStaff = function_exists('forum_user_can_moderate_for_user_id')
            && forum_user_can_moderate_for_user_id((int) ($topic['user_id'] ?? 0), $homeTenantId);
        $topicTrendLevel = function_exists('forum_topic_trend_level') ? forum_topic_trend_level($topic + ['post_count' => $postCount]) : null;
        $topicCreatedTs = strtotime((string) ($topic['created_at'] ?? ''));
        $topicStaleNotice = $topicCreatedTs !== false && $topicCreatedTs < strtotime('-3 months');
        $topicAutoLockedNotice = !empty($topic['auto_locked_at']);

        return Response::view('layout.forum', [
            'content' => 'forum.topic',
            'title' => $topic['title'],
            'forumConfig' => $fcTopic,
            'categoryScope' => $categoryScope,
            'tenantDisplayName' => $tenantDisplayName,
            'forumMaxPostLen' => $forumMaxPostLen,
            'topic' => $topic,
            'posts' => $posts,
            'page' => $page,
            'totalPages' => $totalPages,
            'postCount' => $postCount,
            'isSubscribed' => false,
            'canReply' => false,
            'isModo' => $isModo,
            'firstPostId' => $firstPostId,
            'moderationTutorialHtml' => '',
            'csrfToken' => $csrfToken,
            'topicAuthorIsStaff' => $topicAuthorIsStaff,
            'topicTrendLevel' => $topicTrendLevel,
            'topicStaleNotice' => $topicStaleNotice,
            'topicAutoLockedNotice' => $topicAutoLockedNotice,
            'forumOrgRoleChoices' => [],
            'forumVisibleRoleCurrent' => 0,
            'interteamCoopReadOnly' => true,
            'interteamMissionTitle' => $missionTitle,
            'interteamMissionSlug' => $missionSlug,
        ]);
    }
}
