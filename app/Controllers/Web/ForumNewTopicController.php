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
use App\Repositories\UserForumStatsRepository;

class ForumNewTopicController
{
    public function __construct(
        private ForumCategoryRepository $categoryRepository,
        private ForumTopicRepository $topicRepository,
        private ForumPostRepository $postRepository,
        private UserForumStatsRepository $userForumStatsRepository
    ) {}

    public function form(Request $request, array $params = []): Response
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

        if (!function_exists('can') || !can('forum.create_topic')) {
            Session::flash('error', 'Vous n\'êtes pas autorisé à créer un sujet.');
            return Response::redirect(url('forum'));
        }

        if (function_exists('forum_is_enabled') && !forum_is_enabled()) {
            $isModo = function_exists('forum_viewer_is_moderator') && forum_viewer_is_moderator();
            if (!$isModo) {
                return Response::redirect(url('forum'));
            }
        }

        $categoriesWithChildren = $this->categoryRepository->listForTenantWithChildren($tenantId);
        if (function_exists('forum_filter_category_tree_for_user')) {
            $categoriesWithChildren = forum_filter_category_tree_for_user($categoriesWithChildren, (int) $userId);
        }
        if (function_exists('forum_community_section_open_for_current_viewer')
            && !forum_community_section_open_for_current_viewer((int) $tenantId)) {
            $stripOrg = static function (array $nodes) use (&$stripOrg): array {
                $out = [];
                foreach ($nodes as $n) {
                    if (!is_array($n) || (($n['scope'] ?? '') === 'organization')) {
                        continue;
                    }
                    if (!empty($n['children']) && is_array($n['children'])) {
                        $n['children'] = $stripOrg($n['children']);
                    }
                    $out[] = $n;
                }

                return $out;
            };
            $categoriesWithChildren = $stripOrg($categoriesWithChildren);
        }
        $preselectedCategoryId = 0;
        $categoryIdFromQuery = (int) $request->query('category_id', 0);
        if ($categoryIdFromQuery > 0) {
            $cat = $this->categoryRepository->findById($categoryIdFromQuery, $tenantId);
            if ($cat && (!function_exists('forum_can_read') || forum_can_read($userId, $cat))) {
                $orgClosed = function_exists('forum_community_section_open_for_current_viewer')
                    && !forum_community_section_open_for_current_viewer((int) $tenantId);
                if (!$orgClosed || (($cat['scope'] ?? '') !== 'organization')) {
                    $preselectedCategoryId = $categoryIdFromQuery;
                }
            }
        }
        $fc = forum_config_for_tenant((int) $tenantId);
        $maxLen = (int) ($fc['forum_max_post_length'] ?? 10000);

        return Response::view('layout.forum', [
            'content' => 'forum.new-topic',
            'title' => 'Nouveau sujet',
            'forumConfig' => $fc,
            'categoriesWithChildren' => $categoriesWithChildren,
            'preselectedCategoryId' => $preselectedCategoryId,
            'maxLen' => $maxLen,
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

        if (function_exists('forum_disabled_for_member_response')) {
            $blocked = forum_disabled_for_member_response((int) $tenantId);
            if ($blocked !== null) {
                return $blocked;
            }
        }

        if (!function_exists('can') || !can('forum.create_topic')) {
            Session::flash('error', 'Vous n\'êtes pas autorisé à créer un sujet.');
            return Response::redirect(url('forum'));
        }

        if ($request->method() !== 'POST') {
            return Response::redirect(url('forum/new-topic'));
        }

        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            return Response::redirect(url('forum/new-topic'));
        }

        $categoryId = (int) $request->input('category_id', 0);
        $title = trim((string) $request->input('title', ''));
        $body = trim((string) $request->input('body', ''));

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

        $newScope = (string) ($category['scope'] ?? 'general');
        if (function_exists('forum_organization_scope_accessible_for_current_viewer')
            && !forum_organization_scope_accessible_for_current_viewer((int) $tenantId, $newScope)) {
            Session::flash('error', 'Ce canal unité n’accepte pas de nouveaux sujets pour le moment.');
            return Response::redirect(url('forum/new-topic'));
        }

        if (strlen($title) < 3 || strlen($title) > 255) {
            Session::flash('error', 'Le titre doit faire entre 3 et 255 caractères.');
            return Response::redirect(url('forum/new-topic'));
        }
        $fc = forum_config_for_tenant((int) $tenantId);
        $maxLen = (int) ($fc['forum_max_post_length'] ?? forum_get_setting('forum_max_post_length', 10000));
        $maxLen = max(500, min(200000, $maxLen));
        if (strlen($body) < 5 || strlen($body) > $maxLen) {
            Session::flash('error', 'Le contenu doit faire entre 5 et ' . $maxLen . ' caractères.');
            return Response::redirect(url('forum/new-topic'));
        }
        if (function_exists('forum_validate_post_text_limits')) {
            $err = forum_validate_post_text_limits((int) $tenantId, $body, false, $maxLen);
            if ($err !== null) {
                Session::flash('error', $err);
                return Response::redirect(url('forum/new-topic'));
            }
        }
        if (function_exists('forum_cooldown_remaining_seconds')) {
            $wait = forum_cooldown_remaining_seconds((int) $tenantId, (int) $userId);
            if ($wait > 0) {
                Session::flash('error', 'Merci d’attendre encore ' . $wait . ' seconde(s) avant de publier.');
                return Response::redirect(url('forum/new-topic'));
            }
        }

        $slug = $this->slugify($title);
        if ($slug === '') {
            $slug = 'sujet-' . time();
        }
        $slug = $slug . '-' . substr(uniqid('', true), -6);

        $topicId = $this->topicRepository->create($tenantId, $categoryId, $userId, $title, $slug);
        $firstPostId = $this->postRepository->create($tenantId, $topicId, $userId, $body);
        $this->topicRepository->touchUpdatedAt($topicId);
        $this->userForumStatsRepository->incrementPostCount((int) $tenantId, (int) $userId);
        if (function_exists('forum_after_post_moderation')) {
            forum_after_post_moderation((int) $tenantId, (int) $userId, $firstPostId, $body);
        }

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
