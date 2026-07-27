<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantAdminSettingsRepository;

final class AdminRuntimeSettingsApiController
{
    public function __construct(
        private ?TenantAdminSettingsRepository $repository = null,
    ) {
        $this->repository ??= new TenantAdminSettingsRepository();
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::json(['success' => false, 'message' => 'Connexion requise.'], 401);
        }

        return Response::json([
            'success' => true,
            'settings' => $this->repository->getForTenant($tenantId),
        ]);
    }

    public function save(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::json(['success' => false, 'message' => 'Connexion requise.'], 401);
        }
        if (!Csrf::validate((string) ($request->input('_csrf_token') ?? ''))) {
            return Response::json(['success' => false, 'message' => 'Session expirée. Rechargez la page puis réessayez.'], 419);
        }

        $payload = $this->jsonBody();
        if ($payload === [] && is_string($request->input('settings'))) {
            $decoded = json_decode((string) $request->input('settings'), true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        $this->repository->saveForTenant($tenantId, $payload);

        return Response::json([
            'success' => true,
            'message' => 'Réglages enregistrés.',
            'settings' => $this->repository->getForTenant($tenantId),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
