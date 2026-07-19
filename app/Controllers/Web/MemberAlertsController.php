<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Alerts\MemberAlertsPageService;

final class MemberAlertsController
{
    public function __construct(
        private MemberAlertsPageService $pageService,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }

        $payload = $this->pageService->buildForViewer($tenantId, $userId);

        return Response::view('layout.main', [
            'title' => 'Alertes & annonces',
            'content' => 'alerts.index',
            'alerts_active' => $payload['active'],
            'alerts_history' => $payload['history'],
            'alerts_manage_url' => $payload['manage_url'],
        ]);
    }
}
