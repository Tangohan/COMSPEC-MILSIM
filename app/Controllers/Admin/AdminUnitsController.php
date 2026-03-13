<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;

class AdminUnitsController
{
    public function __construct(
        private UnitRepository $unitRepository,
        private UserRepository $userRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $units = $this->unitRepository->allForTenant((int) $tenantId);
        $unitTypes = config('units.types', []);
        return Response::view('layout.main', [
            'content' => 'admin.units.index',
            'title' => 'Unités / Équipes / Groupes',
            'units' => $units,
            'unitTypes' => $unitTypes,
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $parents = $this->unitRepository->allForTenant((int) $tenantId);
        $users = $this->userRepository->allForTenant((int) $tenantId);
        $unitTypes = config('units.types', []);
        return Response::view('layout.main', [
            'content' => 'admin.units.create',
            'title' => 'Nouvelle unité / équipe / groupe',
            'parents' => $parents,
            'users' => $users,
            'unitTypes' => $unitTypes,
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $name = trim((string) $request->input('name'));
        $slugInput = trim((string) $request->input('slug'));
        $effectiveSlug = $slugInput !== '' ? $slugInput : $this->slugify($name);
        if ($effectiveSlug === '') {
            $effectiveSlug = 'unite';
        }
        $data = [
            'name' => $name,
            'slug' => $effectiveSlug,
            'type' => $request->input('type') ?: null,
            'code' => $request->input('code') ?: null,
            'parent_id' => $request->input('parent_id') ?: null,
            'commander_user_id' => $request->input('commander_user_id') ?: null,
            'display_order' => $request->input('display_order') ?: 0,
        ];
        if ($data['name'] === '') {
            Session::set('error', 'Le nom est requis.');
            return Response::redirect(url('admin/units/create'));
        }
        if ($this->unitRepository->slugExists((int) $tenantId, $effectiveSlug)) {
            Session::set('error', 'Ce slug existe déjà.');
            return Response::redirect(url('admin/units/create'));
        }
        $this->unitRepository->create((int) $tenantId, $data);
        Session::set('success', 'Unité créée.');
        return Response::redirect(url('admin/units'));
    }

    public function edit(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $unit = $this->unitRepository->findById($id, (int) $tenantId);
        if (!$unit) {
            return (new Response())->setStatusCode(404)->setBody('Unité non trouvée.');
        }
        $parents = array_filter($this->unitRepository->allForTenant((int) $tenantId), fn ($u) => (int) $u['id'] !== $id);
        $users = $this->userRepository->allForTenant((int) $tenantId);
        $unitTypes = config('units.types', []);
        return Response::view('layout.main', [
            'content' => 'admin.units.edit',
            'title' => 'Modifier l\'unité',
            'unit' => $unit,
            'parents' => $parents,
            'users' => $users,
            'unitTypes' => $unitTypes,
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $unit = $this->unitRepository->findById($id, (int) $tenantId);
        if (!$unit) {
            return (new Response())->setStatusCode(404)->setBody('Unité non trouvée.');
        }
        $data = [
            'name' => $request->input('name'),
            'slug' => $request->input('slug'),
            'type' => $request->input('type') ?: null,
            'code' => $request->input('code') ?: null,
            'parent_id' => $request->input('parent_id') ?: null,
            'commander_user_id' => $request->input('commander_user_id') ?: null,
            'display_order' => $request->input('display_order') ?: 0,
        ];
        if ($data['slug'] && $this->unitRepository->slugExists((int) $tenantId, $data['slug'], $id)) {
            Session::set('error', 'Ce slug existe déjà.');
            return Response::redirect(url('admin/units/' . $id . '/edit'));
        }
        $this->unitRepository->update($id, (int) $tenantId, $data);
        Session::set('success', 'Unité mise à jour.');
        return Response::redirect(url('admin/units'));
    }

    public function delete(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $unit = $this->unitRepository->findById($id, (int) $tenantId);
        if (!$unit) {
            return (new Response())->setStatusCode(404)->setBody('Unité non trouvée.');
        }
        $this->unitRepository->delete($id, (int) $tenantId);
        Session::set('success', 'Unité supprimée.');
        return Response::redirect(url('admin/units'));
    }

    private function slugify(string $name): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', trim($name));
        return strtolower(trim($slug, '-'));
    }
}
