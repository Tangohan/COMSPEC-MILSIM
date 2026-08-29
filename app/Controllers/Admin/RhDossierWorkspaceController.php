<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ElevationRequestRepository;
use App\Repositories\PersonnelHrDocumentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelMobilityRequestRepository;
use App\Repositories\PersonnelQualificationRepository;
use App\Repositories\PersonnelSuccessionRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Effectifs\RhAlertAggregatorService;
use App\Services\Personnel\PersonnelDuplicateDetectionService;
use App\Support\EffectifsLmsAccess;

/**
 * Dossier RH individuel : documents, mobilité, vivier, alertes agrégées.
 * Partage le shell LMS du bureau effectifs.
 */
final class RhDossierWorkspaceController
{
    public function __construct(
        private UserRepository $userRepository,
        private UnitRepository $unitRepository,
        private PersonnelJobRoleRepository $jobRoles,
        private PersonnelHrDocumentRepository $hrDocuments,
        private PersonnelMobilityRequestRepository $mobility,
        private PersonnelSuccessionRepository $succession,
        private RhAlertAggregatorService $rhAlerts,
        private ?ElevationRequestRepository $elevationRequests = null,
        private ?PersonnelQualificationRepository $qualifications = null,
        private ?PersonnelDuplicateDetectionService $duplicateDetection = null,
    ) {
        $this->elevationRequests ??= new ElevationRequestRepository();
        $this->qualifications ??= new PersonnelQualificationRepository();
        $this->duplicateDetection ??= new PersonnelDuplicateDetectionService();
    }

    public function documents(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');

        return $this->shell('admin.effectifs_workspace.rh_documents', [
            'title' => 'Documents RH',
            'effectifsNav' => 'rh_documents',
            'hrDocuments' => $this->hrDocuments->tableExists()
                ? $this->hrDocuments->listRecentForTenant($tenantId, 60)
                : [],
            'hrDocumentsCount' => $this->hrDocuments->tableExists()
                ? $this->hrDocuments->countForTenant($tenantId)
                : 0,
            'hrDocTypeLabels' => PersonnelHrDocumentRepository::DOC_TYPE_LABELS,
            'hrSchemaReady' => $this->hrDocuments->tableExists(),
            'orgUsers' => $this->userRepository->listForTenant($tenantId, null, 'active', null, 200, 0, true),
            'csrfToken' => Csrf::token(),
            'canManage' => EffectifsLmsAccess::canEditProfiles(Gate::getInstance()),
        ]);
    }

    public function storeDocument(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        if (!EffectifsLmsAccess::canEditProfiles(Gate::getInstance())) {
            Session::flash('error', 'Vous n’êtes pas habilité à ajouter un document RH.');

            return Response::redirect(effectifs_workspace_url('documents-rh'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(effectifs_workspace_url('documents-rh'));
        }
        if (!$this->hrDocuments->tableExists()) {
            Session::flash('error', 'Schéma documents RH indisponible. Relancez les migrations.');

            return Response::redirect(effectifs_workspace_url('documents-rh'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) $request->input('user_id');
        $docType = trim((string) $request->input('doc_type', 'autre'));
        $title = trim((string) $request->input('title', ''));
        $description = trim((string) $request->input('description', ''));
        $filePath = trim((string) $request->input('file_path', ''));
        $visibility = trim((string) $request->input('visibility', 'STAFF')) === 'MEMBER' ? 'MEMBER' : 'STAFF';
        if ($userId < 1 || $this->userRepository->findById($userId, $tenantId) === null) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url('documents-rh'));
        }
        if (!in_array($docType, PersonnelHrDocumentRepository::DOC_TYPES, true)) {
            $docType = 'autre';
        }
        if ($title === '') {
            $title = PersonnelHrDocumentRepository::DOC_TYPE_LABELS[$docType] ?? 'Document RH';
        }
        $id = $this->hrDocuments->create(
            $tenantId,
            $userId,
            $docType,
            $title,
            $description !== '' ? $description : null,
            $filePath !== '' ? mb_substr($filePath, 0, 500) : null,
            $filePath !== '' ? basename($filePath) : null,
            $visibility,
            (int) Session::get('user_id')
        );
        Session::flash($id > 0 ? 'success' : 'error', $id > 0
            ? 'Document RH enregistré sur le dossier.'
            : 'Impossible d’enregistrer le document.');

        return Response::redirect(effectifs_workspace_url('documents-rh'));
    }

    public function mobility(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $status = trim((string) $request->query('statut', 'pending'));
        if ($status === 'all') {
            $statusFilter = null;
        } elseif (!in_array($status, PersonnelMobilityRequestRepository::STATUSES, true)) {
            $statusFilter = 'pending';
            $status = 'pending';
        } else {
            $statusFilter = $status;
        }

        return $this->shell('admin.effectifs_workspace.rh_mobility', [
            'title' => 'Mobilité interne',
            'effectifsNav' => 'rh_mobility',
            'mobilityRequests' => $this->mobility->tableExists()
                ? $this->mobility->listForTenant($tenantId, $statusFilter, 80)
                : [],
            'mobilityPendingCount' => $this->mobility->tableExists() ? $this->mobility->countPending($tenantId) : 0,
            'mobilityStatusFilter' => $status,
            'mobilityTypeLabels' => PersonnelMobilityRequestRepository::TYPE_LABELS,
            'mobilitySchemaReady' => $this->mobility->tableExists(),
            'orgUsers' => $this->userRepository->listForTenant($tenantId, null, 'active', null, 200, 0, true),
            'orgUnits' => $this->unitRepository->allForTenant($tenantId),
            'orgJobRoles' => $this->jobRoles->tablesExist()
                ? $this->jobRoles->listRoleOptionsForSelect($tenantId)
                : [],
            'csrfToken' => Csrf::token(),
            'canManage' => EffectifsLmsAccess::canManageAssignments(Gate::getInstance())
                || EffectifsLmsAccess::canManageStatus(Gate::getInstance()),
        ]);
    }

    public function storeMobility(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canManageAssignments($gate) && !EffectifsLmsAccess::canManageStatus($gate)) {
            Session::flash('error', 'Vous n’êtes pas habilité à enregistrer une mobilité.');

            return Response::redirect(effectifs_workspace_url('mobilite'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(effectifs_workspace_url('mobilite'));
        }
        if (!$this->mobility->tableExists()) {
            Session::flash('error', 'Schéma mobilité indisponible. Relancez les migrations.');

            return Response::redirect(effectifs_workspace_url('mobilite'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) $request->input('user_id');
        $type = trim((string) $request->input('request_type', 'career_wish'));
        $targetUnitId = (int) $request->input('target_unit_id', 0);
        $targetJobRoleId = (int) $request->input('target_job_role_id', 0);
        $targetLabel = trim((string) $request->input('target_label', ''));
        $motivation = trim((string) $request->input('motivation', ''));
        if ($userId < 1 || $this->userRepository->findById($userId, $tenantId) === null) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url('mobilite'));
        }
        if (!in_array($type, PersonnelMobilityRequestRepository::TYPES, true)) {
            $type = 'career_wish';
        }
        $id = $this->mobility->create(
            $tenantId,
            $userId,
            $type,
            $targetUnitId > 0 ? $targetUnitId : null,
            $targetJobRoleId > 0 ? $targetJobRoleId : null,
            $targetLabel !== '' ? $targetLabel : null,
            $motivation !== '' ? mb_substr($motivation, 0, 2000) : null,
            (int) Session::get('user_id')
        );
        Session::flash($id > 0 ? 'success' : 'error', $id > 0
            ? 'Demande de mobilité enregistrée.'
            : 'Impossible d’enregistrer la demande.');

        return Response::redirect(effectifs_workspace_url('mobilite'));
    }

    public function resolveMobility(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canManageAssignments($gate) && !EffectifsLmsAccess::canManageStatus($gate)) {
            Session::flash('error', 'Vous n’êtes pas habilité à traiter une mobilité.');

            return Response::redirect(effectifs_workspace_url('mobilite'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(effectifs_workspace_url('mobilite'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? $request->input('id', 0));
        $status = trim((string) $request->input('status', ''));
        $note = trim((string) $request->input('resolution_note', ''));
        if (!in_array($status, ['approved', 'rejected', 'cancelled', 'applied'], true)) {
            Session::flash('error', 'Statut non reconnu.');

            return Response::redirect(effectifs_workspace_url('mobilite'));
        }
        $ok = $this->mobility->resolve(
            $id,
            $tenantId,
            $status,
            (int) Session::get('user_id'),
            $note !== '' ? mb_substr($note, 0, 500) : null
        );
        Session::flash($ok ? 'success' : 'error', $ok
            ? 'Demande de mobilité mise à jour.'
            : 'Demande introuvable ou déjà traitée.');

        return Response::redirect(effectifs_workspace_url('mobilite'));
    }

    public function succession(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $readiness = trim((string) $request->query('readiness', ''));
        if ($readiness !== '' && !in_array($readiness, PersonnelSuccessionRepository::READINESS, true)) {
            $readiness = '';
        }

        return $this->shell('admin.effectifs_workspace.rh_succession', [
            'title' => 'Succession et vivier',
            'effectifsNav' => 'rh_succession',
            'successionEntries' => $this->succession->tableExists()
                ? $this->succession->listActiveForTenant(
                    $tenantId,
                    $readiness !== '' ? $readiness : null,
                    120
                )
                : [],
            'successionCounts' => $this->succession->tableExists()
                ? $this->succession->countsByReadiness($tenantId)
                : ['ready_now' => 0, 'ready_3m' => 0, 'develop' => 0, 'total' => 0],
            'successionReadinessFilter' => $readiness,
            'successionReadinessLabels' => PersonnelSuccessionRepository::READINESS_LABELS,
            'successionDefaultRoles' => PersonnelSuccessionRepository::DEFAULT_ROLE_LABELS,
            'successionSchemaReady' => $this->succession->tableExists(),
            'orgUsers' => $this->userRepository->listForTenant($tenantId, null, 'active', null, 200, 0, true),
            'csrfToken' => Csrf::token(),
            'canManage' => EffectifsLmsAccess::canManageGrades(Gate::getInstance())
                || EffectifsLmsAccess::canManageRoles(Gate::getInstance())
                || EffectifsLmsAccess::canManageStatus(Gate::getInstance()),
        ]);
    }

    public function storeSuccession(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $gate = Gate::getInstance();
        if (
            !EffectifsLmsAccess::canManageGrades($gate)
            && !EffectifsLmsAccess::canManageRoles($gate)
            && !EffectifsLmsAccess::canManageStatus($gate)
        ) {
            Session::flash('error', 'Vous n’êtes pas habilité à gérer le vivier.');

            return Response::redirect(effectifs_workspace_url('vivier'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(effectifs_workspace_url('vivier'));
        }
        if (!$this->succession->tableExists()) {
            Session::flash('error', 'Schéma vivier indisponible. Relancez les migrations.');

            return Response::redirect(effectifs_workspace_url('vivier'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) $request->input('user_id');
        $roleLabel = trim((string) $request->input('target_role_label', ''));
        $readiness = trim((string) $request->input('readiness', 'develop'));
        $notes = trim((string) $request->input('notes', ''));
        if ($userId < 1 || $this->userRepository->findById($userId, $tenantId) === null) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url('vivier'));
        }
        if ($roleLabel === '') {
            Session::flash('error', 'Indiquez le poste cible.');

            return Response::redirect(effectifs_workspace_url('vivier'));
        }
        $id = $this->succession->upsert(
            $tenantId,
            $userId,
            $roleLabel,
            $readiness,
            $notes !== '' ? mb_substr($notes, 0, 2000) : null,
            (int) Session::get('user_id')
        );
        Session::flash($id > 0 ? 'success' : 'error', $id > 0
            ? 'Entrée de vivier enregistrée.'
            : 'Impossible d’enregistrer l’entrée.');

        return Response::redirect(effectifs_workspace_url('vivier'));
    }

    public function deactivateSuccession(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(effectifs_workspace_url('vivier'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? $request->input('id', 0));
        $ok = $this->succession->deactivate($id, $tenantId);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Entrée retirée du vivier.' : 'Entrée introuvable.');

        return Response::redirect(effectifs_workspace_url('vivier'));
    }

    public function alerts(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $summary = $this->rhAlerts->summarize($tenantId);

        return $this->shell('admin.effectifs_workspace.rh_alerts', [
            'title' => 'Alertes RH',
            'effectifsNav' => 'rh_alerts',
            'rhAlertSummary' => $summary,
            'rhInactiveMembers' => $this->rhAlerts->listInactiveMembers($tenantId),
            'rhProlongedAbsences' => $this->rhAlerts->listProlongedAbsences($tenantId),
            'rhInactivityDays' => RhAlertAggregatorService::INACTIVITY_DAYS,
            'rhProlongedAbsenceDays' => RhAlertAggregatorService::PROLONGED_ABSENCE_DAYS,
        ]);
    }

    private function denyUnlessAccess(): ?Response
    {
        if (!(int) Session::get('user_id') || !(int) Session::get('tenant_id')) {
            return Response::redirect(url('login'));
        }
        if (!EffectifsLmsAccess::allows(Gate::getInstance())) {
            Session::flash('error', 'Accès réservé au pilotage des effectifs.');

            return Response::redirect(url('dashboard'));
        }

        return null;
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function shell(string $content, array $extra): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $elevationOpen = 0;
        try {
            $elevationOpen = $this->elevationRequests->countOpenForTenant($tenantId);
        } catch (\Throwable) {
            $elevationOpen = 0;
        }
        $qualifExpiring = 0;
        try {
            $qualifExpiring = count($this->qualifications->listExpiringForTenant($tenantId, 60, 300));
        } catch (\Throwable) {
            $qualifExpiring = 0;
        }
        $dupScan = ['enabled' => false, 'fields' => [], 'groups' => [], 'group_count' => 0, 'member_count' => 0];
        try {
            $dupScan = $this->duplicateDetection->scan($tenantId);
        } catch (\Throwable) {
        }
        $mobilityPending = 0;
        try {
            $mobilityPending = $this->mobility->countPending($tenantId);
        } catch (\Throwable) {
        }
        $rhAlertTotal = 0;
        try {
            $rhAlertTotal = (int) ($this->rhAlerts->summarize($tenantId)['total'] ?? 0);
        } catch (\Throwable) {
        }

        return Response::view('layout.effectifs_lms', array_merge([
            'content' => $content,
            'showPortalFooter' => false,
            'rosterCounts' => [
                'total' => $this->userRepository->countListForTenant($tenantId, null, null, null, true),
                'active' => $this->userRepository->countListForTenant($tenantId, null, 'active', null, true),
                'inactive' => $this->userRepository->countListForTenant($tenantId, null, 'inactive', null, true),
                'pending' => $this->userRepository->countListForTenant($tenantId, null, 'pending_verification', null, true),
                'no_unit' => $this->userRepository->countListForTenant($tenantId, null, null, null, true, true, null),
                'no_role' => $this->userRepository->countListForTenant($tenantId, null, null, null, true, null, true),
            ],
            'elevationOpenCount' => $elevationOpen,
            'qualificationsExpiringCount' => $qualifExpiring,
            'personnelDuplicateScan' => $dupScan,
            'mobilityPendingCount' => $mobilityPending,
            'rhAlertTotalCount' => $rhAlertTotal,
            'viewerName' => (string) (Session::get('display_name') ?? Session::get('email') ?? ''),
        ], $extra));
    }
}
