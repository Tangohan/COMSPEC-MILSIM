<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ModpackRepository;
use App\Services\Auth\AuthService;
use App\Services\Dashboard\TenantDashboardPinService;

/**
 * Espace léger mobile : liens opérationnels sans navigation d’administration.
 */
final class OperateurTerrainController
{
    public function __construct(
        private AuthService $authService,
        private ModpackRepository $modpackRepository,
        private TenantDashboardPinService $pinService,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 2) {
            return Response::redirect(url('dashboard'));
        }
        $uid = (int) $user['id'];
        $modpack = $this->modpackRepository->getPrimaryForTenant($tenantId);
        $atakUrl = null;
        $modPath = dirname(__DIR__, 2) . '/../storage/atak-mod/' . $tenantId . '/comspec-overwatch.zip';
        if (is_file($modPath) && is_readable($modPath)) {
            $atakUrl = url('atak/mod/download');
        }
        $pins = [];
        try {
            $pins = $this->pinService->listResolvedPinsForViewer($tenantId, $uid);
        } catch (\Throwable) {
            $pins = [];
        }
        $pinLinks = array_values(array_filter($pins, static fn (array $p): bool => in_array($p['kind'] ?? '', ['external_url', 'document', 'document_category', 'courrier_document'], true)));

        return Response::view('layout.terrain_operateur', [
            'title' => 'Terrain',
            'content' => 'operateur/terrain',
            'terrain_modpack' => $modpack,
            'terrain_atak_url' => $atakUrl,
            'terrain_pin_links' => array_slice($pinLinks, 0, 8),
        ]);
    }
}
