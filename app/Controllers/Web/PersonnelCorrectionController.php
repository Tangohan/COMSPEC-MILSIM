<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelCorrectionRequestRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Personnel\PersonnelCorrectionRequestService;

final class PersonnelCorrectionController
{
    /** Demandes en attente affichées d'un coup (les plus anciennes d'abord). */
    private const QUEUE_LIMIT = 60;

    private const HISTORY_LIMIT = 100;

    public function __construct(
        private AuthService $authService,
        private UserRepository $userRepository,
        private PersonnelCorrectionRequestService $correctionService,
        private PersonnelCorrectionRequestRepository $correctionRepository,
    ) {
    }

    /** GET /personnel/{id}/correction — formulaire anomalie / correction RH */
    public function form(Request $request, array $params = []): Response
    {
        $ctx = $this->authContext();
        if ($ctx === null) {
            return Response::redirect(url('login'));
        }
        [$tenantId, $viewer] = $ctx;
        $targetId = (int) ($params['id'] ?? 0);
        $target = $this->userRepository->findById($targetId, $tenantId);
        if (!$target) {
            Session::flash('error', 'Fiche introuvable.');

            return Response::redirect(url('personnel'));
        }
        $viewerId = (int) $viewer['id'];
        $isSelf = $viewerId === $targetId;
        if (!$isSelf && !$this->canStaffManage()) {
            Session::flash('error', 'Vous ne pouvez proposer une correction que sur votre propre fiche.');

            return Response::redirect(url('personnel/' . $targetId));
        }

        $snapshot = $this->correctionService->currentSnapshot($targetId);
        $pending = $this->correctionRepository->listForTarget($tenantId, $targetId, 15);
        foreach ($pending as &$row) {
            if (!is_array($row)) {
                continue;
            }
            $proposed = is_array($row['proposed'] ?? null) ? $row['proposed'] : [];
            $before = is_array($row['before'] ?? null) ? $row['before'] : [];
            $row['diff_lines'] = $this->correctionService->diffLinesForDisplay($proposed, $before, $tenantId);
            $reqName = trim((string) ($row['requester_display_name'] ?? ''));
            $reqCs = trim((string) ($row['requester_callsign'] ?? ''));
            $row['requester_label'] = $reqName !== '' ? $reqName : ($reqCs !== '' ? $reqCs : '');
            $resName = trim((string) ($row['resolver_display_name'] ?? ''));
            $resCs = trim((string) ($row['resolver_callsign'] ?? ''));
            $row['resolver_label'] = $resName !== '' ? $resName : ($resCs !== '' ? $resCs : '');
        }
        unset($row);
        $hasOpen = $this->correctionRepository->hasPendingForTarget($tenantId, $targetId);
        $staff = $this->canStaffManage();

        return Response::view('layout.main', [
            'title' => $staff ? 'Dossier — Corrections RH' : 'Correction RH — anomalie',
            'content' => 'personnel.correction_form',
            'targetUser' => $target,
            'snapshot' => $snapshot,
            'personnelProfile' => (new PersonnelProfileRepository())->getByUserId($targetId) ?? [],
            'fieldLabels' => PersonnelCorrectionRequestService::fieldLabels($staff),
            'fieldCatalog' => PersonnelCorrectionRequestService::fieldCatalog($staff),
            'fieldGroups' => PersonnelCorrectionRequestService::FIELD_GROUPS,
            'choiceCatalog' => $this->correctionChoices($tenantId, $snapshot, $staff),
            'pending' => $pending,
            'hasOpen' => $hasOpen,
            'isSelf' => $isSelf,
            'canApplyImmediately' => $staff,
            'csrf' => Csrf::token(),
            'backOfficePageCss' => ['personnel-dossier.css', 'back-office-corrections.css'],
        ]);
    }

    /** POST /personnel/{id}/correction */
    public function submit(Request $request, array $params = []): Response
    {
        $ctx = $this->authContext();
        if ($ctx === null) {
            return Response::redirect(url('login'));
        }
        [$tenantId, $viewer] = $ctx;
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('personnel'));
        }
        $targetId = (int) ($params['id'] ?? 0);
        $viewerId = (int) $viewer['id'];
        $isSelf = $viewerId === $targetId;
        if (!$isSelf && !$this->canStaffManage()) {
            Session::flash('error', 'Accès refusé.');

            return Response::redirect(url('personnel/' . max(1, $targetId)));
        }

        $fields = $this->collectCorrectionFields($request, $this->canStaffManage());
        $applyNow = $this->canStaffManage() && (string) $request->input('apply_now', '') === '1';
        $result = $applyNow
            ? $this->correctionService->applyDirect(
                $tenantId,
                $viewerId,
                $targetId,
                $fields,
                (string) $request->input('note', '')
            )
            : $this->correctionService->submit(
                $tenantId,
                $viewerId,
                $targetId,
                $fields,
                (string) $request->input('note', '')
            );
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);
        if ($result['ok'] && !$applyNow && !empty($result['recipient_names'])) {
            Session::flash(
                'info',
                'Notification envoyée à : ' . implode(', ', $result['recipient_names']) . '.'
            );
        }

        return Response::redirect(url('personnel/' . $targetId));
    }

    /**
     * GET /back-office/personnel/corrections
     * Onglets (?vue=) : « a-traiter » (défaut), « historique », « corriger » (dossier corrigé sans demande, ?membre=).
     */
    public function index(Request $request, array $params = []): Response
    {
        $ctx = $this->authContext();
        if ($ctx === null) {
            return Response::redirect(url('login'));
        }
        if (!$this->canStaffManage()) {
            Session::flash('error', 'Droits insuffisants pour traiter les corrections RH.');

            return Response::redirect(url('dashboard'));
        }
        [$tenantId] = $ctx;
        $directTargetId = (int) $request->query('membre', 0);
        $vue = (string) $request->query('vue', '');
        if (!in_array($vue, ['a-traiter', 'historique', 'corriger'], true)) {
            $vue = $directTargetId > 0 ? 'corriger' : 'a-traiter';
        }

        $data = [
            'title' => 'Corrections RH',
            'content' => 'personnel.corrections_queue',
            'vue' => $vue,
            'fieldLabels' => PersonnelCorrectionRequestService::fieldLabels(true),
            'csrf' => Csrf::token(),
            'pendingCount' => $this->correctionRepository->countPendingForTenant($tenantId),
            'resolvedCount' => $this->correctionRepository->countResolvedForTenant($tenantId),
            'canBatch' => $this->canBatch(),
            'batchMax' => PersonnelCorrectionRequestService::BATCH_MAX,
            'requests' => [],
            'history' => [],
            'directMembers' => [],
            'directTarget' => null,
            'directSnapshot' => [],
            'directPending' => [],
            'directHasOpen' => false,
            'directFieldCatalog' => [],
            'directFieldGroups' => [],
            'directChoiceCatalog' => [],
            'personnelProfile' => [],
            'backOfficePageCss' => ['back-office-corrections.css', 'personnel-dossier.css'],
        ];

        if ($vue === 'a-traiter') {
            $data['requests'] = $this->presentRequests($this->correctionRepository->listOpenForTenant($tenantId, self::QUEUE_LIMIT), $tenantId, true);
        } elseif ($vue === 'historique') {
            $data['history'] = $this->presentRequests($this->correctionRepository->listResolvedForTenant($tenantId, self::HISTORY_LIMIT), $tenantId, false);
        } else {
            $members = [];
            foreach ($this->userRepository->listForTenant($tenantId, null, 'active', null, null, null, true) as $u) {
                $id = (int) ($u['id'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $members[] = ['id' => $id, 'label' => $this->memberLabel($u)];
            }
            usort($members, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

            $directTarget = $directTargetId > 0 ? $this->userRepository->findById($directTargetId, $tenantId) : null;
            $choices = PersonnelCorrectionRequestService::choiceCatalog();
            if (is_array($directTarget)) {
                $directSnapshot = $this->correctionService->currentSnapshot((int) $directTarget['id']);
                $data['directSnapshot'] = $directSnapshot;
                $data['directPending'] = $this->correctionRepository->listForTarget($tenantId, (int) $directTarget['id'], 5);
                $data['directHasOpen'] = $this->correctionRepository->hasPendingForTarget($tenantId, (int) $directTarget['id']);
                $data['personnelProfile'] = (new PersonnelProfileRepository())->getByUserId((int) $directTarget['id']) ?? [];
                $choices = $this->correctionChoices($tenantId, $directSnapshot, true, false);
                $choices['tutors'] = [];
                foreach ($members as $m) {
                    $choices['tutors'][] = ['value' => (string) $m['id'], 'label' => $m['label']];
                }
            }
            $data['directMembers'] = $members;
            $data['directTarget'] = $directTarget;
            $data['directFieldCatalog'] = PersonnelCorrectionRequestService::fieldCatalog(true);
            $data['directFieldGroups'] = PersonnelCorrectionRequestService::FIELD_GROUPS;
            $data['directChoiceCatalog'] = $choices;
        }

        return Response::view('layout.main', $data);
    }

    /**
     * Prépare l'affichage : qui demande quoi, depuis quand, valeur actuelle et valeur proposée côte à côte.
     * Pour les demandes en attente, la valeur « actuelle » est relue sur la fiche au moment de l'affichage.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function presentRequests(array $rows, int $tenantId, bool $live): array
    {
        $labels = PersonnelCorrectionRequestService::fieldLabels(true);
        $snapshots = [];
        $today = strtotime(date('Y-m-d')) ?: time();
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $targetId = (int) ($row['target_user_id'] ?? 0);
            $proposed = is_array($row['proposed'] ?? null) ? $row['proposed'] : [];
            $before = is_array($row['before'] ?? null) ? $row['before'] : [];
            $current = $before;
            if ($live && $targetId > 0) {
                $snapshots[$targetId] ??= $this->correctionService->currentSnapshot($targetId);
                $current = $snapshots[$targetId] + $before;
            }
            $changes = [];
            $same = 0;
            foreach ($proposed as $key => $value) {
                $key = (string) $key;
                $oldLines = $this->correctionService->displayFieldLines($key, $current[$key] ?? '', $tenantId);
                $newLines = $this->correctionService->displayFieldLines($key, $value, $tenantId);
                if ($oldLines === $newLines) {
                    ++$same;
                }
                $changes[] = [
                    'label' => $labels[$key] ?? 'Information du dossier',
                    'before' => $oldLines,
                    'after' => $newLines,
                    'blank' => PersonnelCorrectionRequestService::emptyFieldPhrase($key),
                ];
            }
            $createdTs = strtotime((string) ($row['created_at'] ?? ''));
            $row['target_label'] = $this->nameOf((string) ($row['target_display_name'] ?? ''), (string) ($row['target_callsign'] ?? ''), $targetId);
            $row['requester_label'] = $this->nameOf((string) ($row['requester_display_name'] ?? ''), (string) ($row['requester_callsign'] ?? ''), (int) ($row['requested_by'] ?? 0));
            $row['resolver_label'] = $this->nameOf((string) ($row['resolver_display_name'] ?? ''), (string) ($row['resolver_callsign'] ?? ''), (int) ($row['resolved_by'] ?? 0));
            $row['self_request'] = (int) ($row['requested_by'] ?? 0) === $targetId;
            $row['waiting_days'] = $createdTs !== false ? max(0, (int) floor(($today - strtotime(date('Y-m-d', $createdTs))) / 86400)) : null;
            $row['changes'] = $changes;
            $row['already_applied'] = $live && $changes !== [] && $same === count($changes);
            $out[] = $row;
        }

        return $out;
    }

    private function nameOf(string $displayName, string $callsign, int $userId): string
    {
        $displayName = trim($displayName);
        if ($displayName !== '') {
            return $displayName;
        }
        $callsign = trim($callsign);
        if ($callsign !== '') {
            return $callsign;
        }

        return $userId > 0 ? 'Membre n° ' . $userId : '';
    }

    /** POST /back-office/personnel/corrections/lot — même décision pour plusieurs demandes. */
    public function decideBatch(Request $request, array $params = []): Response
    {
        $ctx = $this->authContext();
        if ($ctx === null) {
            return Response::redirect(url('login'));
        }
        if (!$this->canBatch()) {
            Session::flash('error', 'Droits insuffisants pour traiter plusieurs demandes à la fois.');

            return Response::redirect(url('back-office/personnel/corrections'));
        }
        [$tenantId, $viewer] = $ctx;
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/personnel/corrections'));
        }
        $ids = $request->input('ids', []);
        $result = $this->correctionService->decideBatch(
            $tenantId,
            is_array($ids) ? $ids : [],
            (int) $viewer['id'],
            (string) $request->input('decision', ''),
            (string) $request->input('resolution_note', '')
        );
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url('back-office/personnel/corrections'));
    }

    /** POST /back-office/personnel/corrections/appliquer */
    public function applyDirect(Request $request, array $params = []): Response
    {
        $ctx = $this->authContext();
        if ($ctx === null) {
            return Response::redirect(url('login'));
        }
        if (!$this->canStaffManage()) {
            Session::flash('error', 'Droits insuffisants.');

            return Response::redirect(url('dashboard'));
        }
        [$tenantId, $viewer] = $ctx;
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/personnel/corrections'));
        }
        $targetId = (int) $request->input('target_user_id', 0);
        $result = $this->correctionService->applyDirect(
            $tenantId,
            (int) $viewer['id'],
            $targetId,
            $this->collectCorrectionFields($request, true),
            (string) $request->input('note', '')
        );
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);
        $suffix = $targetId > 0 ? ('?membre=' . $targetId) : '';

        return Response::redirect(url('back-office/personnel/corrections') . $suffix);
    }

    /** POST /back-office/personnel/corrections/{id}/decide */
    public function decide(Request $request, array $params = []): Response
    {
        $ctx = $this->authContext();
        if ($ctx === null) {
            return Response::redirect(url('login'));
        }
        if (!$this->canStaffManage()) {
            Session::flash('error', 'Droits insuffisants.');

            return Response::redirect(url('dashboard'));
        }
        [$tenantId, $viewer] = $ctx;
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/personnel/corrections'));
        }
        $requestId = (int) ($params['id'] ?? 0);
        $result = $this->correctionService->decide(
            $tenantId,
            $requestId,
            (int) $viewer['id'],
            (string) $request->input('decision', ''),
            (string) $request->input('resolution_note', '')
        );
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);
        // Décision prise depuis la page Effectifs « À traiter » : on y revient.
        if ((string) $request->input('return_to', '') === 'a-traiter') {
            return Response::redirect(effectifs_workspace_url('a-traiter'));
        }

        return Response::redirect(url('back-office/personnel/corrections'));
    }

    /** POST /personnel/{id}/correction/{requestId}/annuler — le membre annule sa demande. */
    public function cancelOwn(Request $request, array $params = []): Response
    {
        $ctx = $this->authContext();
        if ($ctx === null) {
            return Response::redirect(url('login'));
        }
        [$tenantId, $viewer] = $ctx;
        $targetId = (int) ($params['id'] ?? 0);
        $requestId = (int) ($params['requestId'] ?? 0);
        $redirect = $this->editOrbatRedirect($targetId, (int) $viewer['id']);
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($redirect);
        }
        $result = $this->correctionService->cancelByMember($tenantId, $requestId, (int) $viewer['id']);
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect($redirect);
    }

    private function editOrbatRedirect(int $targetId, int $viewerId): string
    {
        if ($targetId > 0 && $targetId === $viewerId) {
            return url('back-office/ma-situation/ma-fiche/modifier') . '#edit-orbat';
        }

        return url('personnel/' . max(1, $targetId) . '/edit') . '#edit-orbat';
    }

    /** @return array{0: int, 1: array<string, mixed>}|null */
    private function authContext(): ?array
    {
        $user = $this->authService->user();
        $tenantId = (int) Session::get('tenant_id');
        if (!$user || $tenantId < 1) {
            return null;
        }

        return [$tenantId, $user];
    }

    private function canStaffManage(): bool
    {
        return $this->allowsAny(PersonnelCorrectionRequestService::STAFF_PERMISSIONS);
    }

    /** Traitement par lot : réservé aux droits qui modifient les dossiers de toute la communauté. */
    private function canBatch(): bool
    {
        return $this->allowsAny(PersonnelCorrectionRequestService::BATCH_PERMISSIONS);
    }

    /** @param list<string> $slugs */
    private function allowsAny(array $slugs): bool
    {
        if ($this->authService->user() === null) {
            return false;
        }
        $gate = Gate::getInstance();
        foreach ($slugs as $slug) {
            if ($gate->allows($slug)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function collectCorrectionFields(Request $request, bool $forStaff = false): array
    {
        $fields = [];
        foreach (array_keys(PersonnelCorrectionRequestService::fieldLabels($forStaff)) as $key) {
            if ($key === 'unit_assignments') {
                if ($request->input($key) === null) {
                    continue;
                }
                $fields[$key] = $this->collectIndexedRows(
                    $request,
                    'unit_assignments',
                    'unit_primary_index'
                );
                continue;
            }
            if ($key === 'job_roles') {
                if ($request->input($key) === null) {
                    continue;
                }
                $fields[$key] = $this->collectIndexedRows(
                    $request,
                    'job_roles',
                    'job_primary_index'
                );
                continue;
            }
            if ($key === 'deployable') {
                if ($request->input($key) === null) {
                    continue;
                }
                $fields[$key] = (string) $request->input('deployable', '0');
                continue;
            }
            if ($request->input($key) !== null) {
                $fields[$key] = $request->input($key);
            }
        }

        return $fields;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectIndexedRows(Request $request, string $key, string $primaryIndexKey): array
    {
        $raw = $request->input($key, []);
        if (!is_array($raw)) {
            return [];
        }
        $primary = (int) $request->input($primaryIndexKey, 0);
        $out = [];
        foreach ($raw as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $row['is_primary'] = ((int) $index === $primary) ? 1 : 0;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, list<array{value: string, label: string}>>
     */
    private function correctionChoices(int $tenantId, array $snapshot = [], bool $fullJobCatalog = false, bool $includeTutors = true): array
    {
        $choices = PersonnelCorrectionRequestService::choiceCatalog();
        $choices['grade_id'] = $this->gradeChoices($tenantId);
        $choices['units'] = $this->unitChoices($tenantId);
        $keep = [];
        foreach (PersonnelCorrectionRequestService::decodeJobRoleRows($snapshot['job_roles'] ?? []) as $row) {
            $id = (int) ($row['role_id'] ?? $row['personnel_job_role_id'] ?? 0);
            if ($id > 0) {
                $keep[] = $id;
            }
        }
        $choices['job_roles'] = $this->jobRoleChoices($tenantId, $keep, $fullJobCatalog);
        if ($includeTutors) {
            $choices['tutors'] = $this->tutorChoices($tenantId);
        }

        return $choices;
    }

    /** @param array<string, mixed> $user */
    private function memberLabel(array $user): string
    {
        $dn = trim((string) ($user['display_name'] ?? ''));
        if ($dn !== '') {
            return $dn;
        }
        $cs = trim((string) ($user['callsign'] ?? ''));
        if ($cs !== '') {
            return $cs;
        }

        return 'Membre';
    }

    /** @return list<array{value: string, label: string}> */
    private function gradeChoices(int $tenantId): array
    {
        $out = [];
        foreach ((new GradeRepository())->listForTenant($tenantId) as $g) {
            $id = (int) ($g['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $label = trim((string) ($g['label_long'] ?? $g['label_short'] ?? $g['name'] ?? ''));
            $out[] = ['value' => (string) $id, 'label' => $label !== '' ? $label : ('Grade #' . $id)];
        }

        return $out;
    }

    /** @return list<array{value: string, label: string}> */
    private function unitChoices(int $tenantId): array
    {
        $out = [];
        foreach ((new UnitRepository())->allForTenant($tenantId) as $u) {
            $id = (int) ($u['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $label = trim((string) ($u['name'] ?? ''));
            $out[] = ['value' => (string) $id, 'label' => $label !== '' ? $label : ('Unité #' . $id)];
        }

        return $out;
    }

    /** @return list<array{value: string, label: string}> */
    private function jobRoleChoices(int $tenantId, array $keepRoleIds = [], bool $fullCatalog = false): array
    {
        $repo = new PersonnelJobRoleRepository();
        if (!$repo->tablesExist()) {
            return [];
        }
        $source = $fullCatalog
            ? $repo->listRoleOptionsForSelect($tenantId)
            : $repo->listRoleOptionsForMemberDossier($tenantId, $keepRoleIds);
        $out = [];
        foreach ($source as $opt) {
            $id = (int) ($opt['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $label = trim((string) ($opt['label'] ?? $opt['name'] ?? ''));
            $out[] = ['value' => (string) $id, 'label' => $label !== '' ? $label : ('Emploi #' . $id)];
        }

        return $out;
    }

    /** @return list<array{value: string, label: string}> */
    private function tutorChoices(int $tenantId): array
    {
        $out = [];
        foreach ($this->userRepository->listForTenant($tenantId, null, 'active', null, null, null, true) as $u) {
            $id = (int) ($u['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $out[] = ['value' => (string) $id, 'label' => $this->memberLabel($u)];
        }
        usort($out, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $out;
    }
}
