<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Authorization\DashboardPinsAccess;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ArsenalWardrobeRepository;
use App\Repositories\TenantDashboardWardrobePinRepository;
use App\Services\Dashboard\DashboardWardrobeShowcaseService;
use App\Support\EquipmentCoverStorage;

final class DashboardWardrobePinsAdminController
{
    public function __construct(
        private TenantDashboardWardrobePinRepository $pins,
        private ArsenalWardrobeRepository $wardrobes,
        private DashboardWardrobeShowcaseService $showcase,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $deny = $this->denyUnlessManage();
        if ($deny !== null) {
            return $deny;
        }
        $tenantId = (int) Session::get('tenant_id');
        $rows = $this->pins->listOrderedForTenant($tenantId);
        $cards = [];
        foreach ($rows as $row) {
            $card = $this->showcase->resolveCard($row);
            if ($card !== null) {
                $cards[] = $card;
            }
        }

        return Response::view('layout.main', [
            'title' => 'Tenues du tableau de bord',
            'content' => 'admin.organization.dashboard_wardrobe_pins',
            'kitPins' => $cards,
            'maxPins' => TenantDashboardWardrobePinRepository::MAX_PINS,
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        return $this->formView(null);
    }

    public function store(Request $request, array $params = []): Response
    {
        $deny = $this->denyUnlessManage();
        if ($deny !== null) {
            return $deny;
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/dashboard-tenues'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($this->pins->countForTenant($tenantId) >= TenantDashboardWardrobePinRepository::MAX_PINS) {
            Session::flash('error', 'Nombre maximum de tenues en vitrine atteint (' . TenantDashboardWardrobePinRepository::MAX_PINS . ').');

            return Response::redirect(url('back-office/dashboard-tenues'));
        }
        $payload = $this->buildPayload($request, $tenantId, $userId, null);
        if (is_string($payload)) {
            Session::flash('error', $payload);

            return Response::redirect(url('back-office/dashboard-tenues/create'));
        }
        $this->pins->create($tenantId, $payload);
        Session::flash('success', 'Tenue ajoutée au tableau de bord.');

        return Response::redirect(url('back-office/dashboard-tenues'));
    }

    public function edit(Request $request, array $params = []): Response
    {
        $deny = $this->denyUnlessManage();
        if ($deny !== null) {
            return $deny;
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $pin = $this->pins->findById($id, $tenantId);
        if ($pin === null) {
            Session::flash('error', 'Cette tenue n’est plus en vitrine.');

            return Response::redirect(url('back-office/dashboard-tenues'));
        }

        return $this->formView($pin);
    }

    public function update(Request $request, array $params = []): Response
    {
        $deny = $this->denyUnlessManage();
        if ($deny !== null) {
            return $deny;
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/dashboard-tenues'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->pins->findById($id, $tenantId);
        if ($existing === null) {
            Session::flash('error', 'Cette tenue n’est plus en vitrine.');

            return Response::redirect(url('back-office/dashboard-tenues'));
        }
        $payload = $this->buildPayload($request, $tenantId, $userId, $existing);
        if (is_string($payload)) {
            Session::flash('error', $payload);

            return Response::redirect(url('back-office/dashboard-tenues/' . $id . '/edit'));
        }
        $this->pins->update($id, $tenantId, $payload);
        Session::flash('success', 'Vitrine mise à jour.');

        return Response::redirect(url('back-office/dashboard-tenues'));
    }

    public function delete(Request $request, array $params = []): Response
    {
        $deny = $this->denyUnlessManage();
        if ($deny !== null) {
            return $deny;
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/dashboard-tenues'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->pins->findById($id, $tenantId);
        if ($existing !== null) {
            EquipmentCoverStorage::delete(isset($existing['figure_path']) ? (string) $existing['figure_path'] : null);
            EquipmentCoverStorage::delete(isset($existing['backdrop_path']) ? (string) $existing['backdrop_path'] : null);
        }
        if ($this->pins->delete($id, $tenantId)) {
            Session::flash('success', 'Tenue retirée du tableau de bord.');
        } else {
            Session::flash('error', 'Retrait impossible.');
        }

        return Response::redirect(url('back-office/dashboard-tenues'));
    }

    public function move(Request $request, array $params = []): Response
    {
        $deny = $this->denyUnlessManage();
        if ($deny !== null) {
            return $deny;
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/dashboard-tenues'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $dir = trim((string) $request->input('direction'));
        $rows = $this->pins->listOrderedForTenant($tenantId);
        $orderedIds = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows);
        $idx = array_search($id, $orderedIds, true);
        if ($idx === false) {
            return Response::redirect(url('back-office/dashboard-tenues'));
        }
        if ($dir === 'up' && $idx > 0) {
            $tmp = $orderedIds[$idx - 1];
            $orderedIds[$idx - 1] = $orderedIds[$idx];
            $orderedIds[$idx] = $tmp;
        } elseif ($dir === 'down' && $idx < count($orderedIds) - 1) {
            $tmp = $orderedIds[$idx + 1];
            $orderedIds[$idx + 1] = $orderedIds[$idx];
            $orderedIds[$idx] = $tmp;
        }
        $this->pins->reorder($tenantId, $orderedIds);

        return Response::redirect(url('back-office/dashboard-tenues'));
    }

    private function denyUnlessManage(): ?Response
    {
        if (!Session::get('user_id')) {
            return Response::redirect(url('login'));
        }
        if (!DashboardPinsAccess::canManage()) {
            Session::flash('error', 'Vous n’avez pas la permission de choisir les tenues du tableau de bord.');

            return Response::redirect(url('dashboard'));
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $pin
     */
    private function formView(?array $pin): Response
    {
        $deny = $this->denyUnlessManage();
        if ($deny !== null) {
            return $deny;
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $wardrobes = [];
        if ($this->wardrobes->tablesReady()) {
            $wardrobes = $this->wardrobes->listAccessibleWardrobes($tenantId, $userId);
        }
        $isEdit = $pin !== null;
        $id = $isEdit ? (int) ($pin['id'] ?? 0) : 0;

        return Response::view('layout.main', [
            'title' => $isEdit ? 'Modifier une tenue en vitrine' : 'Mettre une tenue en avant',
            'content' => 'admin.organization.dashboard_wardrobe_pins_form',
            'pin' => $pin,
            'wardrobes' => $wardrobes,
            'formAction' => $isEdit
                ? url('back-office/dashboard-tenues/' . $id . '/update')
                : url('back-office/dashboard-tenues/store'),
        ]);
    }

    /**
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>|string
     */
    private function buildPayload(Request $request, int $tenantId, int $userId, ?array $existing): array|string
    {
        $wardrobeId = (int) $request->input('wardrobe_id');
        $wardrobe = $wardrobeId > 0 ? $this->wardrobes->findWardrobe($tenantId, $wardrobeId) : null;
        if ($wardrobe === null) {
            return 'Choisissez une tenue de la communauté.';
        }
        $dup = $this->pins->findByWardrobe($tenantId, $wardrobeId);
        if ($dup !== null && (int) ($dup['id'] ?? 0) !== (int) ($existing['id'] ?? 0)) {
            return 'Cette tenue est déjà mise en avant.';
        }
        $title = trim((string) $request->input('title'));
        if (strlen($title) > 200) {
            return 'Titre trop long.';
        }
        $badge = trim((string) $request->input('badge_label'));
        if (strlen($badge) > 80) {
            return 'Pastille trop longue.';
        }
        $figurePath = isset($existing['figure_path']) ? (string) $existing['figure_path'] : null;
        $backdropPath = isset($existing['backdrop_path']) ? (string) $existing['backdrop_path'] : null;
        if ((string) $request->input('remove_figure') === '1') {
            EquipmentCoverStorage::delete($figurePath);
            $figurePath = null;
        }
        if ((string) $request->input('remove_backdrop') === '1') {
            EquipmentCoverStorage::delete($backdropPath);
            $backdropPath = null;
        }
        $figure = EquipmentCoverStorage::storeFigureFromUpload($tenantId, is_array($_FILES['figure'] ?? null) ? $_FILES['figure'] : []);
        if ($figure['error'] !== null) {
            return $figure['error'];
        }
        if ($figure['path'] !== null) {
            EquipmentCoverStorage::delete($figurePath);
            $figurePath = $figure['path'];
        }
        $backdrop = EquipmentCoverStorage::storeFromUpload($tenantId, 'backdrop', is_array($_FILES['backdrop'] ?? null) ? $_FILES['backdrop'] : []);
        if ($backdrop['error'] !== null) {
            return $backdrop['error'];
        }
        if ($backdrop['path'] !== null) {
            EquipmentCoverStorage::delete($backdropPath);
            $backdropPath = $backdrop['path'];
        }

        return [
            'wardrobe_id' => $wardrobeId,
            'title' => $title !== '' ? $title : null,
            'badge_label' => $badge !== '' ? $badge : null,
            'figure_path' => $figurePath !== '' ? $figurePath : null,
            'backdrop_path' => $backdropPath !== '' ? $backdropPath : null,
            'created_by' => $userId > 0 ? $userId : null,
        ];
    }
}
