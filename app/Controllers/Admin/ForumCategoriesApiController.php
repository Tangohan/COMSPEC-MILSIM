<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Core\Gate;
use App\Repositories\ForumCategoryRepository;

class ForumCategoriesApiController
{
    public function __construct(
        private ForumCategoryRepository $forumCategoryRepository
    ) {}

    /**
     * Admin / gestionnaire de catégories : toutes les actions.
     * Modérateurs forum : création de sous-catégorie (parent_id racine) uniquement.
     */
    private function authorize(Request $request): bool
    {
        $gate = Gate::getInstance();
        if ($gate->allows('admin.access')) {
            return true;
        }
        if (function_exists('can') && can('forum.categories.manage')) {
            return true;
        }
        $action = (string) $request->input('action', '');
        if ($action === 'create') {
            $parentRaw = $request->input('parent_id');
            $parentId = $parentRaw !== null && $parentRaw !== '' ? (int) $parentRaw : 0;
            if ($parentId > 0 && function_exists('forum_user_can_moderate') && forum_user_can_moderate()) {
                return true;
            }
        }

        return false;
    }

    public function handle(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::json(['success' => false, 'message' => 'Non authentifié'], 401);
        }
        if (!$this->authorize($request)) {
            return Response::json(['success' => false, 'message' => 'Non autorisé'], 403);
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            return Response::json(['success' => false, 'message' => 'Jeton CSRF invalide'], 403);
        }

        $action = $request->input('action', '');
        $tenantId = (int) $tenantId;

        return match ($action) {
            'create' => $this->create($request, $tenantId),
            'update' => $this->update($request, $tenantId),
            'lock' => $this->lock($request, $tenantId),
            'delete' => $this->delete($request, $tenantId),
            'reorder' => $this->reorder($request, $tenantId),
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
