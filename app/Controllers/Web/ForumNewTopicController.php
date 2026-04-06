<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Core\Validator;
use App\Repositories\ForumCategoryRepository;
use App\Repositories\ForumTopicRepository;
use App\Repositories\ForumPostRepository;
use App\Repositories\UserForumStatsRepository;
use App\Repositories\TenantRepository;

class ForumNewTopicController
{
    public function __construct(
        private ForumCategoryRepository $categoryRepository,
        private ForumTopicRepository $topicRepository,
        private ForumPostRepository $postRepository,
        private UserForumStatsRepository $userForumStatsRepository,
        private TenantRepository $tenantRepository,
    ) {}

    private function effectiveTenantId(Request $request): int
    {
        $sessionTid = (int) Session::get('tenant_id');
        if (!Gate::getInstance()->allows('admin.system')) {
            return $sessionTid;
        }
        $ctx = (int) $request->input('forum_tenant', 0);
        if ($ctx < 1) {
            $ctx = (int) $request->query('forum_tenant', 0);
        }
        if ($ctx > 1 && $this->tenantRepository->findById($ctx)) {
            return $ctx;
        }

        return $sessionTid;
    }

    private function newTopicRedirectUrl(Request $request): string
    {
        $base = url('forum/new-topic');
        if (!Gate::getInstance()->allows('admin.system')) {
            return $base;
        }
        $ctx = (int) $request->input('forum_tenant', 0);
        if ($ctx < 1) {
            $ctx = (int) $request->query('forum_tenant', 0);
        }
        if ($ctx > 1 && $this->tenantRepository->findById($ctx)) {
            return $base . '?forum_tenant=' . $ctx;
        }

        return $base;
    }

    private function forumIndexUrl(Request $request): string
    {
        $base = url('forum');
        if (!Gate::getInstance()->allows('admin.system')) {
            return $base;
        }
        $ctx = (int) $request->input('forum_tenant', 0);
        if ($ctx < 1) {
            $ctx = (int) $request->query('forum_tenant', 0);
        }
        if ($ctx > 1 && $this->tenantRepository->findById($ctx)) {
            return $base . '?forum_tenant=' . $ctx;
        }

        return $base;
    }

    public function form(Request $request, array $params = []): Response
    {
        $sessionTenantId = (int) Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$sessionTenantId || !$userId) {
            Session::flash('error', 'Authentification requise.');
            return Response::redirect(url('login'));
        }

        $tenantId = $this->effectiveTenantId($request);
        $forumNewTopicTenantContext = ($tenantId !== $sessionTenantId && $tenantId > 1) ? $tenantId : 0;

        if (function_exists('forum_disabled_for_member_response')) {
            $blocked = forum_disabled_for_member_response($tenantId);
            if ($blocked !== null) {
                return $blocked;
            }
        }

        if (!function_exists('can') || !can('forum.create_topic')) {
            Session::flash('error', 'Vous n\'êtes pas autorisé à créer un sujet.');
            return Response::redirect($this->forumIndexUrl($request));
        }

        if (function_exists('forum_is_enabled') && !forum_is_enabled()) {
            $isModo = function_exists('forum_viewer_is_moderator') && forum_viewer_is_moderator();
            if (!$isModo) {
                return Response::redirect($this->forumIndexUrl($request));
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
            'forumNewTopicTenantContext' => $forumNewTopicTenantContext,
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $sessionTenantId = (int) Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$sessionTenantId || !$userId) {
            Session::flash('error', 'Authentification requise.');
            return Response::redirect(url('login'));
        }

        $tenantId = $this->effectiveTenantId($request);

        if (function_exists('forum_disabled_for_member_response')) {
            $blocked = forum_disabled_for_member_response($tenantId);
            if ($blocked !== null) {
                return $blocked;
            }
        }

        if (!function_exists('can') || !can('forum.create_topic')) {
            Session::flash('error', 'Vous n\'êtes pas autorisé à créer un sujet.');
            return Response::redirect($this->forumIndexUrl($request));
        }

        if ($request->method() !== 'POST') {
            return Response::redirect($this->newTopicRedirectUrl($request));
        }

        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            return Response::redirect($this->newTopicRedirectUrl($request));
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
            return Response::redirect($this->newTopicRedirectUrl($request));
        }

        $category = $this->categoryRepository->findById($categoryId, $tenantId);
        if (!$category) {
            Session::flash('error', 'Catégorie invalide.');
            return Response::redirect($this->newTopicRedirectUrl($request));
        }

        $newScope = (string) ($category['scope'] ?? 'general');
        if (function_exists('forum_organization_scope_accessible_for_current_viewer')
            && !forum_organization_scope_accessible_for_current_viewer((int) $tenantId, $newScope)) {
            Session::flash('error', 'Ce canal unité n’accepte pas de nouveaux sujets pour le moment.');
            return Response::redirect($this->newTopicRedirectUrl($request));
        }

        if (strlen($title) < 3 || strlen($title) > 255) {
            Session::flash('error', 'Le titre doit faire entre 3 et 255 caractères.');
            return Response::redirect($this->newTopicRedirectUrl($request));
        }
        $fc = forum_config_for_tenant((int) $tenantId);
        $maxLen = (int) ($fc['forum_max_post_length'] ?? forum_get_setting('forum_max_post_length', 10000));
        $maxLen = max(500, min(200000, $maxLen));
        if (strlen($body) < 5 || strlen($body) > $maxLen) {
            Session::flash('error', 'Le contenu doit faire entre 5 et ' . $maxLen . ' caractères.');
            return Response::redirect($this->newTopicRedirectUrl($request));
        }
        if (function_exists('forum_validate_post_text_limits')) {
            $err = forum_validate_post_text_limits((int) $tenantId, $body, false, $maxLen);
            if ($err !== null) {
                Session::flash('error', $err);
                return Response::redirect($this->newTopicRedirectUrl($request));
            }
        }
        if (function_exists('forum_cooldown_remaining_seconds')) {
            $wait = forum_cooldown_remaining_seconds((int) $tenantId, (int) $userId);
            if ($wait > 0) {
                Session::flash('error', 'Merci d’attendre encore ' . $wait . ' seconde(s) avant de publier.');
                return Response::redirect($this->newTopicRedirectUrl($request));
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
