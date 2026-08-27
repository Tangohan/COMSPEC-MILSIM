<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ForumAttachmentRepository;
use App\Repositories\ForumAuthorIdentityRepository;
use App\Repositories\ForumCategoryRepository;
use App\Repositories\ForumNotificationRepository;
use App\Repositories\ForumPostRepository;
use App\Repositories\ForumPostReactionRepository;
use App\Repositories\ForumTopicRepository;
use App\Repositories\ForumVoteRepository;
use App\Repositories\TrainingCertificateRepository;
use App\Repositories\InterteamMissionRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Cooperation\CooperationPhasePolicy;
use App\Services\Profile\ProfilePublicIdentityService;

/**
 * Sujet du brief hébergé par une autre communauté (coopération inter-unités).
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
        private ForumPostReactionRepository $forumPostReactionRepository,
        private TrainingCertificateRepository $trainingCertificateRepository,
        private ForumAttachmentRepository $forumAttachmentRepository,
        private TenantRepository $tenantRepository,
        private InterteamMissionRepository $interteamMissionRepository,
        private ForumNotificationRepository $forumNotificationRepository,
        private UserRepository $userRepository
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        $consumerTenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$consumerTenantId || !$userId) {
            Session::flash('error', 'Connectez-vous pour continuer.');

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
        $missionId = $mission ? (int) ($mission['id'] ?? 0) : 0;

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
        $postIds = array_values(array_filter(array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $posts), static fn (int $pid): bool => $pid > 0));
        $reactionCountMap = $this->forumPostReactionRepository->countByKeysForPosts($postIds);
        $userReactionMap = $this->forumPostReactionRepository->getUserReactionsForPosts((int) $userId, $postIds);
        $authorIds = array_values(array_unique(array_filter(array_map(static fn (array $row): int => (int) ($row['user_id'] ?? 0), $posts), static fn (int $uid): bool => $uid > 0)));
        $certTitleMap = $this->trainingCertificateRepository->latestValidCertifiedCourseTitlesForUsers($homeTenantId, $authorIds);
        $coopTenantNames = $this->buildCoopTenantNameMap($posts);
        foreach ($posts as $i => $p) {
            if ($categoryScope === 'platform') {
                $p = $this->profilePublicIdentityService->filterAuthorCardForPlatformForum($p);
            }
            $p = $this->profilePublicIdentityService->filterAuthorFieldsForForumViewer($p, $isModo);
            $enriched = $this->profilePublicIdentityService->enrichFromForumPostRow($p);
            $p['author_display_resolved'] = $enriched['public_display_name'];
            $ctid = isset($p['coop_source_tenant_id']) ? (int) $p['coop_source_tenant_id'] : 0;
            if ($ctid > 0 && isset($coopTenantNames[$ctid])) {
                $p['author_display_resolved'] .= ' — ' . $coopTenantNames[$ctid];
            }
            $p['vote_score'] = $this->voteRepository->sumForPost((int) $p['id']);
            $p['vote_user_value'] = $this->voteRepository->getUserVote((int) $p['id'], (int) $userId);
            $p['attachments'] = $this->forumAttachmentRepository->listForPost((int) $p['id'], $homeTenantId);
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

        $firstPost = $this->postRepository->getFirstPostOfTopic($id);
        $firstPostId = $firstPost ? (int) $firstPost['id'] : null;
        $csrfToken = Csrf::token();
        $homeRow = $this->tenantRepository->findById($homeTenantId);
        $tenantDisplayName = $homeRow ? trim((string) ($homeRow['name'] ?? '')) : '';
        $forumMaxPostLen = (int) ($fcTopic['forum_max_post_length'] ?? forum_get_setting('forum_max_post_length', 10000));

        $topicAuthorIsStaff = function_exists('forum_user_can_moderate_for_user_id')
            && forum_user_can_moderate_for_user_id((int) ($topic['user_id'] ?? 0), $homeTenantId);
        $topicTrendLevel = function_exists('forum_topic_trend_level') ? forum_topic_trend_level($topic + ['post_count' => $postCount]) : null;
        $topicCreatedTs = strtotime((string) ($topic['created_at'] ?? ''));
        $topicStaleNotice = $topicCreatedTs !== false && $topicCreatedTs < strtotime('-3 months');
        $topicAutoLockedNotice = !empty($topic['auto_locked_at']);

        $consentOk = $missionId <= 0
            || !$this->interteamMissionRepository->consentsTableExists()
            || $this->interteamMissionRepository->hasVerifiedConsent($missionId, (int) $userId);
        $canReplyForum = function_exists('can') && can('forum.reply');
        $canCoopWrite = !function_exists('can')
            || can('cooperation.exchange.write')
            || can('forum.reply')
            || can('cooperation.missions.manage')
            || can('interteam.missions.manage');
        $missionRow = $mission && $missionId > 0 ? $this->interteamMissionRepository->findById($missionId) : null;
        $isHostTenant = $missionRow && (int) ($missionRow['created_by_tenant_id'] ?? 0) === (int) $consumerTenantId;
        $policyOk = !$missionRow || CooperationPhasePolicy::allowsCrossTenantExchangeWrite(
            $missionRow,
            (int) $consumerTenantId,
            $isHostTenant
        );
        $canReply = $consentOk && $canReplyForum && $canCoopWrite && $policyOk && empty($topic['is_locked']) && empty($topic['is_archived']);

        $baseUrl = rtrim(url(''), '/');
        $paginationBase = $baseUrl . '/forum/coop/' . rawurlencode($missionSlug) . '/sujet/' . $id;
        $replyUrl = $paginationBase . '/repondre';

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
            'canReply' => $canReply,
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
            'interteamCoopReadOnly' => !$canReply,
            'interteamMissionTitle' => $missionTitle,
            'interteamMissionSlug' => $missionSlug,
            'interteamCoopReplyUrl' => $replyUrl,
            'interteamCoopPaginationBase' => $paginationBase,
        ]);
    }

    public function reply(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');

            return $this->redirectBackToCoopTopic($params);
        }
        $consumerTenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($consumerTenantId <= 0 || $userId <= 0) {
            return Response::redirect(url('login'));
        }
        $missionSlug = trim((string) ($params['missionSlug'] ?? ''));
        $topicId = (int) ($params['id'] ?? 0);
        if ($missionSlug === '' || $topicId <= 0) {
            return Response::redirect(url('forum'));
        }
        $mission = $this->interteamMissionRepository->findBySlug($missionSlug);
        $missionId = $mission ? (int) ($mission['id'] ?? 0) : 0;
        if ($missionId > 0 && $this->interteamMissionRepository->consentsTableExists()
            && !$this->interteamMissionRepository->hasVerifiedConsent($missionId, $userId)) {
            Session::flash('error', 'Vous devez d’abord confirmer votre autorisation de partage pour cette coopération.');

            return Response::redirect(cooperation_mission_consent_url($missionId));
        }
        $grant = $this->interteamMissionRepository->findTopicGrantForConsumer($missionSlug, $topicId, $consumerTenantId);
        if ($grant === null) {
            Session::flash('error', 'Vous ne pouvez pas répondre sur ce fil.');

            return Response::redirect(url('forum'));
        }
        if (!function_exists('can') || !can('forum.reply')) {
            Session::flash('error', 'Vous n’avez pas l’habilitation pour répondre sur le forum.');

            return $this->redirectBackToCoopTopic($params);
        }
        $homeTenantId = (int) $grant['home_tenant_id'];
        $topic = $this->topicRepository->findById($topicId, $homeTenantId);
        if (!$topic || !empty($topic['is_locked']) || !empty($topic['is_archived']) || !empty($topic['is_hidden'])) {
            Session::flash('error', 'Ce fil n’accepte plus de réponses.');

            return $this->redirectBackToCoopTopic($params);
        }
        $missionRow = $mission ? $this->interteamMissionRepository->findById($missionId) : null;
        $isHostTenant = $missionRow && (int) ($missionRow['created_by_tenant_id'] ?? 0) === $consumerTenantId;
        if ($missionRow && !CooperationPhasePolicy::allowsCrossTenantExchangeWrite($missionRow, $consumerTenantId, $isHostTenant)) {
            Session::flash('error', 'L’espace commun est momentanément en consultation seule selon les règles de cette coopération.');

            return $this->redirectBackToCoopTopic($params);
        }
        $body = trim((string) $request->input('body', ''));
        $fc = forum_config_for_tenant($consumerTenantId);
        $maxLen = (int) ($fc['forum_max_post_length'] ?? forum_get_setting('forum_max_post_length', 10000));
        if ($body === '' || strlen($body) > $maxLen) {
            Session::flash('error', 'Message vide ou trop long.');

            return $this->redirectBackToCoopTopic($params);
        }
        $user = $this->userRepository->findById($userId, $consumerTenantId);
        if (!$user || (int) ($user['tenant_id'] ?? 0) !== $consumerTenantId) {
            Session::flash('error', 'Compte invalide pour cette action.');

            return Response::redirect(url('forum'));
        }
        $officialRaw = trim((string) $request->input('coop_official_kind', 'discussion'));
        $allowedKinds = array_keys(\App\Support\CooperationDictionary::officialMessageKindChoices());
        $officialKind = in_array($officialRaw, $allowedKinds, true) ? $officialRaw : 'discussion';
        $parentPostId = (int) $request->input('parent_post_id', 0);
        $parentPostId = $parentPostId > 0 ? $parentPostId : null;
        $this->postRepository->create($homeTenantId, $topicId, $userId, $body, $parentPostId, $consumerTenantId, $officialKind === 'discussion' ? null : $officialKind, false, null);
        $this->topicRepository->touchUpdatedAt($topicId);
        if ($missionId > 0) {
            $this->interteamMissionRepository->logEvent($missionId, $userId, $consumerTenantId, 'coop_forum_reply', [
                'topic_id' => $topicId,
                'official_kind' => $officialKind,
            ]);
            if ($officialKind === 'decision') {
                $this->interteamMissionRepository->logEvent($missionId, $userId, $consumerTenantId, 'decision_published', [
                    'topic_id' => $topicId,
                ]);
            }
        }
        $this->notifyCoopParticipants(
            $homeTenantId,
            $consumerTenantId,
            $topicId,
            (string) ($topic['title'] ?? 'Sujet'),
            $userId,
            (string) ($mission['slug'] ?? '')
        );

        Session::flash('success', 'Message publié.');

        return $this->redirectBackToCoopTopic($params);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function redirectBackToCoopTopic(array $params): Response
    {
        $missionSlug = trim((string) ($params['missionSlug'] ?? ''));
        $id = (int) ($params['id'] ?? 0);
        if ($missionSlug === '' || $id <= 0) {
            return Response::redirect(url('forum'));
        }

        return Response::redirect(url('forum/coop/' . rawurlencode($missionSlug) . '/sujet/' . $id));
    }

    /**
     * @param list<array<string, mixed>> $posts
     * @return array<int, string>
     */
    private function buildCoopTenantNameMap(array $posts): array
    {
        $ids = [];
        foreach ($posts as $p) {
            $tid = isset($p['coop_source_tenant_id']) ? (int) $p['coop_source_tenant_id'] : 0;
            if ($tid > 0) {
                $ids[$tid] = true;
            }
        }
        $out = [];
        foreach (array_keys($ids) as $tid) {
            $row = $this->tenantRepository->findById($tid);
            if ($row) {
                $out[$tid] = trim((string) ($row['name'] ?? 'Unité'));
            }
        }

        return $out;
    }

    private function notifyCoopParticipants(
        int $homeTenantId,
        int $authorConsumerTenantId,
        int $topicId,
        string $title,
        int $authorUserId,
        string $missionSlug
    ): void {
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
            foreach ($ids as $uid) {
                $uid = (int) $uid;
                if ($uid <= 0) {
                    continue;
                }
                $u = $this->userRepository->findById($uid);
                $recipientTenantId = $u ? (int) ($u['tenant_id'] ?? 0) : 0;
                if ($recipientTenantId > 0) {
                    $this->forumNotificationRepository->create($recipientTenantId, $uid, 'interteam_coop_reply', [
                        'topic_id' => $topicId,
                        'title' => $title,
                        'mission_slug' => $missionSlug,
                    ]);
                }
            }
            if ($authorConsumerTenantId !== $homeTenantId) {
                $stmt2 = $pdo->prepare('SELECT created_by_user_id FROM interteam_missions m
                    INNER JOIN interteam_mission_forum_grants g ON g.mission_id = m.id
                    WHERE g.grant_type = \'topic\' AND g.resource_id = ? AND g.home_tenant_id = ? LIMIT 1');
                $stmt2->execute([$topicId, $homeTenantId]);
                $leadUid = (int) $stmt2->fetchColumn();
                if ($leadUid > 0 && $leadUid !== $authorUserId) {
                    $this->forumNotificationRepository->create($homeTenantId, $leadUid, 'interteam_coop_reply', [
                        'topic_id' => $topicId,
                        'title' => $title,
                        'mission_slug' => $missionSlug,
                    ]);
                }
            }
        } catch (\Throwable) {
        }
    }
}
