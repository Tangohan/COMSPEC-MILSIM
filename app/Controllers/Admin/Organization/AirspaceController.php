<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Personnel\AirspaceOverviewService;
use App\Services\Personnel\PilotQualificationService;
use App\Services\Platform\FeatureGateService;
use App\Support\PilotSyllabusCatalog;

/**
 * Back-office > Espace aérien : accueil de l'aviation de la communauté (ligne de vol, cursus, pilotes, carnet de
 * vol, flotte), avec les données relevées par le mod et le carnet de vol. Mêmes droits que le cursus pilote.
 */
final class AirspaceController
{
    public const BASE = 'back-office/espace-aerien';
    public const VIEWS = ['ligne', 'cursus', 'pilotes', 'carnet', 'flotte'];

    public function __construct(
        private AirspaceOverviewService $overview,
        private FeatureGateService $features,
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        $view = (string) $request->input('vue', 'ligne');
        $view = in_array($view, self::VIEWS, true) ? $view : 'ligne';
        $logFilter = (string) $request->input('carnet', '');
        $logFilter = in_array($logFilter, ['declared', 'countersigned', 'rejected', 'verified'], true) ? $logFilter : '';
        $track = (string) $request->input('filiere', PilotSyllabusCatalog::TRACK_HELI);
        $track = in_array($track, [PilotSyllabusCatalog::TRACK_HELI, PilotSyllabusCatalog::TRACK_PLANE, 'other'], true) ? $track : PilotSyllabusCatalog::TRACK_HELI;
        $actorId = (int) Session::get('user_id');

        return Response::view('layout.main', [
            'content' => 'admin.airspace.index',
            'backOfficePageCss' => ['espace-aerien.css'],
            'title' => 'Espace aérien',
            'boPageTitle' => 'Espace aérien',
            'boSkipPageHead' => true,
            'aeroView' => $view,
            'aeroTrack' => $track,
            'aeroLogFilter' => $logFilter,
            'aero' => $this->overview->overview($tenantId, $actorId, $logFilter),
            'allowed' => $this->features->allowsWithCatalogDefault($tenantId, PilotQualificationService::FEATURE),
            'actorId' => $actorId,
        ]);
    }
}
