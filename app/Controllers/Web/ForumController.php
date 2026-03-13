<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ForumCategoryRepository;
use App\Repositories\ForumTopicRepository;
use App\Repositories\ForumPostRepository;
use App\Repositories\ForumReportRepository;

class ForumController
{
    public function __construct(
        private ForumCategoryRepository $categoryRepository,
        private ForumTopicRepository $topicRepository,
        private ForumPostRepository $postRepository,
        private ForumReportRepository $reportRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            Session::flash('error', 'Authentification requise.');
            return Response::redirect(url('login'));
        }

        $categories = $this->categoryRepository->listForTenant($tenantId);
        $topicCount = $this->topicRepository->getTotalTopicCount($tenantId);
        $postCount = $this->postRepository->getTotalPostCount($tenantId);
        $postsThisWeek = $this->postRepository->getPostsThisWeekCount($tenantId);
        $activeMembers24h = $this->postRepository->getActiveMembersCount24h($tenantId);
        $recentTopics = $this->topicRepository->getRecentForIndex($tenantId, 10);
        $topContributors = $this->postRepository->getTopContributors($tenantId, 10);
        $pendingReports = $this->reportRepository->listPending($tenantId);

        $searchQuery = trim((string) $request->query('q', ''));
        $searchResults = [];
        if ($searchQuery !== '') {
            $searchResults = $this->topicRepository->search($tenantId, $searchQuery, 30);
        }

        $pinnedAnnouncements = [];
        $announcementsCategory = null;
        foreach ($categories as $cat) {
            if ($cat['slug'] === 'annonces') {
                $announcementsCategory = $cat;
                break;
            }
        }
        if ($announcementsCategory) {
            $pinnedAnnouncements = $this->topicRepository->getPinnedInCategory((int) $announcementsCategory['id'], $tenantId);
        }

        return Response::view('layout.forum', [
            'content' => 'forum.index',
            'title' => config('forum.name') ?? 'Forum',
            'forumConfig' => config('forum') ?? [],
            'categories' => $categories,
            'topicCount' => $topicCount,
            'postCount' => $postCount,
            'postsThisWeek' => $postsThisWeek,
            'activeMembers24h' => $activeMembers24h,
            'recentTopics' => $recentTopics,
            'topContributors' => $topContributors,
            'pendingReports' => $pendingReports,
            'searchQuery' => $searchQuery,
            'searchResults' => $searchResults,
            'pinnedAnnouncements' => $pinnedAnnouncements,
            'announcementsCategory' => $announcementsCategory,
        ]);
    }
}
