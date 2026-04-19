<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\EnlistmentCannedMessageRepository;
use App\Repositories\EnlistmentRecruitmentEngagementRepository;
use App\Repositories\EnlistmentRepository;
use App\Repositories\EnlistmentTimelineRepository;
use App\Repositories\RecruitmentOpeningRepository;
use App\Repositories\RecruitmentTeamWallRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Analytics\AnalyticsEventCategory;
use App\Services\Analytics\AnalyticsEventName;
use App\Services\Analytics\AnalyticsEventService;
use App\Services\Analytics\AnalyticsSubjectType;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;
use App\Services\Recruitment\EnlistmentAcceptanceProvisioningService;
use App\Services\Recruitment\EnlistmentPortalAttachmentService;
use App\Services\Recruitment\EnlistmentPortalAutoModerationCoordinator;
use App\Services\Recruitment\TenantRecruitmentSettings;

class AdminRecruitmentsController
{
    public function __construct(
        private EnlistmentRepository $enlistmentRepository,
        private EnlistmentCannedMessageRepository $cannedMessageRepository,
        private EnlistmentTimelineRepository $enlistmentTimelineRepository,
        private EnlistmentAcceptanceProvisioningService $enlistmentAcceptanceProvisioningService,
        private TenantRepository $tenantRepository,
        private RecruitmentOpeningRepository $recruitmentOpeningRepository,
        private UserRepository $userRepository,
        private EmailService $emailService,
        private EnlistmentPortalAttachmentService $enlistmentPortalAttachmentService,
        private EnlistmentPortalAutoModerationCoordinator $portalAutoModerationCoordinator,
        private EnlistmentRecruitmentEngagementRepository $recruitmentEngagementRepository,
        private AnalyticsEventService $analyticsEventService,
        private RecruitmentTeamWallRepository $recruitmentTeamWallRepository,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $statusFilter = $request->query('status');
        $enlistments = $this->enlistmentRepository->allForTenant((int) $tenantId, $statusFilter ?: null);
        $enlistmentCounts = $this->enlistmentRepository->countsByStatusForTenant((int) $tenantId);
        $tenantSettings = $this->tenantRepository->getSettings((int) $tenantId);
        $slaHours = TenantRecruitmentSettings::enlistmentSlaHoursFromSettings($tenantSettings);
        $submittedOlderThanSla = 0;
        foreach ($enlistments as &$row) {
            $isSubmitted = ((string) ($row['status'] ?? '')) === 'submitted';
            $ageHours = $this->submittedAgeHours($row);
            $row['submitted_age_hours'] = $ageHours;
            $row['submitted_sla_breached'] = $isSubmitted && $ageHours !== null && $ageHours > $slaHours;
            if (!empty($row['submitted_sla_breached'])) {
                $submittedOlderThanSla++;
            }
        }
        unset($row);

        return Response::view('layout.recruitment_lms', [
            'content' => 'admin.recruitments.index',
            'title' => 'Candidatures',
            'recruitmentLmsTitle' => 'File des candidatures',
            'enlistments' => $enlistments,
            'statusFilter' => $statusFilter,
            'enlistmentCounts' => $enlistmentCounts,
            'recruitmentSidebarCounts' => $enlistmentCounts,
            'enlistmentSlaHours' => $slaHours,
            'submittedOlderThanSla' => $submittedOlderThanSla,
            'recruitmentAdminNav' => 'queue',
            'showPortalFooter' => false,
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            return Response::redirect(url('back-office/recruitments'));
        }
        $row = $this->enlistmentRepository->findForTenant((int) $tenantId, $id);
        if (!$row) {
            return Response::redirect(url('back-office/recruitments'));
        }

        $canned = $this->cannedMessageRepository->listForTenant((int) $tenantId);
        $tenantSettings = $this->tenantRepository->getSettings((int) $tenantId);
        $slaHours = TenantRecruitmentSettings::enlistmentSlaHoursFromSettings($tenantSettings);
        $row['submitted_age_hours'] = $this->submittedAgeHours($row);
        $row['submitted_sla_breached'] = ((string) ($row['status'] ?? '')) === 'submitted'
            && $row['submitted_age_hours'] !== null
            && $row['submitted_age_hours'] > $slaHours;

        $linkedOpening = null;
        $communitySlug = '';
        $trow = $this->tenantRepository->findById((int) $tenantId);
        if ($trow) {
            $communitySlug = trim((string) ($trow['slug'] ?? ''));
        }
        $roid = (int) ($row['recruitment_opening_id'] ?? 0);
        if ($roid > 0 && $this->recruitmentOpeningRepository->tablesExist()) {
            $orow = $this->recruitmentOpeningRepository->findByIdForTenant($roid, (int) $tenantId);
            if ($orow) {
                $linkedOpening = $orow;
            }
        }

        $this->enlistmentTimelineRepository->seedLegacyIfEmpty((int) $tenantId, $id, $row);
        $timelineRows = $this->enlistmentTimelineRepository->listForEnlistment((int) $tenantId, $id);
        $actorIds = [];
        foreach ($timelineRows as $tr) {
            $aid = (int) ($tr['actor_user_id'] ?? 0);
            if ($aid > 0) {
                $actorIds[] = $aid;
            }
        }
        $actorIds = array_values(array_unique($actorIds));
        $actorUsers = $actorIds !== [] ? $this->userRepository->findByIdsForTenant((int) $tenantId, $actorIds) : [];
        $timelineActorLabels = [];
        foreach ($actorUsers as $uid => $urow) {
            $lab = trim((string) ($urow['display_name'] ?? ''));
            if ($lab === '') {
                $lab = trim((string) ($urow['callsign'] ?? ''));
            }
            $timelineActorLabels[(int) $uid] = $lab !== '' ? $lab : ('Compte n°' . (int) $uid);
        }

        $navCounts = $this->enlistmentRepository->countsByStatusForTenant((int) $tenantId);
        $candidatePortalAttachments = $this->enlistmentRepository->listCandidatePortalAttachments((int) $tenantId, $id);

        $reviewedById = (int) ($row['reviewed_by'] ?? 0);
        $assigneeUserRow = $reviewedById > 0 ? $this->userRepository->findById($reviewedById, (int) $tenantId) : null;
        $assigneeDisplayName = $this->displayLabelForTenantUser($assigneeUserRow, $reviewedById);

        $recruiterPicksDisplay = [];
        $currentUserId = (int) Session::get('user_id');
        $userHasRecruiterPick = false;
        if ($this->recruitmentEngagementRepository->picksTableExists()) {
            $pickRows = $this->recruitmentEngagementRepository->listPicks((int) $tenantId, $id);
            $pickIds = [];
            foreach ($pickRows as $pr) {
                $pid = (int) ($pr['user_id'] ?? 0);
                if ($pid > 0) {
                    $pickIds[] = $pid;
                }
            }
            $pickIds = array_values(array_unique($pickIds));
            $pickUsers = $pickIds !== [] ? $this->userRepository->findByIdsForTenant((int) $tenantId, $pickIds) : [];
            foreach ($pickRows as $pr) {
                $pid = (int) ($pr['user_id'] ?? 0);
                if ($pid < 1) {
                    continue;
                }
                $recruiterPicksDisplay[] = [
                    'user_id' => $pid,
                    'label' => $this->displayLabelForTenantUser($pickUsers[$pid] ?? null, $pid),
                    'created_at' => (string) ($pr['created_at'] ?? ''),
                ];
            }
            $userHasRecruiterPick = $currentUserId > 0 && $this->recruitmentEngagementRepository->userHasPick((int) $tenantId, $id, $currentUserId);
        }

        $enlistmentAgeDays = $this->enlistmentAgeDays($row);
        $retroWindowEligible = $enlistmentAgeDays !== null && $enlistmentAgeDays >= 30;
        $engagementTablesReady = $this->recruitmentEngagementRepository->engagementReady();
        $staffRetroRow = $engagementTablesReady
            ? $this->recruitmentEngagementRepository->findRetro((int) $tenantId, $id, EnlistmentRecruitmentEngagementRepository::SCOPE_STAFF_ONE_MONTH)
            : null;
        $candidateRetroRow = $engagementTablesReady
            ? $this->recruitmentEngagementRepository->findRetro((int) $tenantId, $id, EnlistmentRecruitmentEngagementRepository::SCOPE_CANDIDATE_RETURN)
            : null;

        $enlistmentAnalyticsRecent = $this->analyticsEventService->listRecentForEnlistment((int) $tenantId, $id, 30);

        $portalAccess = $this->enlistmentRepository->findActiveCandidatePortalAccessRow((int) $tenantId, $id);
        $candidatePortalSuiviUrl = $portalAccess !== null
            ? url('enlistment/suivi/' . rawurlencode($portalAccess['access_token']))
            : null;
        $candidatePortalSuiviExpiresFmt = null;
        if ($portalAccess !== null) {
            $expRaw = trim((string) ($portalAccess['expires_at'] ?? ''));
            if ($expRaw !== '') {
                $candidatePortalSuiviExpiresFmt = date('d/m/Y à H:i', strtotime($expRaw) ?: time());
            }
        }

        try {
            $this->analyticsEventService->record(
                (int) $tenantId,
                $currentUserId > 0 ? $currentUserId : null,
                AnalyticsEventCategory::RECRUITMENT,
                AnalyticsEventName::ENLISTMENT_BACKOFFICE_VIEW,
                AnalyticsSubjectType::ENLISTMENT,
                $id,
                null,
                ['status' => (string) ($row['status'] ?? '')],
            );
        } catch (\Throwable) {
        }

        return Response::view('layout.recruitment_lms', [
            'content' => 'admin.recruitments.show',
            'title' => 'Candidature #' . $id,
            'recruitmentLmsTitle' => 'Dossier #' . $id,
            'enlistment' => $row,
            'enlistmentCannedMessages' => $canned,
            'enlistmentTimeline' => $timelineRows,
            'enlistmentTimelineActorLabels' => $timelineActorLabels,
            'enlistmentTimelineTableMissing' => !$this->enlistmentTimelineRepository->tableExists(),
            'membershipRepairHint' => $this->enlistmentAcceptanceProvisioningService->membershipRepairHint((int) $tenantId, $row),
            'linkedRecruitmentOpening' => $linkedOpening,
            'communitySlug' => $communitySlug,
            'enlistmentSlaHours' => $slaHours,
            'recruitmentSidebarCounts' => $navCounts,
            'recruitmentAdminNav' => 'queue',
            'showPortalFooter' => false,
            'candidatePortalAttachments' => $candidatePortalAttachments,
            'candidatePortalUploadsReady' => $this->enlistmentRepository->candidatePortalUploadsReady(),
            'assigneeDisplayName' => $assigneeDisplayName,
            'recruiterPicksDisplay' => $recruiterPicksDisplay,
            'userHasRecruiterPick' => $userHasRecruiterPick,
            'currentStaffUserId' => $currentUserId,
            'enlistmentAgeDays' => $enlistmentAgeDays,
            'retroWindowEligible' => $retroWindowEligible,
            'enlistmentEngagementTablesReady' => $engagementTablesReady,
            'recruiterPicksTableReady' => $this->recruitmentEngagementRepository->picksTableExists(),
            'staffRetroFeedback' => $staffRetroRow,
            'candidateRetroFeedback' => $candidateRetroRow,
            'enlistmentAnalyticsRecent' => $enlistmentAnalyticsRecent,
            'candidatePortalSuiviUrl' => $candidatePortalSuiviUrl,
            'candidatePortalSuiviExpiresFmt' => $candidatePortalSuiviExpiresFmt,
        ]);
    }

    public function recruiterPick(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId || !$request->isPost()) {
            return Response::redirect(url('back-office/recruitments'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/recruitments'));
        }
        $id = (int) ($params['id'] ?? 0);
        $userId = (int) Session::get('user_id');
        if ($id < 1 || $userId < 1) {
            Session::flash('error', 'Action impossible.');

            return Response::redirect(url('back-office/recruitments'));
        }
        $row = $this->enlistmentRepository->findForTenant((int) $tenantId, $id);
        if (!$row) {
            return Response::redirect(url('back-office/recruitments'));
        }
        if ((string) ($row['status'] ?? '') !== 'submitted') {
            Session::flash('error', 'Ce dossier n’est plus en file d’instruction : le volontariat n’est plus proposé ici.');

            return Response::redirect(url('back-office/recruitments/' . $id));
        }
        if (!$this->recruitmentEngagementRepository->picksTableExists()) {
            Session::flash('error', 'Cette fonctionnalité nécessite une mise à jour de la base de données (migration).');

            return Response::redirect(url('back-office/recruitments/' . $id));
        }
        if ($this->recruitmentEngagementRepository->userHasPick((int) $tenantId, $id, $userId)) {
            Session::flash('success', 'Vous aviez déjà signalé votre intérêt pour ce dossier.');

            return Response::redirect(url('back-office/recruitments/' . $id));
        }
        $added = $this->recruitmentEngagementRepository->addPick((int) $tenantId, $id, $userId);
        if (!$added) {
            Session::flash('error', 'Impossible d’enregistrer votre volontariat pour le moment.');

            return Response::redirect(url('back-office/recruitments/' . $id));
        }
        if ($this->enlistmentTimelineRepository->tableExists()) {
            $this->enlistmentTimelineRepository->append(
                (int) $tenantId,
                $id,
                'staff_note',
                'reception',
                'Volontariat pour suivre ce dossier',
                null,
                $userId,
                null
            );
        }
        try {
            $this->analyticsEventService->record(
                (int) $tenantId,
                $userId,
                AnalyticsEventCategory::RECRUITMENT,
                AnalyticsEventName::ENLISTMENT_RECRUITER_PICK,
                AnalyticsSubjectType::ENLISTMENT,
                $id,
                null,
                null,
            );
        } catch (\Throwable) {
        }
        Session::flash('success', 'Votre intérêt a été enregistré. L’équipe le voit sur la fiche du dossier.');

        return Response::redirect(url('back-office/recruitments/' . $id));
    }

    public function staffRetroSave(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId || !$request->isPost()) {
            return Response::redirect(url('back-office/recruitments'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/recruitments'));
        }
        $id = (int) ($params['id'] ?? 0);
        $userId = (int) Session::get('user_id');
        if ($id < 1 || $userId < 1) {
            Session::flash('error', 'Action impossible.');

            return Response::redirect(url('back-office/recruitments'));
        }
        $row = $this->enlistmentRepository->findForTenant((int) $tenantId, $id);
        if (!$row) {
            return Response::redirect(url('back-office/recruitments'));
        }
        if (!$this->recruitmentEngagementRepository->retroTableExists()) {
            Session::flash('error', 'Cette fonctionnalité nécessite une mise à jour de la base de données (migration).');

            return Response::redirect(url('back-office/recruitments/' . $id));
        }
        $ageDays = $this->enlistmentAgeDays($row);
        if ($ageDays === null || $ageDays < 30) {
            Session::flash('error', 'Le bilan équipe n’est proposé qu’à partir de 30 jours après la réception du dossier.');

            return Response::redirect(url('back-office/recruitments/' . $id));
        }
        $rating = (int) $request->input('retro_staff_rating', 0);
        if ($rating < 1 || $rating > 5) {
            Session::flash('error', 'Choisissez une note de 1 à 5.');

            return Response::redirect(url('back-office/recruitments/' . $id));
        }
        $comment = trim((string) $request->input('retro_staff_comment', ''));
        if ($comment === '') {
            Session::flash('error', 'Ajoutez un court commentaire pour expliquer votre note.');

            return Response::redirect(url('back-office/recruitments/' . $id));
        }
        $trow = $this->tenantRepository->findById((int) $tenantId);
        $tenantName = trim((string) ((is_array($trow) ? $trow : [])['name'] ?? ''));
        if ($tenantName === '') {
            $tenantName = 'Communauté';
        }
        $hit = $this->portalAutoModerationCoordinator->scan($comment);
        if ($hit !== null) {
            $this->portalAutoModerationCoordinator->enforceAfterStaffViolation((int) $tenantId, $tenantName, $row, $userId, $hit, $comment);
            Session::flash('error', 'Ce texte ne peut pas être enregistré : le filtre automatique du portail l’a refusé. Une alerte a été transmise.');

            return Response::redirect(url('back-office/recruitments/' . $id));
        }
        if (!$this->recruitmentEngagementRepository->upsertStaffRetro((int) $tenantId, $id, $userId, $rating, $comment)) {
            Session::flash('error', 'Impossible d’enregistrer le bilan pour le moment.');

            return Response::redirect(url('back-office/recruitments/' . $id));
        }
        if ($this->enlistmentTimelineRepository->tableExists()) {
            $this->enlistmentTimelineRepository->append(
                (int) $tenantId,
                $id,
                'staff_note',
                'general',
                'Bilan de recrutement (équipe, après 30 jours)',
                'Note : ' . $rating . " / 5\n\n" . $comment,
                $userId,
                ['retro' => 'staff_one_month']
            );
        }
        try {
            $this->analyticsEventService->record(
                (int) $tenantId,
                $userId,
                AnalyticsEventCategory::RECRUITMENT,
                AnalyticsEventName::ENLISTMENT_STAFF_RETRO_SUBMIT,
                AnalyticsSubjectType::ENLISTMENT,
                $id,
                null,
                ['rating' => $rating],
            );
        } catch (\Throwable) {
        }
        Session::flash('success', 'Bilan enregistré. Merci pour ce retour, il aide l’équipe à ajuster le processus.');

        return Response::redirect(url('back-office/recruitments/' . $id));
    }

    public function portalOptionsSave(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId || !$request->isPost()) {
            return Response::redirect(url('back-office/recruitments'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/recruitments'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            return Response::redirect(url('back-office/recruitments'));
        }
        $row = $this->enlistmentRepository->findForTenant((int) $tenantId, $id);
        if (!$row) {
            return Response::redirect(url('back-office/recruitments'));
        }
        if (!$this->enlistmentRepository->candidatePortalUploadsReady()) {
            Session::flash('error', 'Les options du portail candidat ne sont pas encore disponibles sur cette installation (migration à exécuter).');

            return Response::redirect(url('back-office/recruitments/' . $id));
        }
        $allowFiles = (string) $request->input('candidate_portal_allow_files', '0') === '1';
        $allowAudio = (string) $request->input('candidate_portal_allow_audio', '0') === '1';
        $prevFiles = !empty($row['candidate_portal_allow_files']);
        $prevAudio = !empty($row['candidate_portal_allow_audio']);
        $ok = $this->enlistmentRepository->updateCandidatePortalOptions((int) $tenantId, $id, $allowFiles, $allowAudio);
        if ($ok && $this->enlistmentTimelineRepository->tableExists()
            && ($prevFiles !== $allowFiles || $prevAudio !== $allowAudio)) {
            $actorId = (int) Session::get('user_id');
            $lines = [
                'Pièces jointes (envoi depuis le portail) : ' . ($allowFiles ? 'autorisées' : 'désactivées') . '.',
                'Messages audio : ' . ($allowAudio ? 'autorisés' : 'désactivés') . '.',
            ];
            $this->enlistmentTimelineRepository->append(
                (int) $tenantId,
                $id,
                'system',
                'portal',
                'Options du portail candidat mises à jour',
                implode("\n", $lines),
                $actorId > 0 ? $actorId : null,
                ['timeline_family' => 'portal_options'],
                null
            );
        }
        Session::flash($ok ? 'success' : 'error', $ok ? 'Options du portail candidat enregistrées.' : 'Impossible d’enregistrer les options.');

        return Response::redirect(url('back-office/recruitments/' . $id));
    }

    public function portalAttachmentDownload(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $enlistmentId = (int) ($params['id'] ?? 0);
        $attachmentId = (int) ($params['attachmentId'] ?? 0);
        if ($enlistmentId < 1 || $attachmentId < 1) {
            return Response::redirect(url('back-office/recruitments'));
        }
        $row = $this->enlistmentRepository->findForTenant((int) $tenantId, $enlistmentId);
        if (!$row) {
            return Response::redirect(url('back-office/recruitments'));
        }
        $att = $this->enlistmentRepository->findCandidatePortalAttachment((int) $tenantId, $enlistmentId, $attachmentId);
        if (!$att) {
            Session::flash('error', 'Pièce jointe introuvable.');

            return Response::redirect(url('back-office/recruitments/' . $enlistmentId));
        }
        $path = $this->enlistmentPortalAttachmentService->absolutePathForStorage((string) ($att['storage_path'] ?? ''));
        if ($path === '' || !is_file($path)) {
            Session::flash('error', 'Fichier introuvable sur le serveur.');

            return Response::redirect(url('back-office/recruitments/' . $enlistmentId));
        }
        $mime = trim((string) ($att['mime'] ?? ''));
        if ($mime === '') {
            $mime = 'application/octet-stream';
        }
        $name = trim((string) ($att['original_name'] ?? 'piece-jointe'));
        $safeName = str_replace(['"', "\r", "\n"], '', $name);
        $inline = (string) $request->query('inline', '') === '1';
        $disp = $inline
            ? 'inline; filename="' . $safeName . '"'
            : 'attachment; filename="' . $safeName . '"';

        $response = new Response();
        $response->setStatusCode(200)
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', $disp)
            ->setBodyStream(static function () use ($path): void {
                $h = fopen($path, 'rb');
                if ($h !== false) {
                    fpassthru($h);
                    fclose($h);
                }
            });

        return $response;
    }

    public function timelineComment(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId || !$request->isPost()) {
            return Response::redirect(url('back-office/recruitments'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/recruitments'));
        }
        $id = (int) ($params['id'] ?? 0);
        $userId = (int) Session::get('user_id');
        if ($id < 1 || $userId < 1) {
            Session::flash('error', 'Action impossible.');

            return Response::redirect(url('back-office/recruitments'));
        }
        if (!$this->enlistmentTimelineRepository->tableExists()) {
            Session::flash('error', 'Le journal des dossiers n’est pas encore disponible sur cette installation (migration à exécuter).');

            return Response::redirect(url('back-office/recruitments/' . $id));
        }
        $row = $this->enlistmentRepository->findForTenant((int) $tenantId, $id);
        if (!$row) {
            return Response::redirect(url('back-office/recruitments'));
        }
        $step = trim((string) $request->input('timeline_step', 'general'));
        $allowedSteps = ['reception', 'instruction', 'decision', 'adhesion', 'general'];
        if (!in_array($step, $allowedSteps, true)) {
            $step = 'general';
        }
        $body = trim((string) $request->input('timeline_body', ''));
        if (mb_strlen($body) < 1) {
            Session::flash('error', 'Saisissez le texte du commentaire.');

            return Response::redirect(url('back-office/recruitments/' . $id));
        }
        if (mb_strlen($body) > 8000) {
            Session::flash('error', 'Commentaire trop long (8 000 caractères maximum).');

            return Response::redirect(url('back-office/recruitments/' . $id));
        }
        $this->enlistmentTimelineRepository->append(
            (int) $tenantId,
            $id,
            'staff_note',
            $step,
            null,
            $body,
            $userId,
            null,
            null
        );
        Session::flash('success', 'Commentaire ajouté au journal du dossier.');

        return Response::redirect(url('back-office/recruitments/' . $id));
    }

    public function settingsSave(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId || !$request->isPost()) {
            return Response::redirect(url('back-office/recruitments'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/recruitments'));
        }
        $slaHours = (int) $request->input('enlistment_sla_hours', TenantRecruitmentSettings::defaultEnlistmentSlaHours());
        $slaHours = max(1, min(720, $slaHours));
        $this->tenantRepository->updateSettings((int) $tenantId, [
            'recruitment' => [
                'enlistment_sla_hours' => $slaHours,
            ],
        ]);
        Session::flash('success', 'Délai d’alerte enregistré : ' . $slaHours . ' h sans traitement.');

        return Response::redirect(url('back-office/recruitments'));
    }

    public function settings(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $tenantSettings = $this->tenantRepository->getSettings((int) $tenantId);
        $slaHours = TenantRecruitmentSettings::enlistmentSlaHoursFromSettings($tenantSettings);
        $submitted = $this->enlistmentRepository->allForTenant((int) $tenantId, 'submitted');
        $breached = 0;
        foreach ($submitted as $row) {
            $age = $this->submittedAgeHours($row);
            if ($age !== null && $age > $slaHours) {
                $breached++;
            }
        }

        $navCounts = $this->enlistmentRepository->countsByStatusForTenant((int) $tenantId);

        return Response::view('layout.recruitment_lms', [
            'content' => 'admin.recruitments.settings',
            'title' => 'Délais d’alerte recrutement',
            'recruitmentLmsTitle' => 'Délais (SLA)',
            'enlistmentSlaHours' => $slaHours,
            'submittedCount' => count($submitted),
            'submittedOlderThanSla' => $breached,
            'recruitmentSidebarCounts' => $navCounts,
            'recruitmentAdminNav' => 'sla',
            'showPortalFooter' => false,
        ]);
    }

    public function finalizeMembership(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId || !$request->isPost()) {
            return Response::redirect(url('back-office/recruitments'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/recruitments'));
        }
        $id = (int) ($params['id'] ?? 0);
        $actorId = (int) Session::get('user_id');
        if ($id < 1 || $actorId < 1) {
            Session::flash('error', 'Action impossible.');

            return Response::redirect(url('back-office/recruitments'));
        }

        $result = $this->enlistmentAcceptanceProvisioningService->repairAcceptedMembership((int) $tenantId, $id, $actorId);
        if (!$result['ok']) {
            Session::flash('error', $result['message'] ?? 'Finalisation impossible.');
        } else {
            $extra = trim((string) ($result['message'] ?? ''));
            Session::flash(
                'success',
                $extra !== ''
                    ? 'Adhésion mise à jour. ' . $extra
                    : 'Adhésion finalisée : le compte est bien rattaché comme membre de la communauté.'
            );
            $this->enlistmentTimelineRepository->logAdhesionStep(
                (int) $tenantId,
                $id,
                $actorId,
                'Finalisation manuelle du rattachement',
                $extra !== '' ? $extra : 'Action « forcer le rattachement » enregistrée pour ce dossier.'
            );
        }

        return Response::redirect(url('back-office/recruitments/' . $id));
    }

    public function cannedMessagesIndex(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $rows = $this->cannedMessageRepository->tableExists()
            ? $this->cannedMessageRepository->listForTenant((int) $tenantId)
            : [];

        $navCounts = $this->enlistmentRepository->countsByStatusForTenant((int) $tenantId);

        return Response::view('layout.recruitment_lms', [
            'content' => 'admin.recruitments.canned_messages',
            'title' => 'Messages préfaits — recrutement',
            'recruitmentLmsTitle' => 'Messages préfaits',
            'cannedMessages' => $rows,
            'cannedMessagesTableMissing' => !$this->cannedMessageRepository->tableExists(),
            'recruitmentSidebarCounts' => $navCounts,
            'recruitmentAdminNav' => 'messages',
            'showPortalFooter' => false,
        ]);
    }

    public function cannedMessageStore(Request $request, array $params = []): Response
    {
        return $this->cannedMessageSave($request, null);
    }

    public function cannedMessageUpdate(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);

        return $this->cannedMessageSave($request, $id > 0 ? $id : null);
    }

    public function cannedMessageDelete(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId || !$request->isPost()) {
            return Response::redirect(url('back-office/recruitments/messages-prefaits'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/recruitments/messages-prefaits'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1 || !$this->cannedMessageRepository->tableExists()) {
            return Response::redirect(url('back-office/recruitments/messages-prefaits'));
        }
        $this->cannedMessageRepository->delete($id, (int) $tenantId);
        Session::flash('success', 'Message préfait supprimé.');

        return Response::redirect(url('back-office/recruitments/messages-prefaits'));
    }

    private function cannedMessageSave(Request $request, ?int $id): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId || !$request->isPost()) {
            return Response::redirect(url('back-office/recruitments/messages-prefaits'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/recruitments/messages-prefaits'));
        }
        if (!$this->cannedMessageRepository->tableExists()) {
            Session::flash('error', 'Table des messages préfaits absente — exécutez les migrations.');

            return Response::redirect(url('back-office/recruitments/messages-prefaits'));
        }

        $label = trim((string) $request->input('label', ''));
        $body = trim((string) $request->input('body', ''));
        $sortOrder = (int) $request->input('sort_order', 0);
        $sortOrder = max(0, min(99999, $sortOrder));

        if (mb_strlen($label) < 1 || mb_strlen($label) > 160) {
            Session::flash('error', 'Libellé : 1 à 160 caractères.');

            return Response::redirect(url('back-office/recruitments/messages-prefaits'));
        }
        if (mb_strlen($body) < 1 || mb_strlen($body) > 8000) {
            Session::flash('error', 'Texte : 1 à 8000 caractères.');

            return Response::redirect(url('back-office/recruitments/messages-prefaits'));
        }

        $context = trim((string) $request->input('context', 'generic'));
        $allowedContexts = ['generic', 'accept', 'pending', 'reject', 'redirect'];
        if (!in_array($context, $allowedContexts, true)) {
            $context = 'generic';
        }

        if ($id === null) {
            $this->cannedMessageRepository->create((int) $tenantId, $label, $body, $sortOrder, $context);
            Session::flash('success', 'Message préfait ajouté.');
        } else {
            $row = $this->cannedMessageRepository->findForTenant($id, (int) $tenantId);
            if (!$row) {
                Session::flash('error', 'Entrée introuvable.');

                return Response::redirect(url('back-office/recruitments/messages-prefaits'));
            }
            $this->cannedMessageRepository->update($id, (int) $tenantId, $label, $body, $sortOrder, $context);
            Session::flash('success', 'Message préfait enregistré.');
        }

        return Response::redirect(url('back-office/recruitments/messages-prefaits'));
    }

    /**
     * @param array<string, mixed>|null $urow
     */
    private function displayLabelForTenantUser(?array $urow, int $userId): string
    {
        if ($userId < 1) {
            return 'Pas encore de référent indiqué';
        }
        if (!is_array($urow)) {
            return 'Référent recrutement (profil indisponible)';
        }
        $lab = trim((string) ($urow['display_name'] ?? ''));
        if ($lab === '') {
            $lab = trim((string) ($urow['callsign'] ?? ''));
        }
        if ($lab === '') {
            $lab = trim((string) ($urow['email'] ?? ''));
        }

        return $lab !== '' ? $lab : ('Membre n°' . $userId);
    }

    /**
     * @param array<string, mixed> $enlistment
     */
    private function enlistmentAgeDays(array $enlistment): ?int
    {
        $base = trim((string) ($enlistment['created_at'] ?? ''));
        if ($base === '') {
            return null;
        }
        $ts = strtotime($base);
        if ($ts === false || $ts <= 0) {
            return null;
        }

        return max(0, (int) floor((time() - $ts) / 86400));
    }

    /**
     * @param array<string,mixed> $enlistment
     */
    private function submittedAgeHours(array $enlistment): ?int
    {
        $base = trim((string) ($enlistment['updated_at'] ?? ''));
        if ($base === '') {
            $base = trim((string) ($enlistment['created_at'] ?? ''));
        }
        if ($base === '') {
            return null;
        }
        $ts = strtotime($base);
        if ($ts === false || $ts <= 0) {
            return null;
        }
        $delta = time() - $ts;
        if ($delta < 0) {
            return 0;
        }

        return (int) floor($delta / 3600);
    }

    public function decision(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId || !$request->isPost()) {
            return Response::redirect(url('back-office/recruitments'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/recruitments'));
        }
        $id = (int) ($params['id'] ?? 0);
        $userId = (int) Session::get('user_id');
        if ($id < 1 || $userId < 1) {
            Session::flash('error', 'Action impossible.');

            return Response::redirect(url('back-office/recruitments'));
        }
        $row = $this->enlistmentRepository->findForTenant((int) $tenantId, $id);
        if (!$row) {
            Session::flash('error', 'Dossier introuvable.');

            return Response::redirect(url('back-office/recruitments'));
        }
        $trow = $this->tenantRepository->findById((int) $tenantId);
        $tenantName = trim((string) ((is_array($trow) ? $trow : [])['name'] ?? ''));
        if ($tenantName === '') {
            $tenantName = 'Communauté';
        }
        $action = (string) $request->input('decision', '');
        $map = [
            'accept' => 'reviewed',
            'reject' => 'rejected',
            'block' => 'blocked',
        ];
        $followupActions = ['pending', 'interview'];
        if (!isset($map[$action]) && !in_array($action, $followupActions, true)) {
            Session::flash('error', 'Décision inconnue.');

            return Response::redirect(url('back-office/recruitments/' . $id));
        }
        $comment = trim((string) $request->input('reviewer_comment', ''));
        $comment = $comment !== '' ? mb_substr($comment, 0, 4000) : null;

        if (in_array($action, $followupActions, true)) {
            $linePrefix = $action === 'interview'
                ? '[DEMANDE ENTRETIEN]'
                : '[MISE EN ATTENTE]';
            $detail = $comment;
            if ($action === 'interview') {
                $slotRaw = trim((string) $request->input('interview_slot', ''));
                if ($slotRaw !== '') {
                    $ts = strtotime($slotRaw);
                    if ($ts !== false) {
                        $slotFmt = date('d/m/Y à H:i', $ts);
                        $detail = trim(($detail ?? '') . ($detail ? "\n" : '') . 'Créneau proposé : ' . $slotFmt);
                    }
                }
            }
            if ($detail === null || $detail === '') {
                $detail = $action === 'interview'
                    ? 'Entretien à planifier avec le candidat.'
                    : 'Dossier conservé en file d’instruction.';
            }
            $followupModerated = false;
            if ($detail !== null && $detail !== '') {
                $hitFollow = $this->portalAutoModerationCoordinator->scan($detail);
                if ($hitFollow !== null) {
                    $this->portalAutoModerationCoordinator->enforceAfterStaffViolation((int) $tenantId, $tenantName, $row, $userId, $hitFollow, $detail);
                    $detail = 'Message non conservé : le filtre automatique du portail a refusé une formulation.';
                    $followupModerated = true;
                }
            }
            $fullNote = $linePrefix . "\n" . $detail;

            $okFollowup = $this->enlistmentRepository->appendInstructionFollowup((int) $tenantId, $id, $userId, $fullNote);
            if (!$okFollowup) {
                Session::flash('error', 'Impossible d’ajouter le suivi sur ce dossier (introuvable ou déjà traité).');

                return Response::redirect(url('back-office/recruitments/' . $id));
            }

            $summary = $action === 'interview'
                ? 'Demande d’entretien consignée'
                : 'Dossier mis en attente';
            $this->enlistmentTimelineRepository->append(
                (int) $tenantId,
                $id,
                'staff_note',
                'instruction',
                $summary,
                $detail,
                $userId,
                ['followup_action' => $action]
            );
            $fresh = $this->enlistmentRepository->findForTenant((int) $tenantId, $id);
            if (is_array($fresh)) {
                $this->notifyCandidate((int) $tenantId, $fresh, $action, $detail, $userId);
            }
            $suffix = $followupModerated ? ' Une partie du texte n’a pas été conservée (filtre portail) et une alerte a été envoyée.' : '';
            Session::flash('success', ($action === 'interview'
                ? 'Demande d’entretien enregistrée. Le dossier reste en instruction.'
                : 'Mise en attente enregistrée. Le dossier reste en instruction.') . $suffix);

            return Response::redirect(url('back-office/recruitments/' . $id));
        }

        $decisionCommentModerated = false;
        if ($comment !== null && $comment !== '') {
            $hitDecision = $this->portalAutoModerationCoordinator->scan($comment);
            if ($hitDecision !== null) {
                $this->portalAutoModerationCoordinator->enforceAfterStaffViolation((int) $tenantId, $tenantName, $row, $userId, $hitDecision, $comment);
                $comment = null;
                $decisionCommentModerated = true;
            }
        }

        if ($action === 'accept') {
            $blocked = $this->enlistmentAcceptanceProvisioningService->assertAcceptAllowed((int) $tenantId, $id);
            if ($blocked !== null) {
                Session::flash('error', $blocked);

                return Response::redirect(url('back-office/recruitments/' . $id));
            }
        }

        $ok = $this->enlistmentRepository->applyDecision((int) $tenantId, $id, $map[$action], $userId, $comment);
        if (!$ok) {
            Session::flash('error', 'Cette candidature ne peut pas être traitée (déjà traitée ou introuvable).');

            return Response::redirect(url('back-office/recruitments/' . $id));
        }
        $messages = [
            'reviewed' => 'Candidature acceptée.',
            'rejected' => 'Candidature refusée.',
            'blocked' => 'Candidature refusée — personne marquée comme non admise (interdit).',
        ];
        $decisionFlash = $messages[$map[$action]];
        if ($decisionCommentModerated) {
            $decisionFlash .= ' Le commentaire n’a pas été conservé (filtre portail) et une alerte a été envoyée.';
        }
        Session::flash('success', $decisionFlash);

        $this->enlistmentTimelineRepository->logDecision((int) $tenantId, $id, $userId, $map[$action], $comment);
        $fresh = $this->enlistmentRepository->findForTenant((int) $tenantId, $id);
        if (is_array($fresh)) {
            $this->notifyCandidate((int) $tenantId, $fresh, $action, $comment ?? '', $userId);
        }

        if ($ok && $map[$action] === 'reviewed') {
            $provision = $this->enlistmentAcceptanceProvisioningService->provisionAfterAccept(
                (int) $tenantId,
                $id,
                $userId,
                $comment
            );
            if (!$provision['ok'] && $provision['message'] !== null && $provision['message'] !== '') {
                Session::flash('error', $provision['message']);
                $this->enlistmentTimelineRepository->logAdhesionStep(
                    (int) $tenantId,
                    $id,
                    $userId,
                    'Synchronisation du compte : point d’attention',
                    (string) $provision['message']
                );
            } elseif ($provision['message'] !== null && $provision['message'] !== '') {
                $modSuffix = $decisionCommentModerated ? ' Le commentaire n’a pas été conservé (filtre portail).' : '';
                Session::flash('success', 'Candidature acceptée. ' . (string) $provision['message'] . $modSuffix);
                $this->enlistmentTimelineRepository->logAdhesionStep(
                    (int) $tenantId,
                    $id,
                    $userId,
                    'Synchronisation du compte membre',
                    (string) $provision['message']
                );
            } else {
                $this->enlistmentTimelineRepository->logAdhesionStep(
                    (int) $tenantId,
                    $id,
                    $userId,
                    'Synchronisation du compte membre',
                    'Les étapes automatiques (compte, rôle, messages) se sont déroulées sans message d’alerte particulier.'
                );
            }
        }

        return Response::redirect(url('back-office/recruitments/' . $id));
    }

    /**
     * @param array<string,mixed> $enlistment
     */
    private function notifyCandidate(int $tenantId, array $enlistment, string $action, string $comment, int $actorUserId): void
    {
        $email = strtolower(trim((string) ($enlistment['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $tenant = $this->tenantRepository->findById($tenantId);
        $tenantName = trim((string) ((is_array($tenant) ? $tenant : [])['name'] ?? ''));
        if ($tenantName === '') {
            $tenantName = 'Communauté';
        }
        $eid = (int) ($enlistment['id'] ?? 0);
        $token = $this->enlistmentRepository->ensureCandidatePortalToken($tenantId, $eid, 24 * 7);
        $portalUrl = $token !== null ? url('enlistment/suivi/' . rawurlencode($token)) : url('enlistment');
        $statusLabel = match ($action) {
            'accept' => 'Acceptée',
            'reject' => 'Refusée',
            'block' => 'Non admis',
            'interview' => 'Entretien proposé',
            'pending' => 'Mise en attente',
            default => 'Mise à jour du dossier',
        };
        $commentStr = trim((string) $comment);
        $msgBody = 'Statut : ' . $statusLabel . ($commentStr !== '' ? ("\n\n" . $commentStr) : '');
        $this->enlistmentRepository->appendCandidatePortalMessage($tenantId, $eid, 'staff', $msgBody);

        $sent = $this->emailService->sendEnlistmentRecruitmentStatusCandidate(
            $email,
            $tenantName,
            $statusLabel,
            $commentStr,
            $portalUrl,
            $tenantId,
            $action,
            $eid
        );
        if ($this->enlistmentTimelineRepository->tableExists() && $eid > 0) {
            $lines = ['Un message de mise à jour a été ajouté au fil du portail de suivi candidat.'];
            $lines[] = $sent
                ? ('Courriel de notification envoyé au candidat (' . $email . ').')
                : 'L’envoi du courriel au candidat a échoué ou a été désactivé.';
            $this->enlistmentTimelineRepository->append(
                $tenantId,
                $eid,
                'system',
                'communication',
                'Notification au candidat (portail et courriel)',
                implode("\n", $lines),
                $actorUserId > 0 ? $actorUserId : null,
                [
                    'timeline_family' => 'email_notify',
                    'notify_kind' => 'candidate_status',
                    'mail_ok' => $sent,
                ],
                null
            );
        }
    }

    public function teamWall(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $kindFilter = trim((string) $request->query('kind', ''));
        if ($kindFilter !== '' && !RecruitmentTeamWallRepository::isValidPostKind($kindFilter)) {
            $kindFilter = '';
        }
        $sortParam = trim((string) $request->query('ord', 'new'));
        $orderSql = $sortParam === 'old' ? 'asc' : 'desc';
        $extended = $this->recruitmentTeamWallRepository->extendedSchemaExists();
        $postKindFilter = $extended && $kindFilter !== '' ? $kindFilter : null;
        $entries = $this->recruitmentTeamWallRepository->tableExists()
            ? $this->recruitmentTeamWallRepository->listRecent((int) $tenantId, 120, $postKindFilter, $orderSql)
            : [];
        // Filtre thème dans l’URL alors que les colonnes ne sont pas encore là : ignorer le filtre mais garder l’URL lisible.
        if (!$extended) {
            $kindFilter = '';
        }
        $wallTotal = $this->recruitmentTeamWallRepository->tableExists()
            ? $this->recruitmentTeamWallRepository->countForTenant((int) $tenantId)
            : 0;
        $countsByKind = $extended
            ? $this->recruitmentTeamWallRepository->countsByKindForTenant((int) $tenantId)
            : [];
        $actorIds = [];
        foreach ($entries as $er) {
            $aid = (int) ($er['actor_user_id'] ?? 0);
            if ($aid > 0) {
                $actorIds[] = $aid;
            }
        }
        $actorIds = array_values(array_unique($actorIds));
        $actorLabels = [];
        if ($actorIds !== []) {
            $users = $this->userRepository->findByIdsForTenant((int) $tenantId, $actorIds);
            foreach ($users as $id => $urow) {
                $lab = trim((string) ($urow['display_name'] ?? ''));
                if ($lab === '') {
                    $lab = trim((string) ($urow['callsign'] ?? ''));
                }
                $actorLabels[(int) $id] = $lab !== '' ? $lab : ('Compte n°' . (int) $id);
            }
        }
        $navCounts = $this->enlistmentRepository->countsByStatusForTenant((int) $tenantId);

        return Response::view('layout.recruitment_lms', [
            'content' => 'admin.recruitments.team_wall',
            'title' => 'Échanges recruteurs',
            'recruitmentLmsTitle' => 'Échanges équipe',
            'teamWallEntries' => $entries,
            'teamWallActorLabels' => $actorLabels,
            'teamWallTableMissing' => !$this->recruitmentTeamWallRepository->tableExists(),
            'teamWallExtendedSchema' => $extended,
            'teamWallKindLabels' => RecruitmentTeamWallRepository::postKindLabels(),
            'teamWallFilterKind' => $kindFilter,
            'teamWallSort' => $sortParam === 'old' ? 'old' : 'new',
            'teamWallCountsByKind' => $countsByKind,
            'teamWallTotalCount' => $wallTotal,
            'recruitmentSidebarCounts' => $navCounts,
            'recruitmentAdminNav' => 'teamwall',
            'showPortalFooter' => false,
        ]);
    }

    public function teamWallPost(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId || !$request->isPost()) {
            return Response::redirect(url('back-office/recruitments/equipe'));
        }
        $returnWallUrl = function () use ($request): string {
            $target = url('back-office/recruitments/equipe');
            $rk = trim((string) $request->input('return_kind', ''));
            $ro = trim((string) $request->input('return_ord', 'new'));
            $qs = [];
            if (RecruitmentTeamWallRepository::isValidPostKind($rk)) {
                $qs['kind'] = $rk;
            }
            if ($ro === 'old') {
                $qs['ord'] = 'old';
            }
            if ($qs !== []) {
                $target .= (str_contains($target, '?') ? '&' : '?') . http_build_query($qs);
            }

            return $target;
        };
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($returnWallUrl());
        }
        if (!$this->recruitmentTeamWallRepository->tableExists()) {
            Session::flash('error', 'Le fil d’échanges n’est pas encore disponible. Un administrateur technique doit finaliser la mise à jour du site.');

            return Response::redirect(url('back-office/recruitments/equipe'));
        }
        $uid = (int) Session::get('user_id');
        if ($uid < 1) {
            return Response::redirect(url('login'));
        }
        $body = trim((string) $request->input('team_wall_body', ''));
        if (mb_strlen($body) < 2) {
            Session::flash('error', 'Message trop court.');

            return Response::redirect($returnWallUrl());
        }
        if (mb_strlen($body) > 4000) {
            Session::flash('error', 'Message trop long (4 000 caractères maximum).');

            return Response::redirect($returnWallUrl());
        }
        $postKind = trim((string) $request->input('team_wall_kind', RecruitmentTeamWallRepository::defaultPostKind()));
        if (!RecruitmentTeamWallRepository::isValidPostKind($postKind)) {
            $postKind = RecruitmentTeamWallRepository::defaultPostKind();
        }
        $subject = trim((string) $request->input('team_wall_subject', ''));
        if (mb_strlen($subject) > 200) {
            Session::flash('error', 'Le sujet est trop long (200 caractères maximum).');

            return Response::redirect($returnWallUrl());
        }
        $subjectOrNull = $subject === '' ? null : $subject;
        $ok = $this->recruitmentTeamWallRepository->create((int) $tenantId, $uid, $body, $postKind, $subjectOrNull);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Message publié.' : 'Publication impossible.');

        return Response::redirect($returnWallUrl());
    }
}
