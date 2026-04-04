<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;

class GroupAdminController
{
    private const TYPE = 'group';

    public function __construct(
        private UnitRepository $unitRepository,
        private UserRepository $userRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $units = $this->unitRepository->getGroups($tenantId);
        return Response::view('layout.main', [
            'content' => 'admin.organization.groups.index',
            'title' => 'Groupes',
            'groups' => $units,
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $parents = $this->unitRepository->getGroups($tenantId);
        $users = $this->userRepository->allForTenant($tenantId);
        return Response::view('layout.main', [
            'content' => 'admin.organization.groups.create',
            'title' => 'Nouveau groupe',
            'parents' => $parents,
            'users' => $users,
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $name = trim((string) $request->input('name'));
        $slugInput = trim((string) $request->input('slug'));
        $effectiveSlug = $slugInput !== '' ? $slugInput : $this->slugify($name);
        if ($effectiveSlug === '') {
            $effectiveSlug = 'groupe';
        }
        $data = [
            'name' => $name,
            'slug' => $effectiveSlug,
            'type' => self::TYPE,
            'code' => $request->input('code') ?: null,
            'parent_id' => $request->input('parent_id') ?: null,
            'commander_user_id' => $request->input('commander_user_id') ?: null,
            'display_order' => (int) ($request->input('display_order') ?? 0),
        ];
        if ($data['name'] === '') {
            Session::flash('error', 'Le nom est requis.');
            return Response::redirect(url('admin/organization/groups/create'));
        }
        if ($this->unitRepository->slugExists($tenantId, $effectiveSlug)) {
            Session::flash('error', 'Ce slug existe déjà.');
            return Response::redirect(url('admin/organization/groups/create'));
        }
        $unit = $this->unitRepository->create($tenantId, $data);
        Session::flash('success', 'Groupe créé.');
        $newId = isset($unit['id']) ? (int) $unit['id'] : 0;
        return Response::redirect($newId ? url('admin/organization/groups/' . $newId) : url('admin/organization/groups'));
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('admin/organization/groups'));
        }
        $unit = $this->unitRepository->findById($id, $tenantId);
        if (!$unit || ($unit['type'] ?? '') !== self::TYPE) {
            Session::flash('error', 'Groupe introuvable.');
            return Response::redirect(url('admin/organization/groups'));
        }
        $memberIds = $this->userRepository->getIdsByUnit($id);
        $allUsers = $this->userRepository->allForTenant($tenantId);
        $members = array_filter($allUsers, fn ($u) => in_array((int) $u['id'], $memberIds, true));
        $commanderId = isset($unit['commander_user_id']) ? (int) $unit['commander_user_id'] : null;
        $commander = $commanderId ? (array_values(array_filter($allUsers, fn ($u) => (int) $u['id'] === $commanderId))[0] ?? null) : null;
        return Response::view('layout.main', [
            'content' => 'admin.organization.groups.show',
            'title' => $unit['name'],
            'group' => $unit,
            'members' => $members,
            'commander' => $commander,
        ]);
    }

    public function edit(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('admin/organization/groups'));
        }
        $unit = $this->unitRepository->findById($id, $tenantId);
        if (!$unit || ($unit['type'] ?? '') !== self::TYPE) {
            Session::flash('error', 'Groupe introuvable.');
            return Response::redirect(url('admin/organization/groups'));
        }
        $parents = array_filter($this->unitRepository->getGroups($tenantId), fn ($u) => (int) $u['id'] !== $id);
        $users = $this->userRepository->allForTenant($tenantId);
        return Response::view('layout.main', [
            'content' => 'admin.organization.groups.edit',
            'title' => 'Modifier le groupe',
            'group' => $unit,
            'parents' => $parents,
            'users' => $users,
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('admin/organization/groups'));
        }
        $unit = $this->unitRepository->findById($id, $tenantId);
        if (!$unit || ($unit['type'] ?? '') !== self::TYPE) {
            Session::flash('error', 'Groupe introuvable.');
            return Response::redirect(url('admin/organization/groups'));
        }
        $slug = trim((string) $request->input('slug')) ?: $this->slugify(trim((string) $request->input('name')));
        if ($slug && $this->unitRepository->slugExists($tenantId, $slug, $id)) {
            Session::flash('error', 'Ce slug existe déjà.');
            return Response::redirect(url('admin/organization/groups/' . $id . '/edit'));
        }
        $this->unitRepository->update($id, $tenantId, [
            'name' => $request->input('name'),
            'slug' => $slug ?: $unit['slug'],
            'type' => self::TYPE,
            'code' => $request->input('code') ?: null,
            'parent_id' => $request->input('parent_id') ?: null,
            'commander_user_id' => $request->input('commander_user_id') ?: null,
            'display_order' => (int) ($request->input('display_order') ?? 0),
        ]);
        Session::flash('success', 'Groupe mis à jour.');
        return Response::redirect(url('admin/organization/groups/' . $id));
    }

    public function delete(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('admin/organization/groups'));
        }
        $unit = $this->unitRepository->findById($id, $tenantId);
        if (!$unit || ($unit['type'] ?? '') !== self::TYPE) {
            Session::flash('error', 'Groupe introuvable.');
            return Response::redirect(url('admin/organization/groups'));
        }
        $this->unitRepository->delete($id, $tenantId);
        Session::flash('success', 'Groupe supprimé.');
        return Response::redirect(url('admin/organization/groups'));
    }

    private function slugify(string $name): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', trim($name));
        return strtolower(trim($slug, '-') ?: 'groupe');
    }
}
