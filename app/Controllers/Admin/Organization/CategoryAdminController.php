<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CategoryRepository;

class CategoryAdminController
{
    public function __construct(
        private CategoryRepository $categoryRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $type = $request->input('type');
        $categories = $this->categoryRepository->allForTenant($tenantId, $type !== '' ? $type : null);
        return Response::view('layout.main', [
            'content' => 'admin.organization.categories.index',
            'title' => 'Catégories',
            'categories' => $categories,
            'filterType' => $type,
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        return Response::view('layout.main', [
            'content' => 'admin.organization.categories.create',
            'title' => 'Nouvelle catégorie',
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            Session::flash('error', 'Le nom est requis.');
            return Response::redirect(url('back-office/categories/create'));
        }
        $slug = trim((string) $request->input('slug')) ?: preg_replace('/[^a-z0-9]+/i', '-', strtolower($name));
        if ($this->categoryRepository->slugExists($tenantId, $slug)) {
            Session::flash('error', 'Ce slug existe déjà.');
            return Response::redirect(url('back-office/categories/create'));
        }
        $this->categoryRepository->create($tenantId, [
            'name' => $name,
            'slug' => $slug,
            'type' => trim((string) ($request->input('type') ?: 'organizational')),
            'description' => trim((string) $request->input('description')) ?: null,
            'color' => trim((string) $request->input('color')) ?: null,
            'display_order' => (int) $request->input('display_order'),
        ]);
        Session::flash('success', 'Catégorie créée.');
        return Response::redirect(url('back-office/categories'));
    }

    public function edit(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('back-office/categories'));
        }
        $category = $this->categoryRepository->findById($id, $tenantId);
        if (!$category) {
            Session::flash('error', 'Catégorie introuvable.');
            return Response::redirect(url('back-office/categories'));
        }
        return Response::view('layout.main', [
            'content' => 'admin.organization.categories.edit',
            'title' => 'Modifier la catégorie',
            'category' => $category,
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('back-office/categories'));
        }
        $category = $this->categoryRepository->findById($id, $tenantId);
        if (!$category) {
            Session::flash('error', 'Catégorie introuvable.');
            return Response::redirect(url('back-office/categories'));
        }
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            Session::flash('error', 'Le nom est requis.');
            return Response::redirect(url('back-office/categories/' . $id . '/edit'));
        }
        $slug = trim((string) $request->input('slug')) ?: preg_replace('/[^a-z0-9]+/i', '-', strtolower($name));
        if ($this->categoryRepository->slugExists($tenantId, $slug, $id)) {
            Session::flash('error', 'Ce slug existe déjà.');
            return Response::redirect(url('back-office/categories/' . $id . '/edit'));
        }
        $this->categoryRepository->update($id, $tenantId, [
            'name' => $name,
            'slug' => $slug,
            'type' => trim((string) ($request->input('type') ?: 'organizational')),
            'description' => trim((string) $request->input('description')) ?: null,
            'color' => trim((string) $request->input('color')) ?: null,
            'display_order' => (int) $request->input('display_order'),
            'is_active' => $request->input('is_active') ? 1 : 0,
        ]);
        Session::flash('success', 'Catégorie mise à jour.');
        return Response::redirect(url('back-office/categories'));
    }
}
