<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\HrCharterRepository;
use App\Repositories\PersonnelAbsenceRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelHrDocumentRepository;
use App\Repositories\PersonnelMobilityRequestRepository;
use App\Repositories\PlatformModuleReleaseRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Personnel\SeniorityDossierInferenceSyncService;
use App\Services\Personnel\SeniorityEnrollmentBootstrapService;
use App\Services\Personnel\SenioritySummaryService;
use App\Services\Platform\FeatureGateService;
use App\Support\PersonnelDossierCompleteness;
use App\Support\PersonnelHrDocumentStorage;

final class RhWorkspaceController
{
    public function __construct(
        private AuthService $authService,
        private FeatureGateService $featureGate,
        private HrCharterRepository $hrCharterRepository,
        private SenioritySummaryService $senioritySummaryService,
        private PlatformModuleReleaseRepository $platformModuleReleaseRepository,
        private PersonnelAssignmentRepository $personnelAssignmentRepository,
        private SeniorityEnrollmentBootstrapService $seniorityEnrollmentBootstrapService,
        private SeniorityDossierInferenceSyncService $seniorityDossierInferenceSyncService,
        private UserRepository $userRepository,
        private PersonnelAbsenceRepository $personnelAbsenceRepository,
        private ?PersonnelMobilityRequestRepository $mobilityRequests = null,
        private ?PersonnelHrDocumentRepository $hrDocuments = null,
    ) {
        $this->mobilityRequests ??= new PersonnelMobilityRequestRepository();
        $this->hrDocuments ??= new PersonnelHrDocumentRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$user || $tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }

        $trainingAllowed = $this->featureGate->allows($tenantId, 'training');
        $charterReady = $this->hrCharterRepository->schemaReady();
        $charterDoc = $charterReady ? $this->hrCharterRepository->getActiveDocumentForTenant($tenantId) : null;
        $charterAccepted = false;
        if ($charterDoc !== null) {
            $charterAccepted = $this->hrCharterRepository->userHasAcceptedDocument($userId, (int) ($charterDoc['id'] ?? 0));
        }

        $seniorityLines = $this->senioritySummaryService->linesForPersonnelFile($tenantId, $userId);

        $richRows = $this->userRepository->listEffectifsRosterByIds($tenantId, [$userId]);
        $rich = $richRows[0] ?? [];
        $dossierCompleteness = PersonnelDossierCompleteness::evaluate(
            $user,
            $rich,
            !empty($rich['unit_id'])
        );

        $testerCommunities = [];
        $rolloutRows = [];
        if ($this->platformModuleReleaseRepository->schemaReady()) {
            $testerCommunities = $this->platformModuleReleaseRepository->listActiveTesterCommunitiesForUser($userId);
            $rawRows = $this->platformModuleReleaseRepository->listModuleAccessRowsForUserTesterCommunities($userId);
            foreach ($rawRows as $row) {
                $mid = (int) ($row['module_id'] ?? 0);
                $byChannel = $mid > 0
                    ? $this->platformModuleReleaseRepository->findCurrentReleasesByChannelForModule($mid)
                    : [];
                $testRelease = $byChannel['TEST'] ?? null;
                $rolloutRows[] = [
                    'module_name' => (string) ($row['module_name'] ?? ''),
                    'module_description' => $row['module_description'] ?? null,
                    'rule_type' => (string) ($row['rule_type'] ?? ''),
                    'rule_label' => $this->accessRuleLabel((string) ($row['rule_type'] ?? '')),
                    'evaluation_version' => $testRelease['version'] ?? null,
                ];
            }
        }

        $greetingName = trim((string) ($user['display_name'] ?? ''));
        if ($greetingName === '') {
            $greetingName = trim((string) ($user['callsign'] ?? ''));
        }

        $absencesSchemaReady = $this->personnelAbsenceRepository->tableExists();
        $personnelAbsences = $absencesSchemaReady
            ? $this->personnelAbsenceRepository->listForUser($tenantId, $userId, 40)
            : [];
        $activeAbsences = $absencesSchemaReady
            ? $this->personnelAbsenceRepository->listActiveForUser($tenantId, $userId)
            : [];

        $mobilitySchemaReady = $this->mobilityRequests->tableExists();
        $myMobility = $mobilitySchemaReady
            ? $this->mobilityRequests->listForUser($tenantId, $userId, 20)
            : [];
        $hrDocsSchemaReady = $this->hrDocuments->tableExists();
        $myHrDocs = $hrDocsSchemaReady
            ? $this->hrDocuments->listForUser($tenantId, $userId, false, true)
            : [];

        return Response::view('layout.main', [
            'title' => 'Espace RH et formations',
            'content' => 'personnel.rh_workspace',
            'rhGreetingName' => $greetingName,
            'rhTrainingAllowed' => $trainingAllowed,
            'rhCharterReady' => $charterReady,
            'rhCharterAccepted' => $charterAccepted,
            'rhSeniorityLines' => $seniorityLines,
            'rhDossierCompleteness' => $dossierCompleteness,
            'rhTesterCommunities' => $testerCommunities,
            'rhRolloutRows' => $rolloutRows,
            'rhWorkspaceCsrf' => Csrf::token(),
            'rhAbsencesSchemaReady' => $absencesSchemaReady,
            'rhPersonnelAbsences' => $personnelAbsences,
            'rhActiveAbsences' => $activeAbsences,
            'rhAbsenceReasonLabels' => PersonnelAbsenceRepository::REASON_LABELS,
            'rhMobilitySchemaReady' => $mobilitySchemaReady,
            'rhMyMobility' => $myMobility,
            'rhMobilityTypeLabels' => PersonnelMobilityRequestRepository::TYPE_LABELS,
            'rhHrDocsSchemaReady' => $hrDocsSchemaReady,
            'rhMyHrDocs' => $myHrDocs,
            'rhHrDocTypeLabels' => PersonnelHrDocumentRepository::DOC_TYPE_LABELS,
        ]);
    }

    public function storeCareerWish(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$user || $tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré. Rechargez la page puis réessayez.');

            return $this->rhFormRedirect($request, 'mobilite');
        }
        if (!$this->mobilityRequests->tableExists()) {
            Session::flash('error', 'Les souhaits d’évolution ne sont pas encore disponibles.');

            return $this->rhFormRedirect($request, 'mobilite');
        }
        $type = trim((string) $request->input('request_type', 'career_wish'));
        if (!in_array($type, PersonnelMobilityRequestRepository::TYPES, true)) {
            $type = 'career_wish';
        }
        $targetLabel = trim((string) $request->input('target_label', ''));
        $motivation = trim((string) $request->input('motivation', ''));
        if ($targetLabel === '' && $motivation === '') {
            Session::flash('error', 'Indiquez au moins un poste ou une motivation.');

            return $this->rhFormRedirect($request, 'mobilite');
        }
        $id = $this->mobilityRequests->create(
            $tenantId,
            $userId,
            $type,
            null,
            null,
            $targetLabel !== '' ? $targetLabel : null,
            $motivation !== '' ? mb_substr($motivation, 0, 2000) : null,
            $userId
        );
        Session::flash($id > 0 ? 'success' : 'error', $id > 0
            ? 'Votre demande a été transmise à l’encadrement.'
            : 'La demande n’a pas pu être enregistrée.');

        return $this->rhFormRedirect($request, 'mobilite', $id > 0 ? 'mon-dossier-rh' : null);
    }

    public function storeElevation(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$user || $tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré. Rechargez la page puis réessayez.');

            return $this->rhFormRedirect($request, '');
        }

        $kind = trim((string) $request->input('elevation_kind', 'general'));
        $note = trim((string) $request->input('elevation_note', ''));
        $proposal = $this->readElevationProposalFromRequest($request);
        $validated = $this->validateElevationProposal($tenantId, $proposal);
        if ($validated['error'] !== null) {
            Session::flash('error', $validated['error']);

            return $this->rhFormRedirect($request, '');
        }
        $proposal = $validated['proposal'];
        $hasChange = ($proposal['grade_id'] ?? null) !== null
            || ($proposal['role_id'] ?? null) !== null
            || ($proposal['job_role_id'] ?? null) !== null
            || ($proposal['unit_id'] ?? null) !== null
            || ($proposal['clearance_level'] ?? null) !== null
            || $note !== '';
        if (!$hasChange) {
            Session::flash('error', 'Indiquez au moins un changement ou un message pour l’encadrement.');

            return $this->rhFormRedirect($request, '');
        }

        $alerts = \App\Core\Container::get(\App\Services\Effectifs\EffectifsStaffAlertService::class);
        $result = $alerts->requestElevation($tenantId, $userId, $user, $kind, $note, $proposal);
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return $this->rhFormRedirect($request, '', $result['ok'] ? 'mon-dossier-rh' : null);
    }

    public function storeAbsence(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$user || $tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré. Rechargez la page puis réessayez.');

            return $this->rhFormRedirect($request, 'absences');
        }
        if (!$this->personnelAbsenceRepository->tableExists()) {
            Session::flash('error', 'L’enregistrement des absences n’est pas encore disponible. Contactez l’encadrement si le problème persiste.');

            return $this->rhFormRedirect($request, 'absences');
        }

        $startsOn = trim((string) $request->input('starts_on', ''));
        $hasDuration = (string) $request->input('has_duration', '0') === '1';
        $endsOn = $hasDuration ? trim((string) $request->input('ends_on', '')) : null;
        if ($endsOn === '') {
            $endsOn = null;
        }
        $reason = trim((string) $request->input('reason', PersonnelAbsenceRepository::REASON_AUTRE));
        $note = trim((string) $request->input('note', ''));

        if ($startsOn === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startsOn)) {
            Session::flash('error', 'Indiquez une date de début valide.');

            return $this->rhFormRedirect($request, 'absences');
        }
        if ($hasDuration && ($endsOn === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endsOn))) {
            Session::flash('error', 'Indiquez une date de fin, ou choisissez une absence sans durée précisée.');

            return $this->rhFormRedirect($request, 'absences');
        }
        if ($endsOn !== null && $endsOn < $startsOn) {
            Session::flash('error', 'La date de fin doit être postérieure ou égale à la date de début.');

            return $this->rhFormRedirect($request, 'absences');
        }

        $id = $this->personnelAbsenceRepository->create(
            $tenantId,
            $userId,
            $startsOn,
            $endsOn,
            $reason,
            $note !== '' ? $note : null,
            $userId
        );
        if ($id === null) {
            Session::flash('error', 'L’absence n’a pas pu être enregistrée. Vérifiez les dates puis réessayez.');

            return $this->rhFormRedirect($request, 'absences');
        }

        Session::flash('success', $endsOn === null
            ? 'Absence enregistrée sans durée précisée. Vous pourrez l’interrompre quand vous serez de retour.'
            : 'Absence enregistrée pour la période indiquée.');

        return $this->rhFormRedirect($request, 'absences', 'mon-dossier-rh');
    }

    public function cancelAbsence(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$user || $tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré. Rechargez la page puis réessayez.');

            return $this->rhFormRedirect($request, 'absences');
        }
        $absenceId = (int) $request->input('absence_id', 0);
        if ($absenceId < 1 || !$this->personnelAbsenceRepository->cancel($tenantId, $userId, $absenceId)) {
            Session::flash('error', 'Cette absence n’a pas pu être annulée. Elle est peut-être déjà clôturée.');

            return $this->rhFormRedirect($request, 'absences');
        }

        Session::flash('success', 'Absence annulée. Vous êtes de nouveau indiqué comme disponible.');

        return $this->rhFormRedirect($request, 'absences', 'mon-dossier-rh');
    }

    public function refreshFromDossier(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$user || $tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré. Rechargez la page puis réessayez.');

            return Response::redirect(url('personnel/mon-espace-rh'));
        }
        try {
            $this->personnelAssignmentRepository->syncMissingFromUserUnitsWhenPossible($userId);
            $this->seniorityEnrollmentBootstrapService->syncTenureCommunityFromEnrollment($tenantId, $userId, null, false);
            \App\Core\Container::get(\App\Services\Personnel\SeniorityPrePlatformService::class)
                ->applyStoredOrgFoundingToUser($tenantId, $userId);
            $this->seniorityDossierInferenceSyncService->syncForUser($tenantId, $userId, false);
            Session::flash('success', 'Vos indicateurs ont été mis à jour à partir des informations de votre dossier.');
        } catch (\Throwable) {
            Session::flash('error', 'La mise à jour n’a pas pu aboutir. Réessayez dans quelques instants ou contactez l’encadrement si le problème persiste.');
        }

        return Response::redirect(url('personnel/mon-espace-rh'));
    }

    public function downloadHrDocument(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$user || $tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->hrDocuments->findById($id, $tenantId) : null;
        if (
            $row === null
            || (int) ($row['user_id'] ?? 0) !== $userId
            || ($row['visibility'] ?? '') !== 'MEMBER'
            || !PersonnelHrDocumentStorage::isStoredPath((string) ($row['file_path'] ?? ''))
        ) {
            Session::flash('error', 'Cette pièce n’est pas disponible.');

            return Response::redirect(url('personnel/mon-espace-rh'));
        }

        return PersonnelHrDocumentStorage::downloadResponse($row);
    }

    private function accessRuleLabel(string $ruleType): string
    {
        return match ($ruleType) {
            'allow_community' => 'Accès proposé dans le cadre de votre programme',
            'deny_community' => 'Restriction liée à votre programme',
            default => 'Règle associée à votre programme',
        };
    }

    private function rhFormRedirect(Request $request, string $workspaceHash, ?string $forceDashboardStep = null): Response
    {
        if (trim((string) $request->input('return_to', '')) === 'dashboard') {
            $step = $forceDashboardStep ?? trim((string) $request->input('return_step', 'mon-dossier-rh'));
            if (!in_array($step, ['absence', 'elevation', 'avancement', 'mon-dossier-rh'], true)) {
                $step = 'mon-dossier-rh';
            }

            return Response::redirect(url('dashboard') . '#' . $step);
        }
        $hash = $workspaceHash !== '' ? '#' . ltrim($workspaceHash, '#') : '';

        return Response::redirect(url('personnel/mon-espace-rh') . $hash);
    }

    /**
     * @return array{grade_id:?int,role_id:?int,job_role_id:?int,unit_id:?int,clearance_level:?string,role_apply_mode:string}
     */
    private function readElevationProposalFromRequest(Request $request): array
    {
        $intOrNull = static function (mixed $raw): ?int {
            if ($raw === null || $raw === '') {
                return null;
            }
            $id = (int) $raw;

            return $id > 0 ? $id : null;
        };
        $clearance = trim((string) $request->input('proposed_clearance_level', $request->input('elevation_clearance_level', '')));

        return [
            'grade_id' => $intOrNull($request->input('proposed_grade_id', $request->input('elevation_grade_id'))),
            'role_id' => $intOrNull($request->input('proposed_role_id', $request->input('elevation_role_id'))),
            'job_role_id' => $intOrNull($request->input('proposed_job_role_id', $request->input('elevation_job_role_id'))),
            'unit_id' => $intOrNull($request->input('proposed_unit_id', $request->input('elevation_unit_id'))),
            'clearance_level' => $clearance !== '' ? $clearance : null,
            'role_apply_mode' => \App\Services\Effectifs\ElevationApprovalService::normalizeRoleApplyMode(
                (string) $request->input('role_apply_mode', \App\Services\Effectifs\ElevationApprovalService::ROLE_APPLY_REPLACE)
            ),
        ];
    }

    /**
     * @param array{grade_id:?int,role_id:?int,job_role_id:?int,unit_id:?int,clearance_level?:?string,role_apply_mode?:string} $proposal
     * @return array{proposal: array{grade_id:?int,role_id:?int,job_role_id:?int,unit_id:?int,clearance_level:?string,role_apply_mode:string}, error:?string}
     */
    private function validateElevationProposal(int $tenantId, array $proposal): array
    {
        $proposal['clearance_level'] = $proposal['clearance_level'] ?? null;
        $proposal['role_apply_mode'] = \App\Services\Effectifs\ElevationApprovalService::normalizeRoleApplyMode(
            isset($proposal['role_apply_mode']) ? (string) $proposal['role_apply_mode'] : \App\Services\Effectifs\ElevationApprovalService::ROLE_APPLY_REPLACE
        );
        $gradeId = $proposal['grade_id'] ?? null;
        if ($gradeId !== null) {
            $allowed = array_map(
                static fn (array $g): int => (int) ($g['id'] ?? 0),
                \App\Core\Container::get(\App\Repositories\GradeRepository::class)->listForTenant($tenantId)
            );
            if (!in_array($gradeId, $allowed, true)) {
                return ['proposal' => $proposal, 'error' => 'Le grade sélectionné n’est pas disponible pour cette communauté.'];
            }
        }

        $roleId = $proposal['role_id'] ?? null;
        if ($roleId !== null && !\App\Core\Container::get(\App\Repositories\RoleRepository::class)->canAssignInTenantAdminContext($roleId, $tenantId)) {
            return ['proposal' => $proposal, 'error' => 'Ce rôle ne peut pas être demandé dans cette communauté.'];
        }

        $jobRoleId = $proposal['job_role_id'] ?? null;
        if ($jobRoleId !== null) {
            $jobRepo = \App\Core\Container::get(\App\Repositories\PersonnelJobRoleRepository::class);
            if (!$jobRepo->tablesExist() || !$jobRepo->findRoleById($jobRoleId, $tenantId)) {
                return ['proposal' => $proposal, 'error' => 'La fonction sélectionnée est introuvable.'];
            }
        }

        $unitId = $proposal['unit_id'] ?? null;
        if ($unitId !== null && !\App\Core\Container::get(\App\Repositories\UnitRepository::class)->findById($unitId, $tenantId)) {
            return ['proposal' => $proposal, 'error' => 'L’affectation sélectionnée est introuvable.'];
        }

        $clearanceLevel = $proposal['clearance_level'] ?? null;
        if ($clearanceLevel !== null
            && !array_key_exists($clearanceLevel, \App\Services\Documents\DocumentAccessService::getClassificationLevelLabels())) {
            return ['proposal' => $proposal, 'error' => 'Le niveau d’habilitation sélectionné n’est pas reconnu.'];
        }

        return ['proposal' => $proposal, 'error' => null];
    }
}
