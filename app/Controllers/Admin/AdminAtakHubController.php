<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakDataRepository;
use App\Repositories\AtakMapRepository;
use App\Repositories\AtakOrderRepository;
use App\Repositories\SseCaseRepository;
use App\Repositories\SsePersonRepository;
use App\Support\ModuleFeatureAccess;

/**
 * Tableau de bord ATAK : dossiers SSE déjà identifiés, localisation téléphone.
 */
final class AdminAtakHubController
{
    public function __construct(
        private ?SseCaseRepository $cases = null,
        private ?SsePersonRepository $persons = null,
        private ?AtakDataRepository $atak = null,
        private ?AtakMapRepository $maps = null,
        private ?AtakOrderRepository $orders = null,
    ) {
        $this->cases ??= new SseCaseRepository();
        $this->persons ??= new SsePersonRepository();
        $this->atak ??= new AtakDataRepository();
        $this->maps ??= new AtakMapRepository();
        $this->orders ??= new AtakOrderRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('view');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $mapId = $this->resolveMapId($tenantId, $request);
        $canManage = ModuleFeatureAccess::allows(
            \App\Services\Rbac\RolePermissionMatrixCatalog::MODULE_ATAK,
            'manage'
        );

        return Response::view('layout.main', [
            'content' => 'admin.atak_hub.index',
            'title' => 'Poste ATAK',
            'atakHubCases' => $this->casesWithIdentities($tenantId),
            'atakHubLivePeople' => $this->livePeople($tenantId, $mapId),
            'atakHubMaps' => $this->mapChoices(),
            'atakHubMapId' => $mapId,
            'canManageAtakHub' => $canManage,
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function placePhoneGeoloc(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/atak'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }
        if (!$this->orders || !$this->orders->tablesReady()) {
            Session::flash('error', 'La localisation téléphone n’est pas encore disponible sur cette communauté.');

            return Response::redirect(url('back-office/atak'));
        }

        $mapId = max(1, (int) ($request->input('map_id') ?? $this->resolveMapId($tenantId, $request)));
        $enable = ((string) $request->input('action', 'on')) !== 'off';
        $source = (string) $request->input('source', '');
        $label = '';
        $netId = '';

        if ($source === 'person') {
            $personId = (int) $request->input('person_id', 0);
            $person = $this->persons->findById($personId, $tenantId);
            if ($person === null) {
                Session::flash('error', 'Cette identité n’est plus dans le registre.');

                return Response::redirect(url('back-office/atak'));
            }
            $label = trim((string) ($person['display_name'] ?? ''));
            $netId = trim((string) ($person['target_unit_netid'] ?? ''));
            if ($label === '') {
                $label = 'Personne identifiée';
            }
        } elseif ($source === 'unit') {
            $unitId = (int) $request->input('unit_id', 0);
            $unit = $this->atak->getUnitById($tenantId, $unitId);
            if ($unit === null) {
                Session::flash('error', 'Ce contact n’est plus sur la carte.');

                return Response::redirect(url('back-office/atak'));
            }
            $label = trim((string) ($unit['call_sign'] ?? 'Contact'));
            $extra = AtakDataRepository::decodeExtra($unit['extra'] ?? null);
            $netId = trim((string) ($extra['net_id'] ?? $extra['netId'] ?? $extra['arma_netid'] ?? ''));
            $mapId = max(1, (int) ($unit['map_id'] ?? $mapId));
        } else {
            Session::flash('error', 'Choisissez une personne à placer sous localisation.');

            return Response::redirect(url('back-office/atak'));
        }

        $issuer = trim((string) (Session::get('display_name') ?? Session::get('callsign') ?? ''));
        if ($issuer === '') {
            $issuer = 'État-major';
        }

        $row = $this->orders->upsertByExternalId($tenantId, $mapId, [
            'external_id' => ($enable ? 'PGEO-W-' : 'PGEOFF-W-') . bin2hex(random_bytes(5)),
            'parent_external_id' => '',
            'order_type' => $enable ? 'PHONE_GEOLOC' : 'PHONE_GEOLOC_OFF',
            'type_label' => $enable ? 'Géolocalisation téléphone' : 'Arrêt géolocalisation téléphone',
            'target' => $label,
            'target_type' => 'all',
            'target_ref' => $netId !== '' ? $netId : $label,
            'target_label' => $label,
            'payload' => $enable ? 'on' : 'off',
            'priority' => 'URGENT',
            'issuer' => $issuer,
            'issuer_user_id' => (int) Session::get('user_id') ?: null,
            'status' => 'PENDING',
            'source' => 'web',
            'radio_sim' => false,
        ]);

        if ($row === null) {
            Session::flash('error', 'Impossible d’envoyer la demande au théâtre.');
        } elseif ($enable) {
            Session::flash('success', 'Localisation demandée pour « ' . $label . ' ». Le contact apparaît sur la carte dès que le théâtre l’applique.');
        } else {
            Session::flash('success', 'Arrêt de la localisation demandé pour « ' . $label . ' ».');
        }

        return Response::redirect(url('back-office/atak'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function casesWithIdentities(int $tenantId): array
    {
        $all = $this->cases->listForTenant($tenantId, null, ['is_folder' => 0]);
        $ids = [];
        foreach ($all as $case) {
            $id = (int) ($case['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $counts = $this->cases->countsForCases($ids, $tenantId);
        $personIds = [];
        $kept = [];
        foreach ($all as $case) {
            $id = (int) ($case['id'] ?? 0);
            $n = (int) ($counts[$id]['persons'] ?? 0);
            if ($n < 1) {
                continue;
            }
            $status = (string) ($case['status'] ?? '');
            if (in_array($status, ['archive', 'clos'], true)) {
                continue;
            }
            $links = $this->cases->listLinkedPersonIds($id, $tenantId);
            $linked = [];
            foreach ($links as $link) {
                $pid = (int) ($link['person_id'] ?? 0);
                if ($pid > 0) {
                    $personIds[] = $pid;
                    $linked[] = $pid;
                }
            }
            $case['identity_count'] = $n;
            $case['linked_person_ids'] = $linked;
            $case['status_label'] = SseCaseRepository::statusLabel($status);
            $kept[] = $case;
            if (count($kept) >= 24) {
                break;
            }
        }
        $byId = [];
        foreach ($this->persons->listByIds($tenantId, $personIds) as $person) {
            $byId[(int) $person['id']] = $person;
        }
        foreach ($kept as &$case) {
            $identities = [];
            foreach ($case['linked_person_ids'] as $pid) {
                if (isset($byId[$pid])) {
                    $identities[] = $byId[$pid];
                }
            }
            $case['identities'] = $identities;
            unset($case['linked_person_ids']);
        }
        unset($case);

        return $kept;
    }

    /**
     * @return list<array{id:int,label:string,tracked:bool}>
     */
    private function livePeople(int $tenantId, int $mapId): array
    {
        $out = [];
        foreach ($this->atak->getUnits($tenantId, $mapId) as $unit) {
            $status = (string) ($unit['status'] ?? '');
            if (!in_array($status, ['linked', 'delayed'], true)) {
                continue;
            }
            $extra = AtakDataRepository::decodeExtra($unit['extra'] ?? null);
            if (!empty($extra['gps_beacon']) || strtolower((string) ($extra['source'] ?? '')) === 'gps') {
                continue;
            }
            $label = trim((string) ($unit['call_sign'] ?? ''));
            if ($label === '') {
                continue;
            }
            $phoneFlag = $extra['phone_geoloc'] ?? false;
            $tracked = $phoneFlag === true || $phoneFlag === 1 || $phoneFlag === '1' || $phoneFlag === 'true'
                || strtolower((string) ($extra['source'] ?? '')) === 'phone';
            $out[] = [
                'id' => (int) ($unit['id'] ?? 0),
                'label' => $label,
                'tracked' => $tracked,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{id:int,label:string}>
     */
    private function mapChoices(): array
    {
        $out = [];
        foreach ($this->maps->getAll() as $m) {
            $id = (int) ($m['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $label = trim((string) ($m['label'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($m['slug'] ?? ('Carte ' . $id)));
            }
            $out[] = ['id' => $id, 'label' => $label];
        }

        return $out;
    }

    private function resolveMapId(int $tenantId, Request $request): int
    {
        $default = $this->maps->getDefaultForTenant($tenantId);
        $defaultId = $default ? (int) ($default['id'] ?? 1) : 1;
        $mapId = max(1, (int) ($request->query('carte') ?? $request->input('map_id') ?? $defaultId));
        $known = [];
        foreach ($this->mapChoices() as $m) {
            $known[(int) $m['id']] = true;
        }
        if ($known !== [] && !isset($known[$mapId])) {
            return $defaultId;
        }

        return $mapId;
    }
}
