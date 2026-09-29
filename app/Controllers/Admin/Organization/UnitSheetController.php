<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\DocumentRepository;
use App\Repositories\OrbatBilletRepository;
use App\Repositories\UnitActivityRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UnitTypeDefinitionRepository;
use App\Repositories\UserRepository;
use App\Services\Organization\OrbatBilletService;
use App\Support\UnitAdminStatus;
use App\Support\VisibilityLevel;

/**
 * Fiche unité BO complète : identité, postes TO&E, journal, documents.
 */
final class UnitSheetController
{
    public function __construct(
        private UnitRepository $units,
        private UnitTypeDefinitionRepository $unitTypes,
        private OrbatBilletService $billets,
        private OrbatBilletRepository $billetRepo,
        private UnitActivityRepository $activities,
        private DocumentRepository $documents,
        private UserRepository $users,
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $unitId = (int) ($params['id'] ?? $request->input('id', 0));
        if ($tenantId < 1 || $unitId < 1) {
            return Response::redirect(url('back-office/organisation/structure'));
        }
        $unit = $this->units->findById($unitId, $tenantId);
        if ($unit === null) {
            Session::flash('error', 'Unité introuvable.');

            return Response::redirect(url('back-office/organisation/structure'));
        }

        $this->billets->ensureKeyPosts($tenantId, $unitId, (int) Session::get('user_id'));
        $manning = $this->billets->unitManning($tenantId, $unitId);
        $types = $this->unitTypes->listForTenant($tenantId);
        if ($types === []) {
            $this->unitTypes->ensureSystemDefaults($tenantId);
            $types = $this->unitTypes->listForTenant($tenantId);
        }
        $activityTypes = $this->activities->listTypesForTenant($tenantId);
        $timeline = $this->activities->listForUnit($tenantId, $unitId, 40);
        foreach ($timeline as &$row) {
            $row['participants'] = $this->activities->participantsForActivity($tenantId, (int) ($row['id'] ?? 0));
        }
        unset($row);

        $docs = $this->documents->listForUnit($tenantId, $unitId, null, 40);
        $commander = null;
        $cid = (int) ($unit['commander_user_id'] ?? 0);
        if ($cid > 0) {
            $commander = $this->users->findById($cid, $tenantId);
        }
        $derived = $this->billets->derivedCommand($tenantId, $unitId);
        $canEdit = Gate::getInstance()->allows('organization.orbat.manage')
            || Gate::getInstance()->allows('admin.organization');

        $unitTypeLabel = '';
        $unitTypeId = (int) ($unit['unit_type_id'] ?? 0);
        foreach ($types as $t) {
            if ((int) ($t['id'] ?? 0) === $unitTypeId) {
                $unitTypeLabel = (string) ($t['label'] ?? '');
                break;
            }
        }

        return Response::view('admin/organization/unit_sheet', [
            'title' => (string) ($unit['name'] ?? 'Unité'),
            'unit' => $unit,
            'unitTypes' => $types,
            'unitTypeLabel' => $unitTypeLabel,
            'manning' => $manning,
            'activityTypes' => $activityTypes,
            'timeline' => $timeline,
            'documents' => $docs,
            'commander' => $commander,
            'derivedCommand' => $derived,
            'adminStatusOptions' => UnitAdminStatus::options(),
            'visibilityOptions' => [
                ['id' => VisibilityLevel::NORMAL, 'label' => 'Normale'],
                ['id' => VisibilityLevel::RESTRICTED, 'label' => 'Commandement'],
                ['id' => VisibilityLevel::HIDDEN, 'label' => 'Masquée'],
            ],
            'canEdit' => $canEdit,
            'csrf' => Csrf::token(),
            'structureUrl' => url('back-office/organisation/structure'),
            'saveUrl' => url('back-office/organisation/unites/' . $unitId),
            'activityUrl' => url('back-office/organisation/unites/' . $unitId . '/activites'),
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        if (!Csrf::verify($request)) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/organisation/structure'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $unitId = (int) ($params['id'] ?? 0);
        $canEdit = Gate::getInstance()->allows('organization.orbat.manage')
            || Gate::getInstance()->allows('admin.organization');
        if (!$canEdit || $tenantId < 1 || $unitId < 1) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('back-office/organisation/structure'));
        }
        $unit = $this->units->findById($unitId, $tenantId);
        if ($unit === null) {
            Session::flash('error', 'Unité introuvable.');

            return Response::redirect(url('back-office/organisation/structure'));
        }

        $data = [];
        foreach (['name', 'code', 'motto', 'description_long', 'orbat_details', 'public_blurb'] as $k) {
            if ($request->input($k) !== null) {
                $v = trim((string) $request->input($k, ''));
                $data[$k] = $v === '' ? null : $v;
            }
        }
        if ($request->input('accent_color') !== null) {
            $data['accent_color'] = trim((string) $request->input('accent_color', ''));
        }
        if ($request->input('public_accent_color') !== null) {
            $data['public_accent_color'] = trim((string) $request->input('public_accent_color', ''));
        }
        if ($request->input('unit_type_id') !== null) {
            $tid = (int) $request->input('unit_type_id', 0);
            $data['unit_type_id'] = $tid > 0 ? $tid : null;
            if ($tid > 0) {
                $type = $this->unitTypes->findById($tid, $tenantId);
                $typeCode = is_array($type) ? (string) ($type['code'] ?? '') : '';
                $this->billets->applyUnitTypeTemplate($tenantId, $unitId, $typeCode, (int) Session::get('user_id'));
            }
        }
        if ($request->input('admin_status') !== null) {
            $data['admin_status'] = UnitAdminStatus::normalize((string) $request->input('admin_status'));
        }
        if ($request->input('visibility_level') !== null) {
            $vis = VisibilityLevel::normalize((string) $request->input('visibility_level'));
            $data['visibility_level'] = $vis;
            $data['orbat_mask_mode'] = VisibilityLevel::toOrbatMaskMode($vis);
        }

        if ($data !== []) {
            $this->units->update($unitId, $tenantId, $data);
        }
        Session::flash('success', 'Fiche unité enregistrée.');

        return Response::redirect(url('back-office/organisation/unites/' . $unitId));
    }

    public function storeActivity(Request $request, array $params = []): Response
    {
        if (!Csrf::verify($request)) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/organisation/structure'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $unitId = (int) ($params['id'] ?? 0);
        $canEdit = Gate::getInstance()->allows('organization.orbat.manage')
            || Gate::getInstance()->allows('admin.organization');
        if (!$canEdit || $tenantId < 1 || $unitId < 1) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('back-office/organisation/unites/' . max(1, $unitId)));
        }
        if ($this->units->findById($unitId, $tenantId) === null) {
            Session::flash('error', 'Unité introuvable.');

            return Response::redirect(url('back-office/organisation/structure'));
        }

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            Session::flash('error', 'Indiquez un titre pour l’activité.');

            return Response::redirect(url('back-office/organisation/unites/' . $unitId));
        }
        $typeId = (int) $request->input('activity_type_id', 0);
        $typeCode = null;
        if ($typeId > 0) {
            foreach ($this->activities->listTypesForTenant($tenantId) as $t) {
                if ((int) ($t['id'] ?? 0) === $typeId) {
                    $typeCode = (string) ($t['code'] ?? '');
                    break;
                }
            }
        }
        $participantRaw = (string) $request->input('participant_user_ids', '');
        $participantIds = [];
        foreach (preg_split('/[\s,;]+/', $participantRaw) ?: [] as $piece) {
            $uid = (int) $piece;
            if ($uid > 0) {
                $participantIds[] = $uid;
            }
        }

        $id = $this->activities->create($tenantId, [
            'unit_id' => $unitId,
            'activity_type_id' => $typeId > 0 ? $typeId : null,
            'activity_type_code' => $typeCode,
            'title' => $title,
            'occurred_on' => (string) $request->input('occurred_on', date('Y-m-d')),
            'summary' => (string) $request->input('summary', ''),
            'created_by' => (int) Session::get('user_id'),
        ], $participantIds);

        Session::flash($id > 0 ? 'success' : 'error', $id > 0
            ? 'Activité enregistrée dans le journal de l’unité.'
            : 'Enregistrement de l’activité impossible.');

        return Response::redirect(url('back-office/organisation/unites/' . $unitId . '#journal'));
    }
}
