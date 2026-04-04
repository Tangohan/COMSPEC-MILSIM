<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Core\Validator;
use App\Repositories\ForumTopicRepository;
use App\Repositories\ForumPostRepository;
use App\Repositories\ForumCategoryRepository;

class ForumTopicController
{
    public function __construct(
        private ForumTopicRepository $topicRepository,
        private ForumPostRepository $postRepository,
        private ForumCategoryRepository $categoryRepository
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            Session::flash('error', 'Authentification requise.');
            return Response::redirect(url('login'));
        }

        $id = (int) ($params['id'] ?? $request->query('topic_id', 0));
        if ($id <= 0) {
            return (new Response())->setStatusCode(404)->setBody('Sujet non trouvé.');
        }
        $topic = $this->topicRepository->findById($id, $tenantId);
        if (!$topic) {
            return (new Response())->setStatusCode(404)->setBody('Sujet non trouvé.');
        }

        $isModo = function_exists('can') && can('forum.moderate');
        if (!empty($topic['is_hidden']) && !$isModo) {
            return (new Response())->setStatusCode(404)->setBody('Sujet non trouvé.');
        }

        $category = $this->categoryRepository->findById((int) $topic['category_id'], $tenantId);
        if ($category && function_exists('forum_can_read') && !forum_can_read($userId, $category)) {
            $resp = new Response();
            $resp->setStatusCode(403)->header('Content-Type', 'text/html; charset=utf-8');
            $resp->setBody('<html><body style="background:#050505;color:#a3a3a3;font-family:sans-serif;padding:2rem;text-align:center;"><h1>Accès refusé</h1><p>Sujet privé dans la caverne.</p></body></html>');
            return $resp;
        }

        $this->topicRepository->incrementViewCount($id);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;
        $postCount = $this->postRepository->countByTopic($id, $isModo);
        $totalPages = max(1, (int) ceil($postCount / $perPage));
        $posts = $this->postRepository->listByTopicPaginated($id, $page, $perPage, $isModo);
        $isSubscribed = $this->topicRepository->isSubscribed($userId, $id);

        $canReply = function_exists('can') && can('forum.reply') && empty($topic['is_locked']) && empty($topic['is_archived']);
        $firstPost = $this->postRepository->getFirstPostOfTopic($id);
        $firstPostId = $firstPost ? (int) $firstPost['id'] : null;
        $moderationTutorialHtml = (string) (config('forum')['moderation_tutorial_html'] ?? '');

        return Response::view('layout.forum', [
            'content' => 'forum.topic',
            'title' => $topic['title'],
            'forumConfig' => config('forum') ?? [],
            'topic' => $topic,
            'posts' => $posts,
            'page' => $page,
            'totalPages' => $totalPages,
            'postCount' => $postCount,
            'isSubscribed' => $isSubscribed,
            'canReply' => $canReply,
            'isModo' => $isModo,
            'firstPostId' => $firstPostId,
            'moderationTutorialHtml' => $moderationTutorialHtml,
        ]);
    }

    public function reply(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            Session::flash('error', 'Authentification requise.');
            return Response::redirect(url('login'));
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
        $validator = new Validator(['body' => $body], ['body' => 'required']);
        if (!$validator->validate()) {
            Session::flash('error', $validator->errors()['body'][0] ?? 'Contenu invalide.');
            return Response::redirect(url('forum/topic/' . $id));
        }

        $this->postRepository->create($tenantId, $id, $userId, $body);
        $this->topicRepository->touchUpdatedAt($id);

        Session::flash('success', 'Réponse publiée.');
        return Response::redirect(url('forum/topic/' . $id));
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
