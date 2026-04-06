<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ForumCategoryRepository;
use App\Repositories\ForumTopicRepository;
use App\Repositories\ForumAuthorIdentityRepository;
use App\Services\Profile\ProfilePublicIdentityService;

class ForumCategoryController
{
    public function __construct(
        private ForumCategoryRepository $categoryRepository,
        private ForumTopicRepository $topicRepository,
        private ForumAuthorIdentityRepository $forumAuthorIdentityRepository,
        private ProfilePublicIdentityService $profilePublicIdentityService
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            Session::flash('error', 'Authentification requise.');
            return Response::redirect(url('login'));
        }

        if (function_exists('forum_disabled_for_member_response')) {
            $blocked = forum_disabled_for_member_response((int) $tenantId);
            if ($blocked !== null) {
                return $blocked;
            }
        }

        $slug = (string) ($params['slug'] ?? $request->query('category', ''));
        if ($slug === '') {
            return (new Response())->setStatusCode(404)->setBody('Catégorie non trouvée.');
        }
        $category = $this->categoryRepository->findBySlug($slug, $tenantId);
        if (!$category) {
            return (new Response())->setStatusCode(404)->setBody('Catégorie non trouvée.');
        }

        if (!function_exists('forum_can_read') || !forum_can_read($userId, $category)) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé à cette catégorie.');
        }

        $catScope = (string) ($category['scope'] ?? 'general');
        if (function_exists('forum_organization_scope_accessible_for_current_viewer')
            && !forum_organization_scope_accessible_for_current_viewer((int) $tenantId, $catScope)) {
            Session::flash('error', 'Ce canal unité n’est pas ouvert aux membres pour le moment.');

            return Response::redirect(url('forum'));
        }

        $isModo = function_exists('forum_viewer_is_moderator') && forum_viewer_is_moderator();
        $canCreate = function_exists('can') && can('forum.create_topic') && forum_can_read($userId, $category);
        $filter = (string) $request->query('filter', '');
        $sort = (string) $request->query('sort', 'activity');
        $q = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));
        $fcat = forum_config_for_tenant((int) $tenantId);
        $perPage = function_exists('forum_pagination_limit')
            ? forum_pagination_limit($fcat, 'forum_topics_per_page', 20, 1, 200)
            : 20;

        if ($q !== '') {
            $topics = $this->topicRepository->searchByCategory((int) $category['id'], $q, $tenantId, $isModo, 100);
            $topics = $this->profilePublicIdentityService->enrichTopicRowsWithPublicNames(
                $topics,
                $this->forumAuthorIdentityRepository,
                (int) $tenantId
            );
            foreach ($topics as $ti => $tr) {
                $topics[$ti]['topic_author_is_staff'] = function_exists('forum_user_can_moderate_for_user_id')
                    && forum_user_can_moderate_for_user_id((int) ($tr['user_id'] ?? 0), (int) $tenantId);
                $topics[$ti]['topic_trend_level'] = function_exists('forum_topic_trend_level') ? forum_topic_trend_level($tr) : null;
            }
            $totalTopics = count($topics);
            $totalPages = 1;
            $topics = array_slice($topics, ($page - 1) * $perPage, $perPage);
        } else {
            $totalTopics = $this->topicRepository->countByCategory((int) $category['id'], $tenantId, $filter ?: null, $userId, $isModo);
            $totalPages = max(1, (int) ceil($totalTopics / $perPage));
            $topics = $this->topicRepository->listByCategory(
                (int) $category['id'],
                $tenantId,
                $page,
                $perPage,
                $sort,
                $filter ?: null,
                $userId,
                $isModo
            );
            $topics = $this->profilePublicIdentityService->enrichTopicRowsWithPublicNames(
                $topics,
                $this->forumAuthorIdentityRepository,
                (int) $tenantId
            );
            foreach ($topics as $ti => $tr) {
                $topics[$ti]['topic_author_is_staff'] = function_exists('forum_user_can_moderate_for_user_id')
                    && forum_user_can_moderate_for_user_id((int) ($tr['user_id'] ?? 0), (int) $tenantId);
                $topics[$ti]['topic_trend_level'] = function_exists('forum_topic_trend_level') ? forum_topic_trend_level($tr) : null;
            }
        }

        $subcategories = $this->categoryRepository->getSubcategories((int) $category['id'], $tenantId);
        $isSubscribed = $this->categoryRepository->isSubscribedCategory($userId, (int) $category['id']);

        $buildCategoryUrl = function (array $overrides = []) use ($slug, $page, $sort, $filter, $q): string {
            $merged = array_merge(
                ['page' => $page, 'sort' => $sort, 'filter' => $filter, 'q' => $q],
                $overrides
            );
            return forum_build_category_url($slug, $merged);
        };

        return Response::view('layout.forum', [
            'content' => 'forum.category',
            'title' => $category['name'],
            'forumConfig' => forum_config_for_tenant((int) $tenantId),
            'category' => $category,
            'topics' => $topics,
            'subcategories' => $subcategories,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalTopics' => $totalTopics,
            'perPage' => $perPage,
            'sort' => $sort,
            'filter' => $filter,
            'q' => $q,
            'canCreate' => $canCreate,
            'isSubscribed' => $isSubscribed,
            'isModo' => $isModo,
            'buildCategoryUrl' => $buildCategoryUrl,
        ]);
    }
}
