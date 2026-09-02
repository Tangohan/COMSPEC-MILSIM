<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Community\TenantRecoveryService;
use App\Services\Community\TenantTypeConfig;

final class SystemTenantRecoveryController
{
    private const MAX_DUMP_BYTES = 8_388_608;

    public function __construct(
        private ?TenantRecoveryService $recovery = null,
        private ?AuditService $audit = null,
    ) {
        $this->recovery ??= new TenantRecoveryService();
        $this->audit ??= new AuditService();
    }

    public function index(Request $request, array $params = []): Response
    {
        $orphanIds = $this->recovery->listOrphanedTenantIds();
        $selectedId = (int) $request->query('tenant_id', $orphanIds[0] ?? 0);
        if ($selectedId < 2 && $orphanIds !== []) {
            $selectedId = (int) $orphanIds[0];
        }

        $draft = Session::getFlash('tenant_recovery_draft');
        if (!is_array($draft)) {
            $draft = [];
        }

        $inspect = $selectedId > 1 ? $this->recovery->inspect($selectedId) : null;
        $form = $this->buildFormDefaults($selectedId, $inspect, $draft);

        $orphanReports = [];
        foreach ($orphanIds as $orphanId) {
            $orphanReports[] = $this->recovery->inspect((int) $orphanId);
        }

        $typeOptions = [];
        foreach (TenantTypeConfig::availableTypes() as $slug => $meta) {
            $typeOptions[$slug] = (string) ($meta['label'] ?? $slug);
        }

        return Response::view('layout.main', [
            'title' => 'Récupération de communauté',
            'content' => 'admin.system.tenant_recovery',
            'orphanIds' => $orphanIds,
            'orphanReports' => $orphanReports,
            'selectedTenantId' => $selectedId,
            'inspect' => $inspect,
            'form' => $form,
            'tenantTypes' => $typeOptions,
            'backOfficePageCss' => ['platform-admin.css'],
        ]);
    }

    public function parseDump(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/tenant-recovery'));
        }

        $tenantId = (int) $request->input('tenant_id');
        $redirect = url('admin/system/tenant-recovery') . ($tenantId > 1 ? '?tenant_id=' . $tenantId : '');

        $content = trim((string) $request->input('sql_paste', ''));
        if ($content === '' && isset($_FILES['sql_file']) && is_array($_FILES['sql_file'])) {
            $file = $_FILES['sql_file'];
            $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_OK) {
                $size = (int) ($file['size'] ?? 0);
                if ($size > self::MAX_DUMP_BYTES) {
                    Session::flash('error', 'Fichier trop volumineux (max 8 Mo).');

                    return Response::redirect($redirect);
                }
                $tmp = (string) ($file['tmp_name'] ?? '');
                if ($tmp !== '' && is_uploaded_file($tmp)) {
                    $raw = file_get_contents($tmp);
                    $content = is_string($raw) ? $raw : '';
                }
            }
        }

        if ($tenantId < 2) {
            Session::flash('error', 'Choisissez d’abord l’identifiant de communauté à récupérer.');

            return Response::redirect($redirect);
        }
        if (trim($content) === '') {
            Session::flash('error', 'Collez un extrait SQL ou téléversez un dump .sql.');

            return Response::redirect($redirect);
        }

        $parsed = $this->recovery->parseTenantRowFromSqlDump($content, $tenantId);
        if ($parsed === null) {
            Session::flash(
                'error',
                'Impossible de trouver une ligne tenants pour l’identifiant #' . $tenantId . ' dans le fichier.'
            );

            return Response::redirect($redirect);
        }

        Session::flash('tenant_recovery_draft', $parsed);
        Session::flash(
            'success',
            'Ligne extraite du dump : vérifiez les champs puis confirmez la restauration.'
        );

        return Response::redirect($redirect);
    }

    public function restore(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/tenant-recovery'));
        }

        $tenantId = (int) $request->input('tenant_id');
        $redirect = url('admin/system/tenant-recovery') . ($tenantId > 1 ? '?tenant_id=' . $tenantId : '');

        $confirmId = (int) $request->input('confirm_tenant_id');
        if ($tenantId < 2 || $confirmId !== $tenantId) {
            Session::flash('error', 'Confirmez l’identifiant en le retapant exactement.');

            return Response::redirect($redirect);
        }
        if ((string) $request->input('confirm_understand') !== '1') {
            Session::flash('error', 'Cochez la case de confirmation avant de continuer.');

            return Response::redirect($redirect);
        }

        $input = [
            'id' => $tenantId,
            'name' => $request->input('name'),
            'slug' => $request->input('slug'),
            'tenant_type' => $request->input('tenant_type', 'full'),
            'logo_url' => $request->input('logo_url'),
            'settings' => $request->input('settings'),
            'owner_user_id' => $request->input('owner_user_id'),
            'plan_slug' => $request->input('plan_slug', 'free'),
            'community_code' => $request->input('community_code'),
            'subscription_status' => $request->input('subscription_status', 'none'),
        ];

        $validation = $this->recovery->validateRestore($input);
        if (!$validation['ok']) {
            Session::flash('error', implode(' ', $validation['errors']));

            return Response::redirect($redirect);
        }

        $result = $this->recovery->restore($validation['normalized']);
        if (!$result['ok']) {
            Session::flash('error', implode(' ', $result['errors']));

            return Response::redirect($redirect);
        }

        $actorId = (int) Session::get('user_id');
        $normalized = $validation['normalized'];
        $this->audit->logChange(
            AuditAction::TENANT_CREATED,
            $tenantId,
            $actorId,
            'tenant',
            $tenantId,
            null,
            [
                'recovery' => true,
                'name' => (string) ($normalized['name'] ?? ''),
                'slug' => (string) ($normalized['slug'] ?? ''),
                'restored_orphan_data' => true,
            ],
        );

        $message = 'Communauté #' . $tenantId . ' recréée. Rechargez l’annuaire et basculez dessus pour vérifier.';
        if ($validation['warnings'] !== []) {
            $message .= ' Avertissements : ' . implode(' ', $validation['warnings']);
        }
        Session::flash('success', $message);

        return Response::redirect(url('admin/tenants'));
    }

    /**
     * @param array<string, mixed>|null $inspect
     * @param array<string, mixed> $draft
     * @return array<string, mixed>
     */
    private function buildFormDefaults(int $tenantId, ?array $inspect, array $draft): array
    {
        $hints = is_array($inspect['identity_hints'] ?? null) ? $inspect['identity_hints'] : [];
        $auditIdentity = is_array($hints['audit_identity'] ?? null) ? $hints['audit_identity'] : [];
        $auditCreated = is_array($hints['audit_created'] ?? null) ? $hints['audit_created'] : [];

        $defaults = [
            'id' => $tenantId,
            'name' => '',
            'slug' => '',
            'tenant_type' => 'full',
            'logo_url' => '',
            'settings' => '',
            'owner_user_id' => '',
            'plan_slug' => 'free',
            'community_code' => '',
            'subscription_status' => 'none',
        ];

        foreach ([$auditIdentity, $auditCreated, $draft] as $source) {
            foreach (['name', 'slug', 'tenant_type', 'logo_url', 'settings', 'owner_user_id', 'plan_slug', 'community_code', 'subscription_status'] as $key) {
                if (!isset($source[$key])) {
                    continue;
                }
                $value = $source[$key];
                if ($value === null || $value === '') {
                    continue;
                }
                if ($key === 'settings' && is_array($value)) {
                    $defaults[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                } else {
                    $defaults[$key] = (string) $value;
                }
            }
        }

        return $defaults;
    }
}
