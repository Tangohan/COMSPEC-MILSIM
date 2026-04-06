<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Repositories\ForumCategoryRepository;
use App\Repositories\ForumTopicRepository;
use App\Repositories\ForumPostRepository;
use App\Repositories\ForumReportRepository;
use App\Repositories\ModerationArtifactRepository;
use App\Repositories\ForumAuthorIdentityRepository;
use App\Repositories\InterteamMissionRepository;
use App\Repositories\TenantRepository;
use App\Services\Profile\ProfilePublicIdentityService;

class ForumController
{
    public function __construct(
        private ForumCategoryRepository $categoryRepository,
        private ForumTopicRepository $topicRepository,
        private ForumPostRepository $postRepository,
        private ForumReportRepository $reportRepository,
        private ModerationArtifactRepository $moderationArtifactRepository,
        private ForumAuthorIdentityRepository $forumAuthorIdentityRepository,
        private ProfilePublicIdentityService $profilePublicIdentityService,
        private InterteamMissionRepository $interteamMissionRepository,
        private TenantRepository $tenantRepository,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $sessionTenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$sessionTenantId || !$userId) {
            Session::flash('error', 'Authentification requise.');

            return Response::redirect(url('login'));
        }
        $sessionTenantId = (int) $sessionTenantId;
        $userId = (int) $userId;

        $gate = Gate::getInstance();
        $forumDataTenantId = $sessionTenantId;
        $forumViewTenantSwitcher = [];
        $forumEffectiveTenantName = '';
        if ($gate->allows('admin.system')) {
            $forumViewTenantSwitcher = $this->tenantRepository->listBasicAll();
            $reqTid = (int) $request->query('forum_tenant', 0);
            if ($reqTid > 1) {
                $trow = $this->tenantRepository->findById($reqTid);
                if ($trow) {
                    $forumDataTenantId = $reqTid;
                    $forumEffectiveTenantName = trim((string) ($trow['name'] ?? ''));
                }
            }
        }

        if (function_exists('forum_disabled_for_member_response')) {
            $blocked = forum_disabled_for_member_response($forumDataTenantId);
            if ($blocked !== null) {
                return $blocked;
            }
        }

        $tree = $this->categoryRepository->listForTenantWithChildren($forumDataTenantId);
        if (function_exists('forum_filter_category_tree_for_user')) {
            $tree = forum_filter_category_tree_for_user($tree, $userId);
        }
        $forumGeneralCategories = [];
        $forumOrganizationCategories = [];
        $forumModerationCategories = [];
        foreach ($tree as $c) {
            $scope = $c['scope'] ?? 'general';
            if ($scope === 'organization') {
                $forumOrganizationCategories[] = $c;
            } elseif ($scope === 'moderation') {
                $forumModerationCategories[] = $c;
            } else {
                $forumGeneralCategories[] = $c;
            }
        }
        $forumGeneralCategories = $this->profilePublicIdentityService->enrichCategoryRowsWithLastAuthor(
            $forumGeneralCategories,
            $this->forumAuthorIdentityRepository,
            $forumDataTenantId
        );
        $forumOrganizationCategories = $this->profilePublicIdentityService->enrichCategoryRowsWithLastAuthor(
            $forumOrganizationCategories,
            $this->forumAuthorIdentityRepository,
            $forumDataTenantId
        );
        $forumModerationCategories = $this->profilePublicIdentityService->enrichCategoryRowsWithLastAuthor(
            $forumModerationCategories,
            $this->forumAuthorIdentityRepository,
            $forumDataTenantId
        );

        $forumCfg = forum_config_for_tenant($forumDataTenantId);
        $forumCommunitySectionClosedNotice = null;
        if (function_exists('forum_community_section_open_for_current_viewer')
            && !forum_community_section_open_for_current_viewer($forumDataTenantId)) {
            $forumCommunitySectionClosedNotice = trim((string) ($forumCfg['community_section_notice'] ?? ''));
            if ($forumCommunitySectionClosedNotice === '') {
                $forumCommunitySectionClosedNotice = 'Cet espace est indisponible ici pour le moment. Pour les échanges d’unité, suivez les canaux indiqués par votre encadrement.';
            }
            $forumOrganizationCategories = [];
        }

        $interteamSharedTopics = [];
        if ($this->interteamMissionRepository->tableExists()) {
            $interteamSharedTopics = $this->interteamMissionRepository->listSharedTopicsForConsumer($forumDataTenantId, 40);
        }

        $topicCount = $this->topicRepository->getTotalTopicCount($forumDataTenantId);
        $postCount = $this->postRepository->getTotalPostCount($forumDataTenantId);
        $postsThisWeek = $this->postRepository->getPostsThisWeekCount($forumDataTenantId);
        $activeMembers24h = $this->postRepository->getActiveMembersCount24h($forumDataTenantId);
        $recentTopics = $this->topicRepository->getRecentForIndex($forumDataTenantId, 10);
        $recentTopics = $this->profilePublicIdentityService->enrichRecentTopicRows(
            $recentTopics,
            $this->forumAuthorIdentityRepository,
            $forumDataTenantId
        );
        $topContributors = $this->postRepository->getTopContributors($forumDataTenantId, 10);
        $topContributors = $this->profilePublicIdentityService->enrichContributorRows(
            $topContributors,
            $this->forumAuthorIdentityRepository,
            $forumDataTenantId
        );
        $pendingReports = $this->reportRepository->listPending($forumDataTenantId);

        $moderationArtifactsTableAvailable = false;
        $contentModerationQueueCount = 0;
        if (function_exists('forum_user_can_moderate') && forum_user_can_moderate()) {
            $moderationArtifactsTableAvailable = $this->moderationArtifactRepository->tableExists();
            if ($moderationArtifactsTableAvailable) {
                $contentModerationQueueCount = $this->moderationArtifactRepository->countQueue($forumDataTenantId, null);
            }
        }

        $searchQuery = trim((string) $request->query('q', ''));
        $searchResults = [];
        if ($searchQuery !== '') {
            $searchResults = $this->topicRepository->search($forumDataTenantId, $searchQuery, 30);
            $searchResults = $this->profilePublicIdentityService->enrichTopicRowsWithPublicNames(
                $searchResults,
                $this->forumAuthorIdentityRepository,
                $forumDataTenantId
            );
        }

        $pinnedAnnouncements = [];
        $announcementsCategory = null;
        foreach ($forumGeneralCategories as $cat) {
            if (($cat['slug'] ?? '') === 'annonces') {
                $announcementsCategory = $cat;
                break;
            }
        }
        if ($announcementsCategory) {
            $pinnedAnnouncements = $this->topicRepository->getPinnedInCategory((int) $announcementsCategory['id'], $forumDataTenantId);
            $pinnedAnnouncements = $this->profilePublicIdentityService->enrichTopicRowsWithPublicNames(
                $pinnedAnnouncements,
                $this->forumAuthorIdentityRepository,
                $forumDataTenantId
            );
        }

        $forumCanCreateSubcategory = function_exists('forum_user_can_moderate') && forum_user_can_moderate();
        $forumFullCategoryAdmin = $gate->allows('admin.access')
            || $gate->allows('admin.system')
            || (function_exists('can') && can('forum.categories.manage'));
        $forumContextMenuEnabled = $forumCanCreateSubcategory || $forumFullCategoryAdmin;
        $forumCanDeleteCategoryMenu = $forumCanCreateSubcategory || $forumFullCategoryAdmin;

        return Response::view('layout.forum', [
            'content' => 'forum.index',
            'title' => $forumCfg['name'] ?? 'Forum',
            'forumConfig' => $forumCfg,
            'forumCanCreateSubcategory' => $forumCanCreateSubcategory,
            'forumFullCategoryAdmin' => $forumFullCategoryAdmin,
            'forumCanDeleteCategoryMenu' => $forumCanDeleteCategoryMenu,
            'forumContextMenuEnabled' => $forumContextMenuEnabled,
            'forumCsrfToken' => Csrf::token(),
            'forumCategoriesApiUrl' => url('api/admin/forum-categories'),
            'forumAdminForumConfigUrl' => url('admin/forum-config'),
            'categories' => $forumGeneralCategories,
            'forumOrganizationCategories' => $forumOrganizationCategories,
            'forumModerationCategories' => $forumModerationCategories,
            'topicCount' => $topicCount,
            'postCount' => $postCount,
            'postsThisWeek' => $postsThisWeek,
            'activeMembers24h' => $activeMembers24h,
            'recentTopics' => $recentTopics,
            'topContributors' => $topContributors,
            'pendingReports' => $pendingReports,
            'moderationArtifactsTableAvailable' => $moderationArtifactsTableAvailable,
            'contentModerationQueueCount' => $contentModerationQueueCount,
            'searchQuery' => $searchQuery,
            'searchResults' => $searchResults,
            'pinnedAnnouncements' => $pinnedAnnouncements,
            'announcementsCategory' => $announcementsCategory,
            'forumCommunitySectionClosedNotice' => $forumCommunitySectionClosedNotice,
            'interteamSharedTopics' => $interteamSharedTopics,
            'forumContextTenantId' => $forumDataTenantId,
            'forumSessionTenantId' => $sessionTenantId,
            'forumViewTenantSwitcher' => $forumViewTenantSwitcher,
            'forumEffectiveTenantName' => $forumEffectiveTenantName,
        ]);
    }
}
