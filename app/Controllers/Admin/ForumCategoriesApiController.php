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

    public function handle(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::json(['success' => false, 'message' => 'Non authentifié'], 401);
        }
        if (!Gate::getInstance()->allows('admin.access') && !(function_exists('can') && can('forum.categories.manage'))) {
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
        $id = $this->forumCategoryRepository->create($tenantId, [
            'name' => $name,
            'slug' => $slug,
            'description' => trim((string) $request->input('description', '')),
            'display_order' => (int) $request->input('display_order', 0),
        ]);
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
        $ok = $this->forumCategoryRepository->update($id, $tenantId, [
            'name' => $name,
            'slug' => trim((string) $request->input('slug', '')),
            'description' => trim((string) $request->input('description', '')),
            'display_order' => (int) $request->input('display_order', 0),
        ]);
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
