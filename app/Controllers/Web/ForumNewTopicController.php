<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Core\Validator;
use App\Repositories\ForumCategoryRepository;
use App\Repositories\ForumTopicRepository;
use App\Repositories\ForumPostRepository;

class ForumNewTopicController
{
    public function __construct(
        private ForumCategoryRepository $categoryRepository,
        private ForumTopicRepository $topicRepository,
        private ForumPostRepository $postRepository
    ) {}

    public function form(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            Session::flash('error', 'Authentification requise.');
            return Response::redirect(url('login'));
        }

        if (!function_exists('can') || !can('forum.create_topic')) {
            Session::flash('error', 'Vous n\'êtes pas autorisé à créer un sujet.');
            return Response::redirect(url('forum'));
        }

        $categories = $this->categoryRepository->listForTenant($tenantId);
        $preselectedSlug = (string) $request->query('category', '');

        return Response::view('layout.forum', [
            'content' => 'forum.new-topic',
            'title' => 'Nouveau sujet',
            'forumConfig' => config('forum') ?? [],
            'categories' => $categories,
            'preselectedSlug' => $preselectedSlug,
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            Session::flash('error', 'Authentification requise.');
            return Response::redirect(url('login'));
        }

        if (!function_exists('can') || !can('forum.create_topic')) {
            Session::flash('error', 'Vous n\'êtes pas autorisé à créer un sujet.');
            return Response::redirect(url('forum'));
        }

        if ($request->method() !== 'POST') {
            return Response::redirect(url('forum/new-topic'));
        }

        if (!Csrf::validate($request->post('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            return Response::redirect(url('forum/new-topic'));
        }

        $categoryId = (int) $request->post('category_id', 0);
        $title = trim((string) $request->post('title', ''));
        $body = trim((string) $request->post('body', ''));

        $validator = new Validator(
            ['title' => $title, 'body' => $body, 'category_id' => $categoryId],
            ['title' => 'required', 'body' => 'required', 'category_id' => 'required']
        );
        if (!$validator->validate()) {
            $errors = $validator->errors();
            Session::flash('error', $errors['title'][0] ?? $errors['body'][0] ?? $errors['category_id'][0] ?? 'Données invalides.');
            return Response::redirect(url('forum/new-topic'));
        }

        $category = $this->categoryRepository->findById($categoryId, $tenantId);
        if (!$category) {
            Session::flash('error', 'Catégorie invalide.');
            return Response::redirect(url('forum/new-topic'));
        }

        $slug = $this->slugify($title);
        if ($slug === '') {
            $slug = 'sujet-' . time();
        }
        $slug = $slug . '-' . substr(uniqid('', true), -6);

        $topicId = $this->topicRepository->create($tenantId, $categoryId, $userId, $title, $slug);
        $this->postRepository->create($tenantId, $topicId, $userId, $body);
        $this->topicRepository->touchUpdatedAt($topicId);

        Session::flash('success', 'Sujet créé.');
        return Response::redirect(url('forum/topic/' . $topicId));
    }

    private function slugify(string $text): string
    {
        $text = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $text);
        $text = preg_replace('/[\s-]+/', '-', trim($text));
        return strtolower($text);
    }
}
