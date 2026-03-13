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

class ForumTopicController
{
    public function __construct(
        private ForumTopicRepository $topicRepository,
        private ForumPostRepository $postRepository
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            Session::flash('error', 'Authentification requise.');
            return Response::redirect(url('login'));
        }

        $id = (int) ($params['id'] ?? 0);
        $topic = $this->topicRepository->findById($id, $tenantId);
        if (!$topic) {
            return (new Response())->setStatusCode(404)->setBody('Sujet non trouvé.');
        }

        $this->topicRepository->incrementViewCount($id);
        $posts = $this->postRepository->listByTopic($id);
        $isSubscribed = $this->topicRepository->isSubscribed($userId, $id);

        return Response::view('layout.forum', [
            'content' => 'forum.topic',
            'title' => $topic['title'],
            'forumConfig' => config('forum') ?? [],
            'topic' => $topic,
            'posts' => $posts,
            'isSubscribed' => $isSubscribed,
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

        if (!Csrf::validate($request->post('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            return Response::redirect(url('forum/topic/' . $id));
        }

        $body = trim((string) $request->post('body', ''));
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
