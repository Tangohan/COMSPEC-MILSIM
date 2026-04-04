<?php

declare(strict_types=1);

namespace App\Controllers\Courrier;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Courrier\DocumentPresetService;

class CourrierPresetController
{
    public function __construct(
        private DocumentPresetService $presetService
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        $presets = $this->presetService->listForTenant($tenantId);
        return Response::view('layout.main', [
            'title' => 'Formats (presets) — Bureau Courrier',
            'content' => 'courrier/presets/index',
            'courrier' => ['presets' => $presets],
        ]);
    }

    public function setDefault(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? $request->input('id') ?? 0);
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        $this->presetService->setAsDefault($id, $tenantId);
        Session::flash('success', 'Format par défaut mis à jour.');
        return Response::redirect(url('courrier/presets'));
    }
}
