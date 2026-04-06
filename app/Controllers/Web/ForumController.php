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
        private ProfilePublicIdentityService $profilePublicIdentityService
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            Session::flash('error', 'Authentification requise.');
            return Response::redirect(url('login'));
        }

        $tree = $this->categoryRepository->listForTenantWithChildren($tenantId);
        if (function_exists('forum_filter_category_tree_for_user')) {
            $tree = forum_filter_category_tree_for_user($tree, (int) $userId);
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
            (int) $tenantId
        );
        $forumOrganizationCategories = $this->profilePublicIdentityService->enrichCategoryRowsWithLastAuthor(
            $forumOrganizationCategories,
            $this->forumAuthorIdentityRepository,
            (int) $tenantId
        );
        $forumModerationCategories = $this->profilePublicIdentityService->enrichCategoryRowsWithLastAuthor(
            $forumModerationCategories,
            $this->forumAuthorIdentityRepository,
            (int) $tenantId
        );
        $topicCount = $this->topicRepository->getTotalTopicCount($tenantId);
        $postCount = $this->postRepository->getTotalPostCount($tenantId);
        $postsThisWeek = $this->postRepository->getPostsThisWeekCount($tenantId);
        $activeMembers24h = $this->postRepository->getActiveMembersCount24h($tenantId);
        $recentTopics = $this->topicRepository->getRecentForIndex($tenantId, 10);
        $recentTopics = $this->profilePublicIdentityService->enrichRecentTopicRows(
            $recentTopics,
            $this->forumAuthorIdentityRepository,
            (int) $tenantId
        );
        $topContributors = $this->postRepository->getTopContributors($tenantId, 10);
        $topContributors = $this->profilePublicIdentityService->enrichContributorRows(
            $topContributors,
            $this->forumAuthorIdentityRepository,
            (int) $tenantId
        );
        $pendingReports = $this->reportRepository->listPending($tenantId);

        $moderationArtifactsTableAvailable = false;
        $contentModerationQueueCount = 0;
        if (function_exists('forum_user_can_moderate') && forum_user_can_moderate()) {
            $moderationArtifactsTableAvailable = $this->moderationArtifactRepository->tableExists();
            if ($moderationArtifactsTableAvailable) {
                $contentModerationQueueCount = $this->moderationArtifactRepository->countQueue((int) $tenantId, null);
            }
        }

        $searchQuery = trim((string) $request->query('q', ''));
        $searchResults = [];
        if ($searchQuery !== '') {
            $searchResults = $this->topicRepository->search($tenantId, $searchQuery, 30);
            $searchResults = $this->profilePublicIdentityService->enrichTopicRowsWithPublicNames(
                $searchResults,
                $this->forumAuthorIdentityRepository,
                (int) $tenantId
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
            $pinnedAnnouncements = $this->topicRepository->getPinnedInCategory((int) $announcementsCategory['id'], $tenantId);
            $pinnedAnnouncements = $this->profilePublicIdentityService->enrichTopicRowsWithPublicNames(
                $pinnedAnnouncements,
                $this->forumAuthorIdentityRepository,
                (int) $tenantId
            );
        }

        $forumCfg = forum_config_for_tenant((int) $tenantId);
        $gate = Gate::getInstance();
        $forumCanCreateSubcategory = function_exists('forum_user_can_moderate') && forum_user_can_moderate();
        $forumFullCategoryAdmin = $gate->allows('admin.access')
            || (function_exists('can') && can('forum.categories.manage'));
        $forumContextMenuEnabled = $forumCanCreateSubcategory || $forumFullCategoryAdmin;

        return Response::view('layout.forum', [
            'content' => 'forum.index',
            'title' => $forumCfg['name'] ?? 'Forum',
            'forumConfig' => $forumCfg,
            'forumCanCreateSubcategory' => $forumCanCreateSubcategory,
            'forumFullCategoryAdmin' => $forumFullCategoryAdmin,
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
        ]);
    }
}
