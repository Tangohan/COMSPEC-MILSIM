<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ForumCategoryRepository;
use App\Repositories\ForumTopicRepository;

class ForumCategoryController
{
    public function __construct(
        private ForumCategoryRepository $categoryRepository,
        private ForumTopicRepository $topicRepository
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            Session::flash('error', 'Authentification requise.');
            return Response::redirect(url('login'));
        }

        $slug = $params['slug'] ?? '';
        $category = $this->categoryRepository->findBySlug($slug, $tenantId);
        if (!$category) {
            return (new Response())->setStatusCode(404)->setBody('Catégorie non trouvée.');
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;
        $sort = (string) $request->query('sort', 'activity');
        $totalTopics = $this->topicRepository->countByCategory((int) $category['id'], $tenantId);
        $totalPages = max(1, (int) ceil($totalTopics / $perPage));
        $topics = $this->topicRepository->listByCategory((int) $category['id'], $tenantId, $page, $perPage, $sort);
        $subcategories = $this->categoryRepository->getSubcategories((int) $category['id'], $tenantId);

        return Response::view('layout.forum', [
            'content' => 'forum.category',
            'title' => $category['name'],
            'forumConfig' => config('forum') ?? [],
            'category' => $category,
            'topics' => $topics,
            'subcategories' => $subcategories,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalTopics' => $totalTopics,
            'perPage' => $perPage,
            'sort' => $sort,
        ]);
    }
}
