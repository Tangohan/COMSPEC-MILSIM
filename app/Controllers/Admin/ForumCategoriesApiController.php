<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Core\Gate;
use App\Repositories\ForumCategoryRepository;
use App\Repositories\TenantRepository;

class ForumCategoriesApiController
{
    public function __construct(
        private ForumCategoryRepository $forumCategoryRepository,
        private TenantRepository $tenantRepository,
    ) {}

    /**
     * Résout le tenant cible : session, ou `context_tenant_id` pour les super-administrateurs.
     */
    private function resolveTenantId(Request $request): int
    {
        $sessionTid = (int) Session::get('tenant_id');
        if ($sessionTid < 1) {
            return 0;
        }
        $gate = Gate::getInstance();
        if ($gate->allows('admin.system')) {
            $ctx = (int) $request->input('context_tenant_id', 0);
            if ($ctx > 1 && $this->tenantRepository->findById($ctx)) {
                return $ctx;
            }
        }

        return $sessionTid;
    }

    /**
     * Gestionnaire / admin catégories : actions complètes.
     * Modérateurs forum : création sous-catégorie ; suppression d’une sous-catégorie vide uniquement.
     */
    private function authorize(Request $request, int $tenantId): bool
    {
        $gate = Gate::getInstance();
        $action = (string) $request->input('action', '');
        if ($gate->allows('admin.access') || $gate->allows('admin.system')) {
            return true;
        }
        if (function_exists('can') && can('forum.categories.manage')) {
            return true;
        }
        if ($action === 'create') {
            $parentRaw = $request->input('parent_id');
            $parentId = $parentRaw !== null && $parentRaw !== '' ? (int) $parentRaw : 0;
            if ($parentId > 0 && function_exists('forum_user_can_moderate') && forum_user_can_moderate()) {
                return true;
            }
        }
        if ($action === 'delete' && function_exists('forum_user_can_moderate') && forum_user_can_moderate()) {
            $id = (int) $request->input('id', 0);

            return $id > 0 && $this->moderatorMayDeleteCategory($id, $tenantId);
        }

        return false;
    }

    private function moderatorMayDeleteCategory(int $categoryId, int $tenantId): bool
    {
        $cat = $this->forumCategoryRepository->findById($categoryId, $tenantId);
        if (!$cat) {
            return false;
        }
        $pid = $cat['parent_id'] ?? null;

        return $pid !== null && (int) $pid > 0;
    }

    public function handle(Request $request, array $params = []): Response
    {
        if (!Session::get('tenant_id')) {
            return Response::json(['success' => false, 'message' => 'Non authentifié'], 401);
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            return Response::json(['success' => false, 'message' => 'Jeton CSRF invalide'], 403);
        }

        $effectiveTenantId = $this->resolveTenantId($request);
        if ($effectiveTenantId < 1) {
            return Response::json(['success' => false, 'message' => 'Communauté invalide'], 400);
        }

        if (!$this->authorize($request, $effectiveTenantId)) {
            return Response::json(['success' => false, 'message' => 'Non autorisé'], 403);
        }

        $action = $request->input('action', '');

        return match ($action) {
            'create' => $this->create($request, $effectiveTenantId),
            'update' => $this->update($request, $effectiveTenantId),
            'lock' => $this->lock($request, $effectiveTenantId),
            'delete' => $this->delete($request, $effectiveTenantId),
            'reorder' => $this->reorder($request, $effectiveTenantId),
            default => Response::json(['success' => false, 'message' => 'Action inconnue'], 400),
        };
    }

    private function create(Request $request, int $tenantId): Response
    {
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            return Response::json(['success' => false, 'message' => 'Nom requis'], 400);
        }
        $slug = trim((string) $request->input('slug', ''));
        if ($slug === '') {
            $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($name));
            $slug = trim($slug, '-') ?: 'categorie';
        }
        $parentRaw = $request->input('parent_id');
        $parentId = $parentRaw !== null && $parentRaw !== '' ? (int) $parentRaw : null;
        $scope = trim((string) $request->input('scope', 'general'));
        if (!in_array($scope, ['general', 'organization', 'platform', 'moderation'], true)) {
            $scope = 'general';
        }
        try {
            $id = $this->forumCategoryRepository->create($tenantId, [
                'name' => $name,
                'slug' => $slug,
                'description' => trim((string) $request->input('description', '')),
                'display_order' => (int) $request->input('display_order', 0),
                'parent_id' => $parentId,
                'scope' => $scope,
                'owner_tenant_id' => $request->input('owner_tenant_id') !== null && $request->input('owner_tenant_id') !== ''
                    ? (int) $request->input('owner_tenant_id') : null,
            ]);
        } catch (\InvalidArgumentException $e) {
            return Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Duplicate') || str_contains($msg, '1062')) {
                return Response::json(['success' => false, 'message' => 'Ce slug existe déjà pour cette communauté.'], 400);
            }
            throw $e;
        }

        return Response::json(['success' => true, 'id' => $id]);
    }

    private function update(Request $request, int $tenantId): Response
    {
        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return Response::json(['success' => false, 'message' => 'ID requis'], 400);
        }
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            return Response::json(['success' => false, 'message' => 'Nom requis'], 400);
        }
        $payload = [
            'name' => $name,
            'slug' => trim((string) $request->input('slug', '')),
            'description' => trim((string) $request->input('description', '')),
            'display_order' => (int) $request->input('display_order', 0),
        ];
        if ($request->input('parent_id') !== null) {
            $payload['parent_id'] = $request->input('parent_id') === '' ? null : (int) $request->input('parent_id');
        }
        $scopeIn = $request->input('scope');
        if ($scopeIn !== null && $scopeIn !== '') {
            $payload['scope'] = trim((string) $scopeIn);
        }
        if ($request->input('owner_tenant_id') !== null) {
            $payload['owner_tenant_id'] = $request->input('owner_tenant_id') === '' ? null : (int) $request->input('owner_tenant_id');
        }
        try {
            $ok = $this->forumCategoryRepository->update($id, $tenantId, $payload);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Duplicate') || str_contains($msg, '1062')) {
                return Response::json(['success' => false, 'message' => 'Ce slug existe déjà pour cette communauté.'], 400);
            }
            throw $e;
        }

        return $ok ? Response::json(['success' => true]) : Response::json(['success' => false, 'message' => 'Catégorie introuvable'], 404);
    }

    private function lock(Request $request, int $tenantId): Response
    {
        $id = (int) $request->input('id', 0);
        $locked = in_array($request->input('locked'), ['1', 1, true], true);
        if ($id <= 0) {
            return Response::json(['success' => false, 'message' => 'ID requis'], 400);
        }
        $ok = $this->forumCategoryRepository->setLocked($id, $tenantId, $locked);

        return $ok ? Response::json(['success' => true]) : Response::json(['success' => false, 'message' => 'Catégorie introuvable'], 404);
    }

    private function delete(Request $request, int $tenantId): Response
    {
        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return Response::json(['success' => false, 'message' => 'ID requis'], 400);
        }
        if ($this->forumCategoryRepository->countChildren($id, $tenantId) > 0) {
            return Response::json(['success' => false, 'message' => 'Supprimez d’abord les sous-catégories.'], 400);
        }
        if ($this->forumCategoryRepository->countTopicsInCategory($id, $tenantId) > 0) {
            return Response::json(['success' => false, 'message' => 'La catégorie contient encore des sujets.'], 400);
        }
        $ok = $this->forumCategoryRepository->delete($id, $tenantId);

        return $ok ? Response::json(['success' => true]) : Response::json(['success' => false, 'message' => 'Catégorie introuvable'], 404);
    }

    private function reorder(Request $request, int $tenantId): Response
    {
        $order = $request->input('order');
        if (is_string($order)) {
            $order = json_decode($order, true);
        }
        if (!is_array($order)) {
            return Response::json(['success' => false, 'message' => 'Ordre invalide'], 400);
        }
        $this->forumCategoryRepository->reorder($tenantId, array_values($order));

        return Response::json(['success' => true]);
    }
}
