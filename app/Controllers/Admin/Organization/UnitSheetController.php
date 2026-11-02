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
        // Types d’unité livrés par Athena : dans la langue du lecteur (personnalisés par la communauté).
        foreach ($types as &$type) {
            $type['label'] = generated_label('unit_type', (string) ($type['label'] ?? ''), (string) ($type['code'] ?? ''), $tenantId);
        }
        unset($type);
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

        // Adéquation de l’unité (chef, adjoint, effectif, sous-unités) : gestionnaires seulement.
        $unitAdequacy = null;
        if ($canEdit) {
            try {
                $unitAdequacy = \App\Services\Organization\OrgAdequacyView::forUnit(
                    (new \App\Services\Organization\OrgAdequacyService())->evaluate($tenantId),
                    $unitId
                );
            } catch (\Throwable) {
                $unitAdequacy = null;
            }
        }

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
            'unitAdequacy' => $unitAdequacy,
            'adminStatusOptions' => UnitAdminStatus::options(),
            'visibilityOptions' => [
                ['id' => VisibilityLevel::NORMAL, 'label' => 'Normale'],
                ['id' => VisibilityLevel::RESTRICTED, 'label' => 'Commandement'],
                ['id' => VisibilityLevel::HIDDEN, 'label' => 'Masquée'],
            ],
            'canEdit' => $canEdit,
            'jobRoleOptions' => $canEdit ? $this->jobRoleOptions($tenantId) : [],
            'jobRoleDecorations' => $this->jobRoleDecorations($tenantId),
            'postsUrl' => url('back-office/organisation/unites/' . $unitId . '/postes'),
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
        if ($request->input('short_label') !== null) {
            // Colonne ajoutée ici si run-migrations.php n'est pas encore passé.
            if (!$this->units->hasTableColumn('units', 'short_label')) {
                try {
                    $migrate = require base_path('bootstrap/units_short_label_migration.php');
                    ob_start();
                    $migrate(\App\Core\Database::getPdo());
                    ob_end_clean();
                } catch (\Throwable) {
                }
            }
            $short = mb_substr(trim((string) $request->input('short_label', '')), 0, 40);
            $data['short_label'] = $short === '' ? null : $short;
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

    /**
     * Postes de l’unité (tableau d’effectifs) : intitulé, effectif prévu et emploi rattaché.
     * Saisie simple depuis la fiche unité ; un poste peut aussi être ajouté.
     */
    public function savePosts(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $unitId = (int) ($params['id'] ?? 0);
        $back = url('back-office/organisation/unites/' . max(1, $unitId) . '#postes');
        if (!Csrf::verify($request)) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $canEdit = Gate::getInstance()->allows('organization.orbat.manage')
            || Gate::getInstance()->allows('admin.organization');
        if (!$canEdit || $tenantId < 1 || $unitId < 1) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect($back);
        }
        if ($this->units->findById($unitId, $tenantId) === null) {
            Session::flash('error', 'Unité introuvable.');

            return Response::redirect(url('back-office/organisation/structure'));
        }
        if (!$this->billets->schemaReady()) {
            Session::flash('error', 'Les postes ne sont pas encore disponibles : la mise à jour de la base doit être appliquée.');

            return Response::redirect($back);
        }
        $actorId = (int) Session::get('user_id');
        $jobRoleIds = array_map(static fn (array $r): int => $r['id'], $this->jobRoleOptions($tenantId));
        $jobRole = static function (mixed $raw) use ($jobRoleIds): ?int {
            $id = is_numeric($raw) ? (int) $raw : 0;

            return $id > 0 && in_array($id, $jobRoleIds, true) ? $id : null;
        };
        $slotsOf = static fn (mixed $raw): int => max(1, min(99, is_numeric($raw) ? (int) $raw : 1));

        $updated = 0;
        $posts = $request->input('posts');
        foreach (is_array($posts) ? $posts : [] as $billetId => $row) {
            $billetId = (int) $billetId;
            if ($billetId < 1 || !is_array($row)) {
                continue;
            }
            // Un poste d’une autre unité ou d’une autre communauté n’est jamais modifié.
            $billet = $this->billetRepo->findById($tenantId, $billetId);
            if ($billet === null || (int) ($billet['unit_id'] ?? 0) !== $unitId) {
                continue;
            }
            $data = ['authorized_slots' => $slotsOf($row['authorized_slots'] ?? 1)];
            $title = mb_substr(trim((string) ($row['title'] ?? '')), 0, 150);
            if ($title !== '') {
                $data['title'] = $title;
            }
            if (array_key_exists('job_role_id', $row)) {
                $data['job_role_id'] = $jobRole($row['job_role_id']);
            }
            if (!empty($this->billets->updateBillet($tenantId, $billetId, $data, $actorId)['ok'])) {
                $updated++;
            }
        }

        $created = false;
        $newTitle = mb_substr(trim((string) $request->input('new_title', '')), 0, 150);
        if ($newTitle !== '') {
            $result = $this->billets->createBillet($tenantId, [
                'unit_id' => $unitId,
                'code' => $this->uniquePostCode($tenantId, $unitId, $newTitle),
                'title' => $newTitle,
                'authorized_slots' => $slotsOf($request->input('new_slots', 1)),
                'job_role_id' => $jobRole($request->input('new_job_role_id')),
                'sort_order' => 100,
            ], $actorId);
            $created = !empty($result['ok']);
            if (!$created) {
                Session::flash('error', 'Le poste n’a pas pu être ajouté.');

                return Response::redirect($back);
            }
        }

        Session::flash('success', $created
            ? 'Poste ajouté au tableau d’effectifs de l’unité.'
            : ($updated > 0 ? 'Postes de l’unité enregistrés.' : 'Aucun changement.'));

        return Response::redirect($back);
    }

    /** @return list<array{id:int,name:string,abbreviation:?string,hidden:bool}> */
    private function jobRoleOptions(int $tenantId): array
    {
        try {
            $rows = (new \App\Repositories\PersonnelJobRoleRepository())->listRolesWithCategory($tenantId);
        } catch (\Throwable) {
            return [];
        }
        $decorations = $this->jobRoleDecorations($tenantId);
        $out = [];
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id > 0) {
                // Une fonction du site masquée reste valable pour les postes qui l'utilisent déjà (la vue ne la propose plus ailleurs).
                $out[] = ['id' => $id, 'name' => trim((string) ($r['name'] ?? '')) ?: 'Emploi',
                    'abbreviation' => $decorations[$id]['abbreviation'] ?? null, 'hidden' => !empty($decorations[$id]['hidden'])];
            }
        }

        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    private function jobRoleDecorations(int $tenantId): array
    {
        try {
            return (new \App\Services\Personnel\JobRoleEnrichmentService())->decorations($tenantId);
        } catch (\Throwable) {
            return [];
        }
    }

    /** Code interne du poste, unique dans l’unité, déduit de l’intitulé (jamais affiché à l’utilisateur). */
    private function uniquePostCode(int $tenantId, int $unitId, string $title): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
        $base = strtoupper(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', is_string($ascii) ? $ascii : ''), '-'));
        $base = substr($base !== '' ? $base : 'POSTE', 0, 40);
        $taken = [];
        foreach ($this->billetRepo->listForUnit($tenantId, $unitId, true) as $b) {
            $taken[strtoupper((string) ($b['code'] ?? ''))] = true;
        }
        $code = $base;
        for ($i = 2; isset($taken[$code]); $i++) {
            $code = substr($base, 0, 36) . '-' . $i;
        }

        return $code;
    }
}
