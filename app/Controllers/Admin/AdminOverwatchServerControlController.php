<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakMapRepository;
use App\Repositories\AtakRelayRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Services\Tactical\AtakBridgeModulesService;
use App\Services\Tactical\AtakExperienceService;
use App\Support\ModuleFeatureAccess;

/**
 * Poste de contrôle serveur : réalisme, fonctions actives, relais.
 */
final class AdminOverwatchServerControlController
{
    public function __construct(
        private ?TenantAtakConfigRepository $atakConfig = null,
        private ?AtakRelayRepository $relays = null,
        private ?AtakMapRepository $maps = null,
    ) {
        $this->atakConfig ??= new TenantAtakConfigRepository();
        $this->relays ??= new AtakRelayRepository();
        $this->maps ??= new AtakMapRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage', 'back-office/atak');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $exp = new AtakExperienceService();
        $modules = new AtakBridgeModulesService();
        $roleplay = $this->atakConfig->getRoleplayConfig($tenantId);
        $config = $this->atakConfig->getByTenantId($tenantId) ?? [];
        $maintenance = (int) ($config['maintenance_enabled'] ?? 0) === 1;
        $mapLabels = [];
        foreach ($this->maps->getAll() as $map) {
            $id = (int) ($map['id'] ?? 0);
            if ($id > 0) {
                $mapLabels[$id] = (string) ($map['label'] ?? $map['slug'] ?? ('Carte ' . $id));
            }
        }
        $relayRows = [];
        foreach ($this->relays->listForTenant($tenantId) as $row) {
            $mapId = (int) ($row['map_id'] ?? 0);
            $row['map_label'] = $mapLabels[$mapId] ?? ('Carte ' . $mapId);
            $relayRows[] = $row;
        }

        $groupedRules = [];
        foreach ($exp->catalogWithState($tenantId) as $row) {
            $group = (string) ($row['group'] ?? 'fonctions');
            $groupedRules[$group][] = $row;
        }

        $enabledModules = 0;
        $moduleRows = $modules->catalogWithState($tenantId);
        foreach ($moduleRows as $mod) {
            if (!empty($mod['enabled'])) {
                $enabledModules++;
            }
        }

        return Response::view('layout.main', [
            'content' => 'admin.atak.server_control',
            'title' => 'Contrôle serveur Overwatch',
            'pageTitle' => 'Contrôle serveur Overwatch',
            'csrfToken' => Csrf::token(),
            'experienceSchemaReady' => $this->atakConfig->isExperienceSchemaReady(),
            'groupedRules' => $groupedRules,
            'groupLabels' => $exp->groupLabels(),
            'bridgeModules' => $moduleRows,
            'enabledModulesCount' => $enabledModules,
            'bridgeModulesTotal' => count($moduleRows),
            'roleplay' => $roleplay,
            'relays' => $relayRows,
            'maintenanceEnabled' => $maintenance,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]);
    }

    public function storeRules(Request $request, array $params = []): Response
    {
        $redirect = $this->guardPost($request);
        if ($redirect instanceof Response) {
            return $redirect;
        }
        $tenantId = $this->tenantId();
        if (!$this->atakConfig->isExperienceSchemaReady()) {
            Session::flash('error', 'Impossible d’enregistrer : la base n’est pas à jour. Contactez le support, puis réessayez.');

            return Response::redirect(url('back-office/atak/controle-serveur'));
        }

        $svc = new AtakExperienceService();
        $incoming = ['server_control_reviewed' => true];
        foreach ($svc->catalog() as $row) {
            $id = $row['id'];
            if ($row['type'] === 'bool') {
                $incoming[$id] = (string) $request->input('experience_' . $id, '0') === '1';
            } else {
                $incoming[$id] = trim((string) $request->input('experience_' . $id, (string) $row['default']));
            }
        }
        if (!empty($incoming['realism'])) {
            $incoming['troll'] = false;
        }
        $svc->put($tenantId, $incoming);
        $this->markControlReviewed($tenantId);
        Session::flash('success', 'Règles de mission enregistrées. Les opérateurs en liaison les reçoivent sous environ une minute.');

        return Response::redirect(url('back-office/atak/controle-serveur') . '#regles');
    }

    public function storeModules(Request $request, array $params = []): Response
    {
        $redirect = $this->guardPost($request);
        if ($redirect instanceof Response) {
            return $redirect;
        }
        $svc = new AtakBridgeModulesService();
        $boolMap = [];
        foreach ($svc->catalog() as $row) {
            $id = $row['id'];
            $boolMap[$id] = (string) $request->input('module_' . $id, '0') === '1';
        }
        $svc->put($this->tenantId(), $boolMap);
        $this->markControlReviewed($this->tenantId());
        Session::flash('success', 'Fonctions actives enregistrées. Les opérateurs en liaison les reçoivent sous environ une minute.');

        return Response::redirect(url('back-office/atak/controle-serveur') . '#fonctions');
    }

    /**
     * @deprecated Cette méthode est obsolète. Le paramètre link_via_relays est maintenant
     * géré exclusivement dans back-office/atak/roleplay pour éviter la duplication (incohérence #5).
     * Cette route sera supprimée en Phase 4 du plan de centralisation.
     */
    public function storeRelays(Request $request, array $params = []): Response
    {
        $redirect = $this->guardPost($request);
        if ($redirect instanceof Response) {
            return $redirect;
        }
        // Redirection vers la page roleplay où ce paramètre est maintenant géré
        Session::flash('error', 'Ce paramètre est maintenant géré dans la page "Simulation réseau et capteurs".');
        return Response::redirect(url('back-office/atak/roleplay'));
    }

    private function tenantId(): int
    {
        return (int) (Session::get('tenant_id') ?? 0);
    }

    private function guardPost(Request $request): ?Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if ($request->method() !== 'POST' || !Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/atak/controle-serveur'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage', 'back-office/atak');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        return null;
    }

    private function markControlReviewed(int $tenantId): void
    {
        try {
            \App\Core\Container::get(\App\Services\ConfigurationUpdate\ConfigurationUpdateService::class)
                ->markCompleted($tenantId, 'OVERWATCH_SERVER_CONTROL_V1', (int) (Session::get('user_id') ?? 0) ?: null);
        } catch (\Throwable) {
        }
    }
}
