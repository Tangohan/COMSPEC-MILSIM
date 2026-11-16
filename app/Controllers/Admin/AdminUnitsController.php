<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Support\GuardsBackOfficeRight;

class AdminUnitsController
{
    use GuardsBackOfficeRight;

    public function __construct(
        private UnitRepository $unitRepository,
        private UserRepository $userRepository
    ) {}

    public function store(Request $request, array $params = []): Response
    {
        if (($denied = $this->guardBackOfficeRight($request)) !== null) {
            return $denied;
        }
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $name = trim((string) $request->input('name'));
        $slugInput = trim((string) $request->input('slug'));
        $effectiveSlug = $slugInput !== '' ? $slugInput : UnitRepository::slugFromName($name, '');
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
            return Response::redirect(url('back-office/units/create'));
        }
        if (($lengthError = UnitRepository::fieldLengthError($data)) !== null) {
            Session::set('error', $lengthError);
            return Response::redirect(url('back-office/units/create'));
        }
        if ($this->unitRepository->slugExists((int) $tenantId, $effectiveSlug)) {
            Session::set('error', 'Ce slug existe déjà.');
            return Response::redirect(url('back-office/units/create'));
        }
        $planBlock = \App\Services\Billing\OrbatUnitQuota::blockMessage((int) $tenantId);
        if ($planBlock !== null) {
            Session::set('error', $planBlock);
            return Response::redirect(url('back-office/units/create'));
        }
        $this->unitRepository->create((int) $tenantId, $data);
        Session::set('success', 'Unité créée.');
        return Response::redirect(url('back-office/units'));
    }

    public function update(Request $request, array $params = []): Response
    {
        if (($denied = $this->guardBackOfficeRight($request)) !== null) {
            return $denied;
        }
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
        if (($lengthError = UnitRepository::fieldLengthError($data)) !== null) {
            Session::set('error', $lengthError);
            return Response::redirect(url('back-office/units/' . $id . '/edit'));
        }
        if ($data['slug'] && $this->unitRepository->slugExists((int) $tenantId, $data['slug'], $id)) {
            Session::set('error', 'Ce slug existe déjà.');
            return Response::redirect(url('back-office/units/' . $id . '/edit'));
        }
        $this->unitRepository->update($id, (int) $tenantId, $data);
        Session::set('success', 'Unité mise à jour.');
        return Response::redirect(url('back-office/units'));
    }

    public function delete(Request $request, array $params = []): Response
    {
        if (($denied = $this->guardBackOfficeRight($request)) !== null) {
            return $denied;
        }
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
        return Response::redirect(url('back-office/units'));
    }
}
