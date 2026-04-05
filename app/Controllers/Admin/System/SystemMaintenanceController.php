<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\MaintenanceRepository;
use App\Support\MaintenanceGuard;
use RuntimeException;

final class SystemMaintenanceController
{
    public function __construct(
        private ?MaintenanceRepository $repo = null
    ) {
        $this->repo ??= new MaintenanceRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        if (!$this->repo->tableExists()) {
            Session::flash('error', 'Tables de maintenance absentes : exécutez la migration (app_maintenance).');

            return Response::view('layout.main', [
                'content' => 'admin.system.maintenance_index',
                'title' => 'Maintenance',
                'maintenanceRules' => [],
                'maintenanceTableMissing' => true,
            ]);
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.maintenance_index',
            'title' => 'Maintenance',
            'maintenanceRules' => $this->repo->listAll(),
            'maintenanceTableMissing' => false,
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        if (!$this->repo->tableExists()) {
            return Response::redirect(url('admin/maintenance'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.maintenance_form',
            'title' => 'Nouvelle règle de maintenance',
            'maintenanceRule' => null,
            'formAction' => url('admin/maintenance'),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/maintenance'));
        }
        if (!$this->repo->tableExists()) {
            return Response::redirect(url('admin/maintenance'));
        }

        try {
            $data = $this->normalizeFormData($request);
            $actorId = Session::get('user_id') ? (int) Session::get('user_id') : null;
            $this->repo->create($data, $actorId, MaintenanceGuard::resolveClientIp());
            Session::flash('success', 'Règle créée.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        } catch (\Throwable) {
            Session::flash('error', 'Enregistrement impossible.');
        }

        return Response::redirect(url('admin/maintenance'));
    }

    public function edit(Request $request, array $params = []): Response
    {
        if (!$this->repo->tableExists()) {
            return Response::redirect(url('admin/maintenance'));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->repo->findById($id) : null;
        if (!$row) {
            Session::flash('error', 'Règle introuvable.');

            return Response::redirect(url('admin/maintenance'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.maintenance_form',
            'title' => 'Modifier la règle #' . $id,
            'maintenanceRule' => $row,
            'formAction' => url('admin/maintenance/' . $id . '/update'),
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/maintenance'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0 || !$this->repo->findById($id)) {
            Session::flash('error', 'Règle introuvable.');

            return Response::redirect(url('admin/maintenance'));
        }

        try {
            $data = $this->normalizeFormData($request);
            $actorId = Session::get('user_id') ? (int) Session::get('user_id') : null;
            $this->repo->update($id, $data, $actorId, MaintenanceGuard::resolveClientIp());
            Session::flash('success', 'Règle mise à jour.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        } catch (\Throwable) {
            Session::flash('error', 'Mise à jour impossible.');
        }

        return Response::redirect(url('admin/maintenance'));
    }

    public function delete(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/maintenance'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return Response::redirect(url('admin/maintenance'));
        }

        try {
            $actorId = Session::get('user_id') ? (int) Session::get('user_id') : null;
            $this->repo->delete($id, $actorId, MaintenanceGuard::resolveClientIp());
            Session::flash('success', 'Règle supprimée.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        } catch (\Throwable) {
            Session::flash('error', 'Suppression impossible.');
        }

        return Response::redirect(url('admin/maintenance'));
    }

    public function toggle(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/maintenance'));
        }
        $id = (int) ($params['id'] ?? 0);
        $enabled = $request->input('enabled');
        $on = $enabled === '1' || $enabled === 1 || $enabled === true;

        if ($id <= 0) {
            return Response::redirect(url('admin/maintenance'));
        }

        try {
            $actorId = Session::get('user_id') ? (int) Session::get('user_id') : null;
            $this->repo->setEnabled($id, $on, $actorId, MaintenanceGuard::resolveClientIp());
            Session::flash('success', $on ? 'Maintenance activée.' : 'Maintenance désactivée.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        } catch (\Throwable) {
            Session::flash('error', 'Changement d\'état impossible.');
        }

        return Response::redirect(url('admin/maintenance'));
    }

    public function audit(Request $request, array $params = []): Response
    {
        if (!$this->repo->tableExists()) {
            return Response::redirect(url('admin/maintenance'));
        }
        $id = (int) ($params['id'] ?? 0);
        $rule = $id > 0 ? $this->repo->findById($id) : null;
        if (!$rule) {
            Session::flash('error', 'Règle introuvable.');

            return Response::redirect(url('admin/maintenance'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.maintenance_audit',
            'title' => 'Historique — règle #' . $id,
            'maintenanceRule' => $rule,
            'auditRows' => $this->repo->listAuditFor($id),
        ]);
    }

    /** @return array<string, mixed> */
    private function normalizeFormData(Request $request): array
    {
        $scope = trim((string) $request->input('scope', 'global'));
        if ($scope === '') {
            $scope = 'global';
        }

        $httpRaw = $request->input('http_status');
        $httpStatus = ($httpRaw === null || $httpRaw === '') ? 503 : (int) $httpRaw;
        if ($httpStatus < 100 || $httpStatus > 599) {
            $httpStatus = 503;
        }

        return [
            'scope' => $scope,
            'is_enabled' => $request->input('is_enabled') === '1' || $request->input('is_enabled') === 1,
            'title' => trim((string) $request->input('title', 'Maintenance en cours')) ?: 'Maintenance en cours',
            'message' => $this->optionalString($request->input('message')),
            'maintenance_code' => $this->optionalString($request->input('maintenance_code')),
            'starts_at' => $this->optionalDatetime($request->input('starts_at')),
            'ends_at' => $this->optionalDatetime($request->input('ends_at')),
            'allow_admin_bypass' => $request->input('allow_admin_bypass') === '1' || $request->input('allow_admin_bypass') === 1,
            'allowed_ips' => $this->optionalString($request->input('allowed_ips')),
            'allowed_roles' => $this->optionalString($request->input('allowed_roles')),
            'redirect_url' => $this->optionalString($request->input('redirect_url')),
            'http_status' => $httpStatus,
            'priority' => (int) $request->input('priority', 100),
        ];
    }

    private function optionalString(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    private function optionalDatetime(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        $s = str_replace('T', ' ', $s);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $s) === 1) {
            return $s . ':00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $s) === 1) {
            return $s;
        }

        return $s;
    }
}
