<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\MissionsPortal\MissionsPortalService;

/**
 * Portail back-office : missions, participants, état ATAK et liaisons.
 */
final class MissionsPortalController
{
    public function __construct(
        private MissionsPortalService $portal,
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        $mapId = max(0, (int) ($request->query('carte') ?? 0));
        $hub = $this->portal->hub($tenantId, $mapId);

        return Response::view('layout.main', [
            'content' => 'admin.missions_portal.index',
            'title' => 'Portail missions',
            'mpHub' => $hub,
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $mapId = max(0, (int) ($request->query('carte') ?? 0));
        $detail = $this->portal->detail($tenantId, $id, $mapId);
        if ($detail === null) {
            Session::flash('error', 'Cette mission est introuvable ou n’est plus disponible.');

            return Response::redirect(url('back-office/missions'));
        }
        $section = strtolower(trim((string) ($request->query('vue') ?? 'recapitulatif')));
        if (!in_array($section, ['recapitulatif', 'participants', 'atak', 'liaisons'], true)) {
            $section = 'recapitulatif';
        }

        return Response::view('layout.main', [
            'content' => 'admin.missions_portal.show',
            'title' => (string) ($detail['plan']['title'] ?? 'Mission'),
            'boPageTitle' => (string) ($detail['plan']['title'] ?? 'Mission'),
            'boPageSubtitle' => 'Récapitulatif, participants, communications ATAK et liaisons.',
            'mpDetail' => $detail,
            'mpSection' => $section,
        ]);
    }

    private function tenantId(): int
    {
        return (int) Session::get('tenant_id');
    }
}
