<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CommunityEventRepository;
use App\Repositories\InterteamMissionRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PlanningEntryRepository;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;

final class OperationalBoardController
{
    public function __construct(
        private PlanningEntryRepository $planningEntries,
        private UserRepository $users,
        private CommunityEventRepository $communityEvents,
        private InterteamMissionRepository $interteamMissions,
        private TrainingCourseRepository $trainingCourses,
        private UnitRepository $units,
        private PersonnelJobRoleRepository $jobRoles,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }

        $posture = $this->planningEntries->getPosture($tenantId) ?? ['posture_level' => 'NORMAL'];
        $filters = [
            'status' => (string) ($request->query('status') ?? 'active'),
            'entry_type' => (string) ($request->query('entry_type') ?? ''),
            'operational_status' => (string) ($request->query('operational_status') ?? ''),
            'tag' => (string) ($request->query('tag') ?? ''),
            'period_start' => (string) ($request->query('period_start') ?? date('Y-m-d')),
            'period_end' => (string) ($request->query('period_end') ?? date('Y-m-d', strtotime('+14 days'))),
            'mode' => (string) ($request->query('mode') ?? 'standard'),
            'critical_only' => (int) ($request->query('critical_only') ?? 0),
        ];
        if (in_array((string) ($posture['posture_level'] ?? 'NORMAL'), ['ALERTE', 'CRISE'], true)) {
            $filters['critical_only'] = 1;
        }

        $userId = (int) (Session::get('user_id') ?? 0);
        $this->planningEntries->ensureDefaultPlanningTemplatesIfEmpty($tenantId, $userId);

        $entries = $this->planningEntries->listForBoard($tenantId, $filters);
        $panels = $this->partitionBoardPanels($entries);
        $memberOptions = $this->memberSelectOptions($tenantId);

        return Response::view('layout.main', [
            'title' => 'Tableau opérationnel',
            'content' => 'operations/board',
            'boardSchemaReady' => $this->planningEntries->isOperationalBoardSchemaReady(),
            'boardFilters' => $filters,
            'boardPanels' => $panels,
            'boardCategories' => $this->planningEntries->listCategories($tenantId),
            'boardTemplates' => $this->planningEntries->listTemplates($tenantId),
            'boardDraftCount' => $this->planningEntries->countPlanningEntriesByStatus($tenantId, 'draft'),
            'boardPosture' => $posture,
            'boardLogs' => $this->planningEntries->listRecentLogs($tenantId, 40),
            'boardTags' => $this->planningEntries->listTags($tenantId),
            'boardQualificationAlerts' => $this->planningEntries->findQualificationAlerts($tenantId),
            'boardFireConflicts' => $this->planningEntries->findFireWindowConflicts($tenantId),
            'boardMemberOptions' => $memberOptions,
            'boardToday' => date('Y-m-d'),
        ]);
    }

    /** Vue portail (lecture filtrée). */
    public function portalIndex(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        $canRestricted = $gate->allows('operational.board.edit')
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('admin.system');

        $filters = [
            'entry_type' => (string) ($request->query('entry_type') ?? ''),
            'operational_status' => (string) ($request->query('operational_status') ?? ''),
            'period_start' => (string) ($request->query('period_start') ?? date('Y-m-d')),
            'period_end' => (string) ($request->query('period_end') ?? date('Y-m-d', strtotime('+14 days'))),
        ];
        $viewerUnitIds = $this->units->unitIdsForUser($tenantId, $userId);
        $viewerJobRoleIds = $this->jobRoles->assignedJobRoleIdsForUser($tenantId, $userId);
        $entries = $this->planningEntries->listForPortal($tenantId, $filters, $userId, $canRestricted, $viewerUnitIds, $viewerJobRoleIds);
        $panels = $this->partitionBoardPanels($entries);

        return Response::view('layout.main', [
            'title' => 'Mur opérationnel',
            'content' => 'operations/board_portal',
            'boardSchemaReady' => $this->planningEntries->isOperationalBoardSchemaReady(),
            'boardFilters' => $filters,
            'boardPanels' => $panels,
            'boardPosture' => $this->planningEntries->getPosture($tenantId) ?? ['posture_level' => 'NORMAL'],
            'boardToday' => date('Y-m-d'),
            'boardPortalReadOnly' => true,
        ]);
    }

       /** Écran d’accueil : un parcours par type de fiche (URL dédiée par type). */
    public function formNewHub(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }

        return Response::view('layout.main', [
            'title' => 'Nouvelle entrée — choisir le type',
            'content' => 'operations/board_entry_new_hub',
            'boardSchemaReady' => $this->planningEntries->isOperationalBoardSchemaReady(),
        ]);
    }

    /** Formulaire de création pour un type précis (slug dans l’URL). */
    public function formNewTyped(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $slug = strtolower(trim((string) ($params['entryType'] ?? '')));
        if (!in_array($slug, $this->boardEntryTypeSlugs(), true)) {
            Session::flash('error', 'Ce type de fiche n’existe pas. Choisissez-en un dans la liste.');

            return Response::redirect(url('back-office/tableau-operationnel/fiche/nouvelle'));
        }
        $prefill = $this->prefillFromQuery($request, $tenantId);
        $prefill['entry_type'] = $slug;

        return Response::view('layout.main', [
            'title' => 'Nouvelle fiche — ' . $this->boardEntryTypeTitle($slug),
            'content' => 'operations/board_entry_form',
            'boardEntry' => null,
            'boardEntryPersonnel' => [],
            'boardEntryAssets' => [],
            'boardEntryNotes' => [],
            'boardEntryChecklists' => [],
            'boardCategories' => $this->planningEntries->listCategories($tenantId),
            'boardMemberOptions' => $this->memberSelectOptions($tenantId),
            'boardPrefill' => $prefill,
            'boardLinkedSource' => $this->resolveBoardLinkedSource($tenantId, $prefill),
            'boardSchemaReady' => $this->planningEntries->isOperationalBoardSchemaReady(),
            'boardVisibilityUnits' => $this->boardUnitOptionsForForm($tenantId),
            'boardVisibilityJobRoles' => $this->boardJobRoleOptionsForVisibility($tenantId),
            'boardFormVariant' => $slug,
            'boardFormReturnUrl' => url('back-office/tableau-operationnel/fiche/nouvelle/' . rawurlencode($slug)),
        ]);
    }

    public function formEdit(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $entry = $this->planningEntries->findByIdForTenant($tenantId, $id);
        if ($entry === null) {
            Session::flash('error', 'Entrée introuvable.');

            return Response::redirect(url('back-office/tableau-operationnel'));
        }

        $variant = $this->enum((string) ($entry['entry_type'] ?? ''), $this->boardEntryTypeSlugs(), 'task');

        return Response::view('layout.main', [
            'title' => 'Modifier — ' . $this->boardEntryTypeTitle($variant),
            'content' => 'operations/board_entry_form',
            'boardEntry' => $entry,
            'boardEntryPersonnel' => $this->planningEntries->listPersonnelRowsForEntry($id),
            'boardEntryAssets' => $this->planningEntries->listAssetRowsForEntry($id),
            'boardEntryNotes' => $this->planningEntries->listNoteRowsForEntry($id),
            'boardEntryChecklists' => $this->planningEntries->listChecklistRowsForEntry($id),
            'boardCategories' => $this->planningEntries->listCategories($tenantId),
            'boardMemberOptions' => $this->memberSelectOptions($tenantId),
            'boardPrefill' => [],
            'boardLinkedSource' => $this->resolveBoardLinkedSource($tenantId, $entry),
            'boardSchemaReady' => $this->planningEntries->isOperationalBoardSchemaReady(),
            'boardVisibilityUnits' => $this->boardUnitOptionsForForm($tenantId),
            'boardVisibilityJobRoles' => $this->boardJobRoleOptionsForVisibility($tenantId),
            'boardFormVariant' => $variant,
            'boardFormReturnUrl' => url('back-office/tableau-operationnel/fiche/' . $id),
        ]);
    }

    public function storePlanningCategory(Request $request, array $params = []): Response
    {
        if (!$this->validateCsrf()) {
            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        [$tenantId, $userId] = $this->tenantAndUser();
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        $name = trim((string) $request->input('category_name', ''));
        $color = trim((string) $request->input('category_color', '#334155'));
        $redirect = trim((string) $request->input('redirect_after', ''));
        if ($name === '') {
            Session::flash('error', 'Indiquez un nom pour la rubrique.');

            return Response::redirect($this->safeBoardFormRedirect($redirect));
        }
        $newCatId = $this->planningEntries->createPlanningCategory($tenantId, $name, $color);
        if ($newCatId === null) {
            Session::flash('error', 'Impossible d’ajouter cette rubrique. Le nom est peut-être déjà utilisé.');

            return Response::redirect($this->safeBoardFormRedirect($redirect));
        }
        Session::flash('success', 'Rubrique ajoutée : vous pouvez la sélectionner dans la liste.');

        return Response::redirect($this->safeBoardFormRedirect($redirect, $newCatId));
    }

    public function update(Request $request, array $params = []): Response
    {
        if (!$this->validateCsrf()) {
            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        [$tenantId, $userId] = $this->tenantAndUser();
        $entryId = (int) ($params['id'] ?? 0);
        if ($tenantId < 1 || $userId < 1 || $entryId < 1) {
            Session::flash('error', 'Données invalides.');

            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        if ($this->planningEntries->findByIdForTenant($tenantId, $entryId) === null) {
            Session::flash('error', 'Entrée introuvable.');

            return Response::redirect(url('back-office/tableau-operationnel'));
        }

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            Session::flash('error', 'L’intitulé est obligatoire.');

            return Response::redirect(url('back-office/tableau-operationnel/fiche/' . $entryId));
        }

        $payload = $this->payloadFromRequest($request);
        $payload['title'] = $title;
        $visErr = $this->validateVisibilityTargets(
            (string) $payload['visibility_scope'],
            $payload['visibility_unit_id'] ?? null,
            $payload['visibility_job_role_ids'] ?? null
        );
        if ($visErr !== null) {
            Session::flash('error', $visErr);

            return Response::redirect(url('back-office/tableau-operationnel/fiche/' . $entryId));
        }
        $this->planningEntries->updateEntry($tenantId, $entryId, $payload, $userId);
        $this->syncChildrenFromRequest($request, $tenantId, $entryId, $userId);
        $tags = $this->parseTags((string) $request->input('tags_csv', ''));
        $this->planningEntries->replaceTagsForEntry($tenantId, $entryId, $tags, $userId);

        Session::flash('success', 'Fiche mise à jour.');

        return Response::redirect(url('back-office/tableau-operationnel/fiche/' . $entryId));
    }

    public function duplicate(Request $request, array $params = []): Response
    {
        if (!$this->validateCsrf()) {
            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        [$tenantId, $userId] = $this->tenantAndUser();
        $entryId = (int) ($params['id'] ?? 0);
        $newId = $this->planningEntries->duplicateEntry($tenantId, $entryId, $userId);
        Session::flash($newId === null ? 'error' : 'success', $newId === null ? 'Duplication impossible.' : 'Copie créée en brouillon.');

        return Response::redirect($newId ? $this->boardPilotageDraftsUrl() : url('back-office/tableau-operationnel'));
    }

    /** Création rapide depuis un événement, une mission coopération ou une formation. */
    public function storeLinked(Request $request, array $params = []): Response
    {
        if (!$this->validateCsrf()) {
            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        [$tenantId, $userId] = $this->tenantAndUser();
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        $kind = $this->enum((string) $request->input('source_type', ''), ['event', 'mission', 'formation'], '');
        $refId = (int) $request->input('source_id', 0);
        if ($kind === '' || $refId < 1) {
            Session::flash('error', 'Référence source incomplète.');

            return Response::redirect(url('back-office/tableau-operationnel'));
        }

        $title = '';
        $linkedType = $kind;
        $linkedId = $refId;
        $startDate = null;
        $endDate = null;
        $entryType = 'mission';
        $description = '';

        if ($kind === 'event') {
            if (!$this->communityEvents->belongsToTenant($refId, $tenantId)) {
                Session::flash('error', 'Événement introuvable pour cette communauté.');

                return Response::redirect(url('evenements'));
            }
            $ev = $this->communityEvents->findByIdForTenant($refId, $tenantId);
            if ($ev) {
                $title = trim((string) ($ev['title'] ?? 'Événement')) ?: 'Événement';
                $description = trim((string) ($ev['description'] ?? ''));
                $starts = $ev['starts_at'] ?? null;
                $ends = $ev['ends_at'] ?? null;
                if (is_string($starts) && $starts !== '') {
                    $startDate = substr($starts, 0, 10);
                }
                if (is_string($ends) && $ends !== '') {
                    $endDate = substr($ends, 0, 10);
                }
            } else {
                $title = 'Événement';
            }
        } elseif ($kind === 'mission') {
            if (!$this->interteamMissionVisibleToTenant($refId, $tenantId)) {
                Session::flash('error', 'Mission introuvable pour cette communauté.');

                return Response::redirect(url('back-office/cooperation/missions'));
            }
            $m = $this->interteamMissions->findById($refId);
            $title = $m ? (trim((string) ($m['title'] ?? '')) ?: 'Mission inter-unités') : 'Mission inter-unités';
            $description = $m ? trim((string) ($m['summary'] ?? $m['description'] ?? '')) : '';
            $entryType = 'mission';
        } else {
            $course = $this->trainingCourses->findByIdForViewer($refId, $tenantId);
            if ($course === null) {
                Session::flash('error', 'Formation introuvable.');

                return Response::redirect(url('formations'));
            }
            $title = trim((string) ($course['title'] ?? '')) ?: 'Formation';
            $description = trim((string) ($course['summary'] ?? $course['description'] ?? ''));
            $entryType = 'formation';
        }

        $existingId = $this->planningEntries->findOpenEntryIdForLink($tenantId, $linkedType, $linkedId);
        if ($existingId !== null) {
            $msg = match ($kind) {
                'event' => 'Une fiche du tableau opérationnel est déjà rattachée à cet événement.',
                'formation' => 'Une fiche du tableau opérationnel est déjà rattachée à cette formation.',
                default => 'Une fiche du tableau opérationnel est déjà rattachée à cette mission.',
            };
            if ($this->requestPrefersJson()) {
                return Response::json([
                    'success' => false,
                    'code' => 'duplicate_linked_entry',
                    'existing_entry_id' => $existingId,
                    'message' => $msg,
                ], 409);
            }
            Session::flash('error', $msg);

            return Response::redirect(url('back-office/tableau-operationnel/fiche/' . $existingId));
        }

        $newId = $this->planningEntries->create([
            'tenant_id' => $tenantId,
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'entry_type' => $entryType,
            'category_id' => null,
            'linked_type' => $linkedType,
            'linked_id' => $linkedId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'draft',
            'validation_status' => 'draft',
            'priority' => 'normal',
            'display_order' => 100,
            'visibility_scope' => 'tenant',
            'security_level' => 'unit_public',
            'operational_status' => 'planned',
            'phase_current' => 'phase_1',
            'chief_user_id' => null,
            'deputy_user_id' => null,
            'replacement_user_id' => null,
            'replacement_auto_activate' => 0,
            'command_chain' => null,
            'accountability_note' => null,
            'location_lat' => null,
            'location_lng' => null,
            'operation_zone' => null,
            'map_link' => null,
            'dossier_ref' => null,
            'legal_constraints' => null,
            'fire_window_start' => null,
            'fire_window_end' => null,
            'created_by' => $userId,
        ]);
        if ($newId < 1) {
            Session::flash('error', 'Le tableau opérationnel n’est pas disponible sur ce serveur.');

            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        Session::flash('success', 'Brouillon créé et lié à la source. Complétez la fiche puis validez la publication.');

        return Response::redirect($this->boardPilotageDraftsUrl());
    }

    public function store(Request $request, array $params = []): Response
    {
        if (!$this->validateCsrf()) {
            return Response::redirect(url('back-office/tableau-operationnel'));
        }

        [$tenantId, $userId] = $this->tenantAndUser();
        $title = trim((string) $request->input('title', ''));
        if ($tenantId < 1 || $userId < 1 || $title === '') {
            Session::flash('error', 'Impossible de créer l\'entrée (titre/session).');
            return Response::redirect(url('back-office/tableau-operationnel'));
        }

        $scope = $this->enum($request->input('visibility_scope', 'tenant'), ['tenant', 'unit', 'role', 'private'], 'tenant');
        $vis = $this->visibilityTargetsFromRequest($request, $scope);
        $visErr = $this->validateVisibilityTargets($scope, $vis['visibility_unit_id'], $vis['visibility_job_role_ids']);
        if ($visErr !== null) {
            Session::flash('error', $visErr);

            return Response::redirect(url('back-office/tableau-operationnel/fiche/nouvelle'));
        }

        $newId = $this->planningEntries->create([
            'tenant_id' => $tenantId,
            'title' => $title,
            'description' => trim((string) $request->input('description', '')),
            'entry_type' => $this->enum($request->input('entry_type', 'task'), ['permanence', 'info', 'mission', 'task', 'formation', 'manifestation', 'flash_info'], 'task'),
            'category_id' => (int) $request->input('category_id', 0),
            'linked_type' => $this->normalizeLinkedType((string) $request->input('linked_type', 'none')),
            'linked_id' => $this->nullableInt($request->input('linked_id', '')),
            'start_date' => trim((string) $request->input('start_date', '')),
            'end_date' => trim((string) $request->input('end_date', '')),
            'all_day' => (int) $request->input('all_day', 1) === 1 ? 1 : 0,
            'status' => 'draft',
            'validation_status' => 'draft',
            'priority' => $this->enum($request->input('priority', 'normal'), ['low', 'normal', 'high', 'critical'], 'normal'),
            'display_order' => (int) $request->input('display_order', 100),
            'visibility_scope' => $scope,
            'visibility_unit_id' => $vis['visibility_unit_id'],
            'visibility_job_role_ids' => $vis['visibility_job_role_ids'],
            'security_level' => $this->enum($request->input('security_level', 'unit_public'), ['unit_public', 'command_restricted', 'confidential', 'secret_ops'], 'unit_public'),
            'operational_status' => $this->enum($request->input('operational_status', 'planned'), ['planned', 'in_progress', 'suspended', 'completed', 'cancelled'], 'planned'),
            'phase_current' => $this->enum($request->input('phase_current', 'phase_1'), ['phase_1', 'phase_2', 'phase_3'], 'phase_1'),
            'chief_user_id' => $this->nullableInt($request->input('chief_user_id', '')),
            'deputy_user_id' => $this->nullableInt($request->input('deputy_user_id', '')),
            'replacement_user_id' => $this->nullableInt($request->input('replacement_user_id', '')),
            'replacement_auto_activate' => (int) $request->input('replacement_auto_activate', 0),
            'command_chain' => trim((string) $request->input('command_chain', '')),
            'accountability_note' => trim((string) $request->input('accountability_note', '')),
            'location_lat' => trim((string) $request->input('location_lat', '')),
            'location_lng' => trim((string) $request->input('location_lng', '')),
            'operation_zone' => trim((string) $request->input('operation_zone', '')),
            'map_link' => trim((string) $request->input('map_link', '')),
            'dossier_ref' => trim((string) $request->input('dossier_ref', '')),
            'legal_constraints' => trim((string) $request->input('legal_constraints', '')),
            'fire_window_start' => $this->normalizeDatetimeLocal((string) $request->input('fire_window_start', '')),
            'fire_window_end' => $this->normalizeDatetimeLocal((string) $request->input('fire_window_end', '')),
            'created_by' => $userId,
        ]);
        if ($newId < 1) {
            Session::flash('error', 'Le tableau opérationnel n’est pas encore activé sur ce serveur. Un administrateur doit exécuter les mises à jour de base de données (script d’initialisation du site).');
            return Response::redirect(url('back-office/tableau-operationnel'));
        }

        $this->syncChildrenFromRequest($request, $tenantId, $newId, $userId);
        $tags = $this->parseTags((string) $request->input('tags_csv', ''));
        $this->planningEntries->replaceTagsForEntry($tenantId, $newId, $tags, $userId);

        Session::flash('success', 'Entrée créée en brouillon.');
        return Response::redirect($this->boardPilotageDraftsUrl());
    }

    public function setPosture(Request $request, array $params = []): Response
    {
        if (!$this->validateCsrf()) {
            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        [$tenantId, $userId] = $this->tenantAndUser();
        $level = $this->enum($request->input('posture_level', 'NORMAL'), ['NORMAL', 'VIGILANCE', 'ALERTE', 'CRISE'], 'NORMAL');
        $this->planningEntries->setPosture($tenantId, $level, $userId);
        $labels = [
            'NORMAL' => 'niveau normal',
            'VIGILANCE' => 'vigilance renforcée',
            'ALERTE' => 'alerte',
            'CRISE' => 'crise',
        ];
        Session::flash('success', 'Niveau de posture appliqué : ' . ($labels[$level] ?? 'mis à jour') . '.');

        return Response::redirect(url('back-office/tableau-operationnel'));
    }

    public function transitionValidation(Request $request, array $params = []): Response
    {
        if (!$this->validateCsrf()) {
            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        [$tenantId, $userId] = $this->tenantAndUser();
        $entryId = (int) ($params['id'] ?? 0);
        $status = $this->enum($request->input('validation_status', 'validated'), ['draft', 'validated', 'active', 'rejected'], 'validated');
        $reason = trim((string) $request->input('validation_comment', ''));
        $this->planningEntries->transitionValidation($tenantId, $entryId, $status, $reason !== '' ? $reason : null, $userId);
        Session::flash('success', 'Validation mise à jour.');

        return Response::redirect(url('back-office/tableau-operationnel'));
    }

    public function transitionOperationalStatus(Request $request, array $params = []): Response
    {
        if (!$this->validateCsrf()) {
            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        [$tenantId, $userId] = $this->tenantAndUser();
        $entryId = (int) ($params['id'] ?? 0);
        $status = $this->enum($request->input('operational_status', 'planned'), ['planned', 'in_progress', 'suspended', 'completed', 'cancelled'], 'planned');
        if (!$this->planningEntries->transitionOperationalStatus($tenantId, $entryId, $status, $userId)) {
            Session::flash('error', 'Transition refusée (checklist requise ou statut invalide).');
        } else {
            Session::flash('success', 'Cycle opérationnel mis à jour.');
        }

        return Response::redirect(url('back-office/tableau-operationnel'));
    }

    public function createFromTemplate(Request $request, array $params = []): Response
    {
        if (!$this->validateCsrf()) {
            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        [$tenantId, $userId] = $this->tenantAndUser();
        $entryId = $this->planningEntries->createFromTemplate($tenantId, (int) $request->input('template_id', 0), $userId);
        Session::flash($entryId === null ? 'error' : 'success', $entryId === null ? 'Modèle introuvable ou inactif.' : 'Une nouvelle fiche brouillon a été créée à partir du modèle.');

        return Response::redirect($entryId ? $this->boardPilotageDraftsUrl() : url('back-office/tableau-operationnel'));
    }

    /** Création d’un modèle vide (squelette) depuis le tableau opérationnel. */
    public function storePlanningTemplate(Request $request, array $params = []): Response
    {
        if (!$this->validateCsrf()) {
            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        [$tenantId, $userId] = $this->tenantAndUser();
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        $name = trim((string) $request->input('template_name', ''));
        if ($name === '') {
            Session::flash('error', 'Indiquez un nom pour le modèle.');

            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        $family = $this->enum(
            (string) $request->input('mission_family', 'custom'),
            ['permanence_opj', 'mission_judiciaire', 'instruction', 'dispositif_securite', 'exercice', 'custom'],
            'custom'
        );
        $entryType = $this->enum(
            (string) $request->input('entry_type', 'task'),
            ['permanence', 'info', 'mission', 'task', 'formation', 'manifestation', 'flash_info'],
            'task'
        );
        $title = trim((string) $request->input('default_title', ''));
        if ($title === '') {
            $title = $name;
        }
        $description = trim((string) $request->input('default_description', ''));
        $payload = [
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'entry_type' => $entryType,
            'category_id' => 0,
            'linked_type' => null,
            'linked_id' => null,
            'all_day' => 1,
            'priority' => $this->enum($request->input('priority', 'normal'), ['low', 'normal', 'high', 'critical'], 'normal'),
            'display_order' => (int) $request->input('display_order', 100),
            'visibility_scope' => $this->enum($request->input('visibility_scope', 'tenant'), ['tenant', 'unit', 'role', 'private'], 'tenant'),
            'security_level' => $this->enum($request->input('security_level', 'unit_public'), ['unit_public', 'command_restricted', 'confidential', 'secret_ops'], 'unit_public'),
            'operational_status' => 'planned',
            'phase_current' => 'phase_1',
            'chief_user_id' => null,
            'deputy_user_id' => null,
            'replacement_user_id' => null,
            'replacement_auto_activate' => 0,
            'command_chain' => null,
            'accountability_note' => null,
            'location_lat' => null,
            'location_lng' => null,
            'operation_zone' => null,
            'map_link' => null,
            'dossier_ref' => null,
            'legal_constraints' => null,
            'fire_window_start' => null,
            'fire_window_end' => null,
        ];
        $newTplId = $this->planningEntries->createPlanningTemplate($tenantId, $name, $family, $payload, $userId);
        if ($newTplId === null) {
            Session::flash('error', 'Impossible d’enregistrer ce modèle pour le moment.');

            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        Session::flash('success', 'Modèle enregistré. Vous pouvez l’utiliser ci-dessous pour générer une nouvelle fiche brouillon.');

        return Response::redirect(url('back-office/tableau-operationnel'));
    }

    /** Enregistre la fiche courante comme modèle réutilisable (sans dates ni liaison métier). */
    public function storePlanningTemplateFromEntry(Request $request, array $params = []): Response
    {
        if (!$this->validateCsrf()) {
            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        [$tenantId, $userId] = $this->tenantAndUser();
        $entryId = (int) ($params['id'] ?? 0);
        if ($tenantId < 1 || $userId < 1 || $entryId < 1) {
            Session::flash('error', 'Données invalides.');

            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        $entry = $this->planningEntries->findByIdForTenant($tenantId, $entryId);
        if ($entry === null) {
            Session::flash('error', 'Entrée introuvable.');

            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        $name = trim((string) $request->input('template_name', ''));
        if ($name === '') {
            Session::flash('error', 'Indiquez un nom pour le modèle.');

            return Response::redirect(url('back-office/tableau-operationnel/fiche/' . $entryId));
        }
        $family = $this->enum(
            (string) $request->input('mission_family', 'custom'),
            ['permanence_opj', 'mission_judiciaire', 'instruction', 'dispositif_securite', 'exercice', 'custom'],
            'custom'
        );
        $payload = $this->planningEntries->planningEntryRowToTemplatePayload($entry);
        $newTplId = $this->planningEntries->createPlanningTemplate($tenantId, $name, $family, $payload, $userId);
        if ($newTplId === null) {
            Session::flash('error', 'Impossible d’enregistrer ce modèle.');

            return Response::redirect(url('back-office/tableau-operationnel/fiche/' . $entryId));
        }
        Session::flash('success', 'Modèle créé à partir de cette fiche (dates et rattachement métier exclus — à compléter sur chaque nouvelle fiche).');

        return Response::redirect(url('back-office/tableau-operationnel/fiche/' . $entryId));
    }

    public function createFrago(Request $request, array $params = []): Response
    {
        if (!$this->validateCsrf()) {
            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        [$tenantId, $userId] = $this->tenantAndUser();
        $entryId = $this->planningEntries->createFrago($tenantId, (int) ($params['id'] ?? 0), $userId);
        Session::flash($entryId === null ? 'error' : 'success', $entryId === null ? 'FRAGO impossible.' : 'FRAGO généré.');

        return Response::redirect($entryId ? url('back-office/tableau-operationnel/fiche/' . $entryId) : url('back-office/tableau-operationnel'));
    }

    public function toggleChecklist(Request $request, array $params = []): Response
    {
        if (!$this->validateCsrf()) {
            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        [$tenantId, $userId] = $this->tenantAndUser();
        $entryId = (int) ($params['id'] ?? 0);
        $itemId = (int) ($params['itemId'] ?? 0);
        $done = (int) $request->input('is_done', 0) === 1;
        $this->planningEntries->markChecklistItem($tenantId, $entryId, $itemId, $done, $userId);

        return Response::redirect(url('back-office/tableau-operationnel/fiche/' . $entryId));
    }

    public function retireFromBoard(Request $request, array $params = []): Response
    {
        if (!$this->validateCsrf()) {
            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        [$tenantId, $userId] = $this->tenantAndUser();
        $entryId = (int) ($params['id'] ?? 0);
        if ($tenantId < 1 || $userId < 1 || $entryId < 1) {
            Session::flash('error', 'Données invalides.');

            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        $reason = trim((string) $request->input('retire_reason', ''));
        if ($this->planningEntries->retireFromBoard($tenantId, $entryId, $userId, $reason !== '' ? $reason : null)) {
            Session::flash('success', 'L’entrée a été retirée du mur opérationnel. Elle n’est plus visible sur le portail.');
        } else {
            Session::flash('error', 'Impossible de retirer cette entrée. Vérifiez qu’elle existe encore.');
        }

        return Response::redirect(url('back-office/tableau-operationnel'));
    }

    public function stream(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $since = (int) ($request->query('since_id') ?? 0);

        return Response::json([
            'ok' => true,
            'events' => $this->planningEntries->listRealtimeSnapshot($tenantId, $since, 100),
        ]);
    }

    private function validateCsrf(): bool
    {
        if (!Csrf::validate((string) ($_POST['_csrf_token'] ?? ''))) {
            Session::flash('error', 'Session expirée.');

            return false;
        }

        return true;
    }

    /** @return array{0:int,1:int} */
    private function tenantAndUser(): array
    {
        return [(int) (Session::get('tenant_id') ?? 0), (int) (Session::get('user_id') ?? 0)];
    }

    private function enum(mixed $value, array $allowed, string $fallback): string
    {
        $value = trim((string) $value);

        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function nullableInt(mixed $value): ?int
    {
        $v = trim((string) $value);
        $i = (int) $v;

        return $i > 0 ? $i : null;
    }

    private function normalizeLinkedType(string $linkedType): ?string
    {
        $normalized = $this->enum($linkedType, ['event', 'mission', 'formation', 'none'], 'none');

        return $normalized === 'none' ? null : $normalized;
    }

    /** @return list<string> */
    private function boardEntryTypeSlugs(): array
    {
        return ['permanence', 'info', 'manifestation', 'mission', 'task', 'formation', 'flash_info'];
    }

    private function boardEntryTypeTitle(string $slug): string
    {
        return match ($slug) {
            'permanence' => 'Permanence',
            'info' => 'Information pratique',
            'manifestation' => 'Manifestation / dispositif',
            'mission' => 'Mission',
            'task' => 'Tâche interne',
            'formation' => 'Activité de formation',
            'flash_info' => 'Flash information',
            default => 'Fiche tableau opérationnel',
        };
    }

    private function safeBoardFormRedirect(string $redirect, ?int $selectCategoryId = null): string
    {
        $base = rtrim(url(''), '/');
        $redirect = trim($redirect);
        if ($redirect === '' || !str_starts_with($redirect, $base)) {
            $redirect = url('back-office/tableau-operationnel/fiche/nouvelle');
        }
        if ($selectCategoryId !== null && $selectCategoryId > 0) {
            $redirect .= (str_contains($redirect, '?') ? '&' : '?') . 'category_id=' . $selectCategoryId;
        }

        return $redirect;
    }

    /** Pilotage du mur : vue filtrée sur les fiches encore en brouillon (non publiées). */
    private function boardPilotageDraftsUrl(): string
    {
        return url('back-office/tableau-operationnel') . '?' . http_build_query(['status' => 'draft'], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return list<array{id:int,label:string}> */
    private function boardUnitOptionsForForm(int $tenantId): array
    {
        $flat = $this->units->listFlatForStructure($tenantId);
        $byId = [];
        foreach ($flat as $row) {
            $byId[(int) $row['id']] = $row;
        }
        $depth = null;
        $depth = function (int $id) use (&$byId, &$depth): int {
            if (!isset($byId[$id])) {
                return 0;
            }
            $p = $byId[$id]['parent_id'];
            $pid = $p !== null && $p !== '' ? (int) $p : 0;
            if ($pid < 1) {
                return 0;
            }

            return 1 + $depth($pid);
        };
        $out = [];
        foreach ($flat as $row) {
            $id = (int) $row['id'];
            $d = $depth($id);
            $pad = $d > 0 ? str_repeat('· ', $d) : '';
            $out[] = ['id' => $id, 'label' => $pad . (string) $row['name']];
        }

        return $out;
    }

    /** @return list<array{id:int,label:string}> */
    private function boardJobRoleOptionsForVisibility(int $tenantId): array
    {
        if (!$this->jobRoles->tablesExist() || !$this->jobRoles->personnelProfilesHaveJobRoleColumns()) {
            return [];
        }
        $opts = $this->jobRoles->listRoleOptionsForSelect($tenantId, false, true, 'fr');
        $out = [];
        foreach ($opts as $o) {
            $out[] = ['id' => (int) ($o['id'] ?? 0), 'label' => (string) ($o['label'] ?? '')];
        }

        return $out;
    }

    /**
     * @return array{visibility_unit_id: ?int, visibility_job_role_ids: ?string}
     */
    private function visibilityTargetsFromRequest(Request $request, string $scope): array
    {
        if ($scope === 'unit') {
            return [
                'visibility_unit_id' => $this->nullableInt($request->input('visibility_unit_id', '')),
                'visibility_job_role_ids' => null,
            ];
        }
        if ($scope === 'role') {
            return [
                'visibility_unit_id' => null,
                'visibility_job_role_ids' => $this->normalizeVisibilityJobRoleJson($request->input('visibility_job_role_id', [])),
            ];
        }

        return ['visibility_unit_id' => null, 'visibility_job_role_ids' => null];
    }

    private function normalizeVisibilityJobRoleJson(mixed $raw): ?string
    {
        if (!is_array($raw)) {
            return null;
        }
        $ids = [];
        foreach ($raw as $x) {
            $i = (int) $x;
            if ($i > 0) {
                $ids[$i] = true;
            }
        }
        if ($ids === []) {
            return null;
        }
        $list = array_keys($ids);
        sort($list);

        return json_encode($list);
    }

    private function validateVisibilityTargets(string $scope, ?int $unitId, ?string $roleJson): ?string
    {
        if ($scope === 'unit') {
            if ($unitId === null || $unitId < 1) {
                return 'Pour une diffusion limitée à une unité, sélectionnez l’unité dans la liste.';
            }
        }
        if ($scope === 'role') {
            $rj = $roleJson !== null ? trim($roleJson) : '';
            if ($rj === '' || $rj === '[]') {
                return 'Pour une diffusion limitée à certains emplois métier, cochez au moins un emploi dans la liste.';
            }
        }

        return null;
    }

    /** @return list<array<string, list<array<string, mixed>>>> */
    private function partitionBoardPanels(array $entries): array
    {
        $panels = [
            'permanences' => [],
            'infos' => [],
            'manifestations' => [],
            'flash' => [],
            'activites' => [],
        ];
        foreach ($entries as $entry) {
            $type = (string) ($entry['entry_type'] ?? 'task');
            if ($type === 'permanence') {
                $panels['permanences'][] = $entry;
            } elseif ($type === 'info') {
                $panels['infos'][] = $entry;
            } elseif ($type === 'manifestation') {
                $panels['manifestations'][] = $entry;
            } elseif ($type === 'flash_info') {
                $panels['flash'][] = $entry;
            } else {
                $panels['activites'][] = $entry;
            }
        }

        return $panels;
    }

    /** @return list<array{id:int,label:string}> */
    private function memberSelectOptions(int $tenantId): array
    {
        $rows = $this->users->listForTenant($tenantId, null, 'active', null, 400, 0, true, null, null);
        $out = [];
        foreach ($rows as $u) {
            $id = (int) ($u['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $label = trim((string) ($u['display_name'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($u['callsign'] ?? ''));
            }
            if ($label === '') {
                $label = (string) ($u['email'] ?? 'Membre');
            }
            $out[] = ['id' => $id, 'label' => $label];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function prefillFromQuery(Request $request, int $tenantId): array
    {
        $out = [];
        $lt = trim((string) $request->query('lie_a', ''));
        $ref = $this->nullableInt($request->query('reference', ''));
        if ($ref !== null && in_array($lt, ['event', 'mission', 'formation'], true)) {
            $out['linked_type'] = $lt;
            $out['linked_id'] = $ref;
        }

        $typeHint = trim((string) $request->query('type', ''));
        if ($typeHint !== '') {
            $typeHint = $this->enum($typeHint, ['permanence', 'info', 'mission', 'task', 'formation', 'manifestation', 'flash_info'], '');
            if ($typeHint !== '') {
                $out['entry_type'] = $typeHint;
            }
        }

        $titleHint = trim((string) $request->query('titre', ''));
        if ($titleHint !== '') {
            $out['title'] = mb_substr($titleHint, 0, 500);
        }

        $debut = trim((string) $request->query('debut', ''));
        if ($debut !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $debut) === 1) {
            $out['start_date'] = $debut;
        }

        $catPre = $this->nullableInt($request->query('category_id', ''));
        if ($catPre !== null) {
            $out['category_id'] = $catPre;
        }

        return $out;
    }

    private function interteamMissionVisibleToTenant(int $missionId, int $tenantId): bool
    {
        foreach ($this->interteamMissions->listForTenant($tenantId) as $m) {
            if ((int) ($m['id'] ?? 0) === $missionId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Libellé + lien vers la source métier pour l’UI (événement, coopération, formation).
     *
     * @param array<string, mixed> $row Ligne fiche ou préremplissage avec linked_type / linked_id
     *
     * @return array{label: string, url: ?string}|null
     */
    private function resolveBoardLinkedSource(int $tenantId, array $row): ?array
    {
        $lt = trim((string) ($row['linked_type'] ?? ''));
        $lid = (int) ($row['linked_id'] ?? 0);
        if ($lid < 1 || !in_array($lt, ['event', 'mission', 'formation'], true)) {
            return null;
        }
        if ($lt === 'event') {
            $ev = $this->communityEvents->findByIdForTenant($lid, $tenantId);
            $title = $ev ? trim((string) ($ev['title'] ?? '')) : '';
            if ($title === '') {
                $title = 'Événement de l’agenda';
            }

            return [
                'label' => 'Événement : ' . $title,
                'url' => url('evenements'),
            ];
        }
        if ($lt === 'mission') {
            if (!$this->interteamMissionVisibleToTenant($lid, $tenantId)) {
                return [
                    'label' => 'Coopération inter-unités (accès restreint ou source indisponible)',
                    'url' => null,
                ];
            }
            $m = $this->interteamMissions->findById($lid);
            $title = $m ? trim((string) ($m['title'] ?? '')) : '';
            if ($title === '') {
                $title = 'Coopération inter-unités';
            }

            return [
                'label' => 'Coopération : ' . $title,
                'url' => cooperation_mission_show_url($lid),
            ];
        }
        $course = $this->trainingCourses->findByIdForViewer($lid, $tenantId);
        if ($course === null) {
            return [
                'label' => 'Formation (parcours indisponible ou non accessible)',
                'url' => url('formations'),
            ];
        }
        $slug = trim((string) ($course['slug'] ?? ''));
        $title = trim((string) ($course['title'] ?? '')) ?: 'Formation';

        return [
            'label' => 'Formation : ' . $title,
            'url' => $slug !== '' ? url('formations/' . $slug) : url('formations'),
        ];
    }

    /** @return array<string, mixed> */
    private function payloadFromRequest(Request $request): array
    {
        $scope = $this->enum($request->input('visibility_scope', 'tenant'), ['tenant', 'unit', 'role', 'private'], 'tenant');
        $vis = $this->visibilityTargetsFromRequest($request, $scope);

        return [
            'title' => '',
            'description' => trim((string) $request->input('description', '')),
            'entry_type' => $this->enum($request->input('entry_type', 'task'), ['permanence', 'info', 'mission', 'task', 'formation', 'manifestation', 'flash_info'], 'task'),
            'category_id' => (int) $request->input('category_id', 0),
            'linked_type' => $this->normalizeLinkedType((string) $request->input('linked_type', 'none')),
            'linked_id' => $this->nullableInt($request->input('linked_id', '')),
            'start_date' => trim((string) $request->input('start_date', '')),
            'end_date' => trim((string) $request->input('end_date', '')),
            'all_day' => (int) $request->input('all_day', 1) === 1,
            'priority' => $this->enum($request->input('priority', 'normal'), ['low', 'normal', 'high', 'critical'], 'normal'),
            'display_order' => (int) $request->input('display_order', 100),
            'visibility_scope' => $scope,
            'visibility_unit_id' => $vis['visibility_unit_id'],
            'visibility_job_role_ids' => $vis['visibility_job_role_ids'],
            'security_level' => $this->enum($request->input('security_level', 'unit_public'), ['unit_public', 'command_restricted', 'confidential', 'secret_ops'], 'unit_public'),
            'operational_status' => $this->enum($request->input('operational_status', 'planned'), ['planned', 'in_progress', 'suspended', 'completed', 'cancelled'], 'planned'),
            'phase_current' => $this->enum($request->input('phase_current', 'phase_1'), ['phase_1', 'phase_2', 'phase_3'], 'phase_1'),
            'chief_user_id' => $this->nullableInt($request->input('chief_user_id', '')),
            'deputy_user_id' => $this->nullableInt($request->input('deputy_user_id', '')),
            'replacement_user_id' => $this->nullableInt($request->input('replacement_user_id', '')),
            'replacement_auto_activate' => (int) $request->input('replacement_auto_activate', 0),
            'command_chain' => trim((string) $request->input('command_chain', '')),
            'accountability_note' => trim((string) $request->input('accountability_note', '')),
            'location_lat' => trim((string) $request->input('location_lat', '')),
            'location_lng' => trim((string) $request->input('location_lng', '')),
            'operation_zone' => trim((string) $request->input('operation_zone', '')),
            'map_link' => trim((string) $request->input('map_link', '')),
            'dossier_ref' => trim((string) $request->input('dossier_ref', '')),
            'legal_constraints' => trim((string) $request->input('legal_constraints', '')),
            'fire_window_start' => $this->normalizeDatetimeLocal((string) $request->input('fire_window_start', '')),
            'fire_window_end' => $this->normalizeDatetimeLocal((string) $request->input('fire_window_end', '')),
        ];
    }

    private function syncChildrenFromRequest(Request $request, int $tenantId, int $entryId, int $userId): void
    {
        $personnel = [];
        $pUser = $request->input('personnel_user_id', []);
        $pRole = $request->input('personnel_role', []);
        $pLead = $request->input('personnel_is_lead', []);
        if (is_array($pUser)) {
            foreach ($pUser as $i => $uid) {
                $uid = (int) $uid;
                if ($uid < 1) {
                    continue;
                }
                $role = is_array($pRole) && isset($pRole[$i]) ? trim((string) $pRole[$i]) : '';
                $lead = is_array($pLead) && isset($pLead[$i]) && (int) $pLead[$i] === 1;
                $personnel[] = ['user_id' => $uid, 'role_label' => $role, 'is_lead' => $lead];
            }
        }
        $this->planningEntries->replacePersonnelForEntry($tenantId, $entryId, $personnel, $userId);

        $assets = [];
        $aType = $request->input('asset_type', []);
        $aLabel = $request->input('asset_label', []);
        $aRef = $request->input('asset_reference', []);
        $aState = $request->input('asset_state', []);
        if (is_array($aLabel)) {
            foreach ($aLabel as $i => $lab) {
                $lab = trim((string) $lab);
                if ($lab === '') {
                    continue;
                }
                $typ = is_array($aType) && isset($aType[$i]) ? trim((string) $aType[$i]) : 'moyen';
                $ref = is_array($aRef) && isset($aRef[$i]) ? trim((string) $aRef[$i]) : '';
                $st = is_array($aState) && isset($aState[$i]) ? trim((string) $aState[$i]) : 'available';
                $assets[] = ['type' => $typ, 'label' => $lab, 'reference' => $ref, 'state' => $st];
            }
        }
        $this->planningEntries->replaceAssetsForEntry($tenantId, $entryId, $assets, $userId);

        $notes = [];
        $nType = $request->input('note_type', []);
        $nContent = $request->input('note_content', []);
        $nPin = $request->input('note_pinned', []);
        if (is_array($nContent)) {
            foreach ($nContent as $i => $content) {
                $content = trim((string) $content);
                if ($content === '') {
                    continue;
                }
                $nt = is_array($nType) && isset($nType[$i]) ? trim((string) $nType[$i]) : 'consigne';
                $pin = is_array($nPin) && isset($nPin[$i]) && (int) $nPin[$i] === 1;
                $notes[] = ['type' => $nt, 'content' => $content, 'pinned' => $pin];
            }
        }
        $this->planningEntries->replaceNotesForEntry($tenantId, $entryId, $notes, $userId);
    }

    /** @return list<string> */
    private function parseTags(string $csv): array
    {
        $parts = preg_split('/[,;]+/', $csv) ?: [];

        return array_values(array_filter(array_map('trim', $parts)));
    }

    private function normalizeDatetimeLocal(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $raw = str_replace('T', ' ', $raw);

        return strlen($raw) === 16 ? $raw . ':00' : $raw;
    }

    private function requestPrefersJson(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        if (str_contains($accept, 'application/json')) {
            return true;
        }
        $xhr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

        return $xhr === 'xmlhttprequest';
    }
}
