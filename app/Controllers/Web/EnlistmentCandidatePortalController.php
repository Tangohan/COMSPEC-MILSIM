<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\EnlistmentRecruitmentEngagementRepository;
use App\Repositories\EnlistmentRepository;
use App\Repositories\EnlistmentTimelineRepository;
use App\Repositories\TenantRepository;
use App\Services\Analytics\AnalyticsEventCategory;
use App\Services\Analytics\AnalyticsEventName;
use App\Services\Analytics\AnalyticsEventService;
use App\Services\Analytics\AnalyticsSubjectType;
use App\Services\Recruitment\EnlistmentCandidatePortalJourneyService;
use App\Services\Recruitment\EnlistmentPortalAttachmentService;
use App\Services\Recruitment\EnlistmentPortalAutoModerationCoordinator;
use App\Services\Recruitment\EnlistmentPortalMessagingNotificationService;
use App\Support\ForumReportReason;

final class EnlistmentCandidatePortalController
{
    private const PORTAL_PIECE_DOWNLOAD_DELAY_SECONDS = 10;

    private const PORTAL_PIECE_DOWNLOAD_SESSION_KEY = '_comspec_portal_piece_download_gates';

    private const PORTAL_REPORT_RATE_SESSION_PREFIX = '_comspec_portal_report_rate_';

    private const PORTAL_REPORT_MAX_PER_HOUR = 8;

    public function __construct(
        private EnlistmentRepository $enlistmentRepository,
        private TenantRepository $tenantRepository,
        private EnlistmentPortalMessagingNotificationService $portalMessagingNotificationService,
        private EnlistmentPortalAttachmentService $portalAttachmentService,
        private EnlistmentPortalAutoModerationCoordinator $portalAutoModerationCoordinator,
        private EnlistmentTimelineRepository $enlistmentTimelineRepository,
        private EnlistmentRecruitmentEngagementRepository $recruitmentEngagementRepository,
        private AnalyticsEventService $analyticsEventService,
        private EnlistmentCandidatePortalJourneyService $candidatePortalJourneyService,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    private function isPortalAccessBlocked(Request $request, array $row): bool
    {
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        $email = strtolower(trim((string) ($row['email'] ?? '')));

        return $this->portalAutoModerationCoordinator->isPortalAccessBlocked($tenantId, $email, trim($request->ip()));
    }

    /**
     * @return array<string, mixed>
     */
    private function portalAccessSuspendedErrorView(): array
    {
        return [
            'message' => 'L’accès à ce suivi en ligne a été suspendu après qu’un message a été bloqué par les règles de sécurité (par exemple contenu injurieux ou inacceptable). Seule l’équipe de la communauté peut rétablir l’accès.',
            'enlistmentRetryUrl' => url('enlistment/error'),
            'errorContext' => 'portal_access_suspended',
        ];
    }

    public function show(Request $request, array $params = []): Response
    {
        $token = (string) ($params['token'] ?? '');
        $row = $this->enlistmentRepository->findByCandidatePortalToken($token);
        if (!$row) {
            return Response::view('enlistment.error', ['message' => 'Lien invalide ou expiré.', 'enlistmentRetryUrl' => url('enlistment/error')]);
        }
        if ($this->isPortalAccessBlocked($request, $row)) {
            return Response::view('enlistment.error', $this->portalAccessSuspendedErrorView());
        }
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        $tenant = $tenantId > 0 ? $this->tenantRepository->findById($tenantId) : null;
        $enlistmentId = (int) ($row['id'] ?? 0);
        $messages = $this->enlistmentRepository->listCandidatePortalMessages($tenantId, $enlistmentId);
        $attachments = $this->enlistmentRepository->listCandidatePortalAttachments($tenantId, $enlistmentId);
        $timelineRows = [];
        if ($this->enlistmentTimelineRepository->tableExists()) {
            $this->enlistmentTimelineRepository->seedLegacyIfEmpty($tenantId, $enlistmentId, $row);
            $timelineRows = $this->enlistmentTimelineRepository->listForEnlistment($tenantId, $enlistmentId);
        }
        $portalSteps = $this->candidatePortalJourneyService->buildSteps($row, $timelineRows, count($messages), count($attachments));
        $ageDays = $this->enlistmentAgeDaysPortal($row);
        $retroEligible = $ageDays !== null && $ageDays >= 30;
        $retroTable = $this->recruitmentEngagementRepository->retroTableExists();
        $candidateRetroRow = $retroTable
            ? $this->recruitmentEngagementRepository->findRetro($tenantId, $enlistmentId, EnlistmentRecruitmentEngagementRepository::SCOPE_CANDIDATE_RETURN)
            : null;

        return Response::view('enlistment.candidate_portal', [
            'enlistment' => $row,
            'messages' => $messages,
            'attachments' => $attachments,
            'tenant' => $tenant,
            'token' => $token,
            'flashOk' => Session::getFlash('success'),
            'flashErr' => Session::getFlash('error'),
            'portalUploadsReady' => $this->enlistmentRepository->candidatePortalUploadsReady(),
            'allowPortalFiles' => !empty($row['candidate_portal_allow_files']),
            'allowPortalAudio' => !empty($row['candidate_portal_allow_audio']),
            'portalRetroEligible' => $retroEligible,
            'candidateRetroFeedback' => $candidateRetroRow,
            'portalRetroTableReady' => $retroTable,
            'portalSteps' => $portalSteps,
        ]);
    }

    public function message(Request $request, array $params = []): Response
    {
        $token = (string) ($params['token'] ?? '');
        $row = $this->enlistmentRepository->findByCandidatePortalToken($token);
        if (!$row || !$request->isPost()) {
            return Response::redirect(url('enlistment/error'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }
        if ($this->isPortalAccessBlocked($request, $row)) {
            $v = $this->portalAccessSuspendedErrorView();
            Session::flash('enlistment_error', (string) ($v['message'] ?? ''));
            Session::flash('enlistment_error_context', (string) ($v['errorContext'] ?? ''));
            Session::flash('enlistment_retry_url', (string) ($v['enlistmentRetryUrl'] ?? url('enlistment')));

            return Response::redirect(url('enlistment/error'));
        }

        $body = trim((string) $request->input('candidate_message', ''));
        if (mb_strlen($body) < 2) {
            Session::flash('error', 'Message trop court.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }

        $hit = $this->portalAutoModerationCoordinator->scan($body);
        if ($hit !== null) {
            $tenantId = (int) ($row['tenant_id'] ?? 0);
            $tenantRow = $tenantId > 0 ? $this->tenantRepository->findById($tenantId) : null;
            $tenantName = trim((string) (is_array($tenantRow) ? ($tenantRow['name'] ?? '') : ''));
            if ($tenantName === '') {
                $tenantName = 'Communauté';
            }
            $this->portalAutoModerationCoordinator->enforceAfterCandidateViolation($tenantId, $tenantName, $row, trim($request->ip()), $hit, $body);
            Session::flash('error', 'Ce message ne peut pas être envoyé : il contient des formulations interdites par le filtre automatique du portail. Une alerte a été transmise à l’équipe et l’accès peut être restreint.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }

        $ok = $this->enlistmentRepository->appendCandidatePortalMessage(
            (int) ($row['tenant_id'] ?? 0),
            (int) ($row['id'] ?? 0),
            'candidate',
            $body
        );
        if ($ok) {
            $tenantId = (int) ($row['tenant_id'] ?? 0);
            $eid = (int) ($row['id'] ?? 0);
            if ($this->enlistmentTimelineRepository->tableExists() && $tenantId > 0 && $eid > 0) {
                $preview = mb_strlen($body) > 500 ? mb_substr($body, 0, 497) . '…' : $body;
                $this->enlistmentTimelineRepository->append(
                    $tenantId,
                    $eid,
                    'system',
                    'communication',
                    'Message du candidat sur le portail',
                    $preview,
                    null,
                    ['timeline_family' => 'portal_message', 'origin' => 'candidate'],
                    null
                );
            }
            $tenantRow = $tenantId > 0 ? $this->tenantRepository->findById($tenantId) : null;
            $tenantName = trim((string) (is_array($tenantRow) ? ($tenantRow['name'] ?? '') : ''));
            if ($tenantName === '') {
                $tenantName = 'Communauté';
            }
            try {
                $this->portalMessagingNotificationService->notifyStaffOfCandidatePortalMessage(
                    $tenantId,
                    $tenantName,
                    $row,
                    $body,
                    is_array($tenantRow) ? $tenantRow : null
                );
            } catch (\Throwable) {
            }
        }
        Session::flash($ok ? 'success' : 'error', $ok ? 'Votre message a été transmis.' : 'Impossible d’enregistrer le message.');

        return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
    }

    public function uploadAttachment(Request $request, array $params = []): Response
    {
        $token = (string) ($params['token'] ?? '');
        $row = $this->enlistmentRepository->findByCandidatePortalToken($token);
        if (!$row || !$request->isPost()) {
            return Response::redirect(url('enlistment/error'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }
        if ($this->isPortalAccessBlocked($request, $row)) {
            $v = $this->portalAccessSuspendedErrorView();
            Session::flash('enlistment_error', (string) ($v['message'] ?? ''));
            Session::flash('enlistment_error_context', (string) ($v['errorContext'] ?? ''));
            Session::flash('enlistment_retry_url', (string) ($v['enlistmentRetryUrl'] ?? url('enlistment')));

            return Response::redirect(url('enlistment/error'));
        }
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        $enlistmentId = (int) ($row['id'] ?? 0);
        if (!$this->enlistmentRepository->candidatePortalUploadsReady()) {
            Session::flash('error', 'L’envoi de fichiers n’est pas disponible pour le moment.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }
        $allowFiles = !empty($row['candidate_portal_allow_files']);
        $allowAudio = !empty($row['candidate_portal_allow_audio']);
        if (!$allowFiles && !$allowAudio) {
            Session::flash('error', 'L’équipe n’a pas activé l’envoi de pièces pour ce dossier.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }
        $file = isset($_FILES['portal_upload']) && is_array($_FILES['portal_upload']) ? $_FILES['portal_upload'] : null;
        $origNameProbe = isset($file['name']) ? trim((string) $file['name']) : '';
        if ($origNameProbe !== '') {
            $hitName = $this->portalAutoModerationCoordinator->scan($origNameProbe);
            if ($hitName !== null) {
                $tenantRow = $tenantId > 0 ? $this->tenantRepository->findById($tenantId) : null;
                $tenantName = trim((string) (is_array($tenantRow) ? ($tenantRow['name'] ?? '') : ''));
                if ($tenantName === '') {
                    $tenantName = 'Communauté';
                }
                $this->portalAutoModerationCoordinator->enforceAfterCandidateViolation($tenantId, $tenantName, $row, trim($request->ip()), $hitName, $origNameProbe);
                Session::flash('error', 'Le nom du fichier est refusé par le filtre automatique du portail. Une alerte a été transmise à l’équipe et l’accès peut être restreint.');

                return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
            }
        }
        $result = $this->portalAttachmentService->storeCandidateUpload($tenantId, $enlistmentId, $allowFiles, $allowAudio, $file);
        if (!($result['ok'] ?? false)) {
            Session::flash('error', (string) ($result['error'] ?? 'Envoi impossible.'));

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }
        $kind = (string) ($result['kind'] ?? 'file');
        $orig = (string) ($result['original_name'] ?? 'fichier');
        $pieceId = (int) ($result['id'] ?? 0);
        $line = ($kind === 'audio' ? 'Enregistrement audio transmis' : 'Document transmis') . ' : ' . $orig;
        if ($pieceId > 0) {
            $line .= "\n\n[piece:#" . $pieceId . ']';
        }
        $this->enlistmentRepository->appendCandidatePortalMessage($tenantId, $enlistmentId, 'candidate', $line);
        if ($this->enlistmentTimelineRepository->tableExists()) {
            $this->enlistmentTimelineRepository->append(
                $tenantId,
                $enlistmentId,
                'system',
                'portal',
                $kind === 'audio' ? 'Enregistrement audio déposé sur le portail' : 'Document déposé sur le portail',
                $line,
                null,
                ['timeline_family' => 'portal_upload', 'kind' => $kind],
                null
            );
        }
        $tenantRow = $tenantId > 0 ? $this->tenantRepository->findById($tenantId) : null;
        $tenantName = trim((string) (is_array($tenantRow) ? ($tenantRow['name'] ?? '') : ''));
        if ($tenantName === '') {
            $tenantName = 'Communauté';
        }
        try {
            $this->portalMessagingNotificationService->notifyStaffOfCandidatePortalUpload(
                $tenantId,
                $tenantName,
                $row,
                $kind,
                $orig,
                is_array($tenantRow) ? $tenantRow : null
            );
        } catch (\Throwable) {
        }
        Session::flash('success', 'Votre pièce jointe a bien été transmise.');

        return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
    }

    public function candidateRetroSave(Request $request, array $params = []): Response
    {
        $token = (string) ($params['token'] ?? '');
        $row = $this->enlistmentRepository->findByCandidatePortalToken($token);
        if (!$row || !$request->isPost()) {
            return Response::redirect(url('enlistment/error'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }
        if ($this->isPortalAccessBlocked($request, $row)) {
            $v = $this->portalAccessSuspendedErrorView();
            Session::flash('enlistment_error', (string) ($v['message'] ?? ''));
            Session::flash('enlistment_error_context', (string) ($v['errorContext'] ?? ''));
            Session::flash('enlistment_retry_url', (string) ($v['enlistmentRetryUrl'] ?? url('enlistment')));

            return Response::redirect(url('enlistment/error'));
        }
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        $enlistmentId = (int) ($row['id'] ?? 0);
        if (!$this->recruitmentEngagementRepository->retroTableExists()) {
            Session::flash('error', 'Cette fonctionnalité n’est pas encore disponible sur cette installation.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }
        $ageDays = $this->enlistmentAgeDaysPortal($row);
        if ($ageDays === null || $ageDays < 30) {
            Session::flash('error', 'Ce bilan n’est proposé qu’à partir de 30 jours après la réception de votre dossier.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }
        $rating = (int) $request->input('candidate_retro_rating', 0);
        if ($rating < 1 || $rating > 5) {
            Session::flash('error', 'Indiquez une note de 1 à 5.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }
        $comment = trim((string) $request->input('candidate_retro_comment', ''));
        if ($comment === '') {
            Session::flash('error', 'Ajoutez quelques lignes pour décrire votre ressenti.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }
        $tenantRow = $tenantId > 0 ? $this->tenantRepository->findById($tenantId) : null;
        $tenantName = trim((string) (is_array($tenantRow) ? ($tenantRow['name'] ?? '') : ''));
        if ($tenantName === '') {
            $tenantName = 'Communauté';
        }
        $hit = $this->portalAutoModerationCoordinator->scan($comment);
        if ($hit !== null) {
            $this->portalAutoModerationCoordinator->enforceAfterCandidateViolation($tenantId, $tenantName, $row, trim($request->ip()), $hit, $comment);
            Session::flash('error', 'Ce texte ne peut pas être enregistré : il contient des formulations interdites par le filtre automatique du portail.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }
        if (!$this->recruitmentEngagementRepository->upsertCandidateRetro($tenantId, $enlistmentId, $rating, $comment)) {
            Session::flash('error', 'Enregistrement impossible pour le moment.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }
        if ($this->enlistmentTimelineRepository->tableExists()) {
            $this->enlistmentTimelineRepository->append(
                $tenantId,
                $enlistmentId,
                'system',
                'communication',
                'Retour du candidat sur le déroulement (après 30 jours)',
                'Note : ' . $rating . " / 5\n\n" . $comment,
                null,
                ['retro' => 'candidate_return'],
                null
            );
        }
        try {
            $this->analyticsEventService->record(
                $tenantId,
                null,
                AnalyticsEventCategory::RECRUITMENT,
                AnalyticsEventName::ENLISTMENT_CANDIDATE_RETRO_SUBMIT,
                AnalyticsSubjectType::ENLISTMENT,
                $enlistmentId,
                null,
                ['rating' => $rating],
            );
        } catch (\Throwable) {
        }
        Session::flash('success', 'Merci : votre retour a bien été transmis à l’équipe recrutement.');

        return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
    }

    public function reportPortalContent(Request $request, array $params = []): Response
    {
        $token = (string) ($params['token'] ?? '');
        $row = $this->enlistmentRepository->findByCandidatePortalToken($token);
        if (!$row || !$request->isPost()) {
            return Response::redirect(url('enlistment/error'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Rechargez la page puis réessayez.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }
        if ($this->isPortalAccessBlocked($request, $row)) {
            $v = $this->portalAccessSuspendedErrorView();
            Session::flash('enlistment_error', (string) ($v['message'] ?? ''));
            Session::flash('enlistment_error_context', (string) ($v['errorContext'] ?? ''));
            Session::flash('enlistment_retry_url', (string) ($v['enlistmentRetryUrl'] ?? url('enlistment')));

            return Response::redirect(url('enlistment/error'));
        }

        $kind = strtolower(trim((string) $request->input('portal_report_kind', '')));
        $targetId = (int) $request->input('portal_report_id', 0);
        $category = trim((string) $request->input('portal_report_category', 'other'));
        $details = trim((string) $request->input('portal_report_details', ''));
        if ($details !== '') {
            $details = mb_substr($details, 0, 2000);
        }

        if ($kind !== 'piece' && $kind !== 'message') {
            Session::flash('error', 'Signalement incomplet. Réessayez depuis le bouton sur le fil ou la liste des pièces.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }
        if ($targetId < 1) {
            Session::flash('error', 'Signalement incomplet.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }

        if (!$this->portalReportRateAllows($token)) {
            Session::flash('error', 'Vous avez déjà envoyé plusieurs signalements récemment. Réessayez dans un moment ou écrivez un message à l’équipe.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }

        $tenantId = (int) ($row['tenant_id'] ?? 0);
        $enlistmentId = (int) ($row['id'] ?? 0);
        if ($tenantId < 1 || $enlistmentId < 1) {
            Session::flash('error', 'Impossible d’enregistrer le signalement pour le moment.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }

        $normalized = ForumReportReason::fromCategory($category !== '' ? $category : 'other', $details);
        $reasonLabel = (string) ($normalized['reason'] ?? 'Autre');

        $pieceRow = null;
        $messageRow = null;
        $timelineBodyLines = ['Signalement transmis depuis le portail de suivi du candidat.', 'Motif : ' . $reasonLabel];

        if ($kind === 'piece') {
            $pieceRow = $this->enlistmentRepository->findCandidatePortalAttachment($tenantId, $enlistmentId, $targetId);
            if ($pieceRow === null) {
                Session::flash('error', 'Ce contenu ne correspond plus à votre dossier ou n’est plus disponible.');

                return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
            }
            $fn = trim((string) ($pieceRow['original_name'] ?? ''));
            $pk = (string) ($pieceRow['kind'] ?? 'file');
            $kindFr = $pk === 'audio' ? 'message vocal / enregistrement audio' : 'document transmis';
            $timelineBodyLines[] = 'Objet du signalement : ' . $kindFr . ($fn !== '' ? ' (« ' . $fn . ' »).' : '.');
        } else {
            $messageRow = $this->enlistmentRepository->findCandidatePortalMessage($tenantId, $enlistmentId, $targetId);
            if ($messageRow === null) {
                Session::flash('error', 'Ce message ne correspond plus à votre dossier ou n’est plus disponible.');

                return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
            }
            if (((string) ($messageRow['entry_kind'] ?? '')) !== 'staff') {
                Session::flash('error', 'Seuls les messages de l’équipe recrutement peuvent être signalés de cette façon. Pour un autre sujet, utilisez le formulaire « Écrire à l’équipe ».');

                return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
            }
            $timelineBodyLines[] = 'Objet du signalement : message de l’équipe recrutement sur le fil de suivi.';
        }

        if ($details !== '') {
            $timelineBodyLines[] = 'Précisions du candidat : ' . $details;
        }

        $timelineBody = implode("\n\n", $timelineBodyLines);
        $emailExcerptLines = [
            'Signalement sur le portail de suivi',
            'Motif : ' . $reasonLabel,
        ];
        if ($kind === 'piece' && is_array($pieceRow)) {
            $fn2 = trim((string) ($pieceRow['original_name'] ?? ''));
            $emailExcerptLines[] = ($pieceRow['kind'] ?? '') === 'audio'
                ? 'Cible : message vocal ou audio joint' . ($fn2 !== '' ? ' (« ' . $fn2 . ' »).' : '.')
                : 'Cible : document joint' . ($fn2 !== '' ? ' (« ' . $fn2 . ' »).' : '.');
        } else {
            $emailExcerptLines[] = 'Cible : message écrit de l’équipe sur le fil de suivi.';
        }
        if ($details !== '') {
            $emailExcerptLines[] = 'Précisions : ' . $details;
        }
        $emailExcerpt = implode("\n", $emailExcerptLines);

        if ($this->enlistmentTimelineRepository->tableExists()) {
            $meta = [
                'timeline_family' => 'portal_candidate_report',
                'report_kind' => $kind,
                'report_type' => (string) ($normalized['report_type'] ?? 'other'),
                'category' => $category,
            ];
            if ($kind === 'piece') {
                $meta['attachment_id'] = $targetId;
            } else {
                $meta['message_id'] = $targetId;
            }
            $this->enlistmentTimelineRepository->append(
                $tenantId,
                $enlistmentId,
                'system',
                'communication',
                'Signalement depuis le portail de suivi',
                $timelineBody,
                null,
                $meta,
                null
            );
        }

        $this->portalReportRateRecord($token);

        $tenantRow = $tenantId > 0 ? $this->tenantRepository->findById($tenantId) : null;
        $tenantName = trim((string) (is_array($tenantRow) ? ($tenantRow['name'] ?? '') : ''));
        if ($tenantName === '') {
            $tenantName = 'Communauté';
        }
        try {
            $this->portalMessagingNotificationService->notifyStaffOfCandidatePortalContentReport(
                $tenantId,
                $tenantName,
                $row,
                $emailExcerpt,
                is_array($tenantRow) ? $tenantRow : null
            );
        } catch (\Throwable) {
        }

        Session::flash('success', 'Votre signalement a bien été transmis. L’équipe recrutement en est informée.');

        return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
    }

    /**
     * @param array<string, mixed> $enlistmentRow
     */
    private function enlistmentAgeDaysPortal(array $enlistmentRow): ?int
    {
        $base = trim((string) ($enlistmentRow['created_at'] ?? ''));
        if ($base === '') {
            return null;
        }
        $ts = strtotime($base);
        if ($ts === false || $ts <= 0) {
            return null;
        }

        return max(0, (int) floor((time() - $ts) / 86400));
    }

    public function attachmentDownloadPreparation(Request $request, array $params = []): Response
    {
        $token = (string) ($params['token'] ?? '');
        $attachmentId = (int) ($params['attachmentId'] ?? 0);
        $row = $this->enlistmentRepository->findByCandidatePortalToken($token);
        if (!$row || $attachmentId < 1) {
            return Response::view('enlistment.error', ['message' => 'Lien invalide ou expiré.', 'enlistmentRetryUrl' => url('enlistment/error')]);
        }
        if ($this->isPortalAccessBlocked($request, $row)) {
            return Response::view('enlistment.error', $this->portalAccessSuspendedErrorView());
        }
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        $enlistmentId = (int) ($row['id'] ?? 0);
        $att = $this->enlistmentRepository->findCandidatePortalAttachment($tenantId, $enlistmentId, $attachmentId);
        if (!$att) {
            return Response::view('enlistment.error', ['message' => 'Pièce jointe introuvable.', 'enlistmentRetryUrl' => url('enlistment/error')]);
        }
        $this->portalPieceDownloadGateTouch($token, $attachmentId);
        $gates = $this->portalPieceDownloadGates();
        $key = $this->portalPieceDownloadGateKey($token, $attachmentId);
        $t0 = (int) ($gates[$key]['t'] ?? time());
        $elapsed = max(0, time() - $t0);
        $waitRemaining = max(0, self::PORTAL_PIECE_DOWNLOAD_DELAY_SECONDS - $elapsed);
        $unlocked = !empty($gates[$key]['unlocked']) || $elapsed >= self::PORTAL_PIECE_DOWNLOAD_DELAY_SECONDS;

        return Response::view('enlistment.portal_attachment_preparation', [
            'portalToken' => $token,
            'attachmentId' => $attachmentId,
            'originalName' => trim((string) ($att['original_name'] ?? 'fichier')),
            'kind' => (string) ($att['kind'] ?? 'file'),
            'downloadDelaySeconds' => self::PORTAL_PIECE_DOWNLOAD_DELAY_SECONDS,
            'waitRemainingSeconds' => $waitRemaining,
            'gateUnlocked' => $unlocked,
            'followUpUrl' => url('enlistment/suivi/' . rawurlencode($token)),
            'downloadUrl' => url('enlistment/suivi/' . rawurlencode($token) . '/piece/' . $attachmentId),
        ]);
    }

    public function downloadAttachment(Request $request, array $params = []): Response
    {
        $token = (string) ($params['token'] ?? '');
        $attachmentId = (int) ($params['attachmentId'] ?? 0);
        $row = $this->enlistmentRepository->findByCandidatePortalToken($token);
        if (!$row || $attachmentId < 1) {
            return Response::view('enlistment.error', ['message' => 'Lien invalide ou expiré.', 'enlistmentRetryUrl' => url('enlistment/error')]);
        }
        if ($this->isPortalAccessBlocked($request, $row)) {
            return Response::view('enlistment.error', $this->portalAccessSuspendedErrorView());
        }
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        $enlistmentId = (int) ($row['id'] ?? 0);
        $att = $this->enlistmentRepository->findCandidatePortalAttachment($tenantId, $enlistmentId, $attachmentId);
        if (!$att) {
            return Response::view('enlistment.error', ['message' => 'Pièce jointe introuvable.', 'enlistmentRetryUrl' => url('enlistment/error')]);
        }
        $path = $this->portalAttachmentService->absolutePathForStorage((string) ($att['storage_path'] ?? ''));
        if ($path === '' || !is_file($path)) {
            return Response::view('enlistment.error', ['message' => 'Fichier introuvable.', 'enlistmentRetryUrl' => url('enlistment/error')]);
        }
        $mime = trim((string) ($att['mime'] ?? ''));
        if ($mime === '') {
            $mime = 'application/octet-stream';
        }
        $name = trim((string) ($att['original_name'] ?? 'piece-jointe'));
        $safeName = str_replace(['"', "\r", "\n"], '', $name);
        $inline = (string) $request->query('inline', '') === '1';
        if (!$inline && !$this->portalPieceDownloadGateAllows($token, $attachmentId)) {
            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token) . '/piece/' . $attachmentId . '/preparation'));
        }
        if (!$inline) {
            $this->portalPieceDownloadGateMarkUnlocked($token, $attachmentId);
        }
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

    private function portalReportRateSessionKey(string $token): string
    {
        return self::PORTAL_REPORT_RATE_SESSION_PREFIX . hash('sha256', $token);
    }

    /**
     * Fenêtre glissante d’1 h, persistance du tableau élagué.
     *
     * @return list<int>
     */
    private function portalReportRateTimestamps(string $token): array
    {
        Session::start();
        $k = $this->portalReportRateSessionKey($token);
        $raw = Session::get($k);
        $list = is_array($raw) ? $raw : [];
        $cut = time() - 3600;
        $out = [];
        foreach ($list as $t) {
            if ((int) $t >= $cut) {
                $out[] = (int) $t;
            }
        }
        $out = array_values($out);
        Session::set($k, $out);

        return $out;
    }

    private function portalReportRateAllows(string $token): bool
    {
        return count($this->portalReportRateTimestamps($token)) < self::PORTAL_REPORT_MAX_PER_HOUR;
    }

    private function portalReportRateRecord(string $token): void
    {
        Session::start();
        $k = $this->portalReportRateSessionKey($token);
        $list = $this->portalReportRateTimestamps($token);
        $list[] = time();
        Session::set($k, $list);
    }

    private function portalPieceDownloadGateKey(string $token, int $attachmentId): string
    {
        return hash('sha256', $token . "\0" . $attachmentId);
    }

    /** @return array<string, array{t: int, unlocked?: bool}> */
    private function portalPieceDownloadGates(): array
    {
        Session::start();
        $g = Session::get(self::PORTAL_PIECE_DOWNLOAD_SESSION_KEY);

        return is_array($g) ? $g : [];
    }

    private function portalPieceDownloadGatesSave(array $gates): void
    {
        Session::start();
        if (count($gates) > 40) {
            $gates = array_slice($gates, -40, null, true);
        }
        Session::set(self::PORTAL_PIECE_DOWNLOAD_SESSION_KEY, $gates);
    }

    private function portalPieceDownloadGateTouch(string $token, int $attachmentId): void
    {
        $key = $this->portalPieceDownloadGateKey($token, $attachmentId);
        $gates = $this->portalPieceDownloadGates();
        if (!isset($gates[$key])) {
            $gates[$key] = ['t' => time(), 'unlocked' => false];
            $this->portalPieceDownloadGatesSave($gates);
        }
    }

    private function portalPieceDownloadGateAllows(string $token, int $attachmentId): bool
    {
        $key = $this->portalPieceDownloadGateKey($token, $attachmentId);
        $gates = $this->portalPieceDownloadGates();
        if (!isset($gates[$key])) {
            return false;
        }
        $row = $gates[$key];
        if (!empty($row['unlocked'])) {
            return true;
        }
        $t0 = (int) ($row['t'] ?? 0);

        return $t0 > 0 && (time() - $t0) >= self::PORTAL_PIECE_DOWNLOAD_DELAY_SECONDS;
    }

    private function portalPieceDownloadGateMarkUnlocked(string $token, int $attachmentId): void
    {
        $key = $this->portalPieceDownloadGateKey($token, $attachmentId);
        $gates = $this->portalPieceDownloadGates();
        if (!isset($gates[$key])) {
            $gates[$key] = ['t' => time(), 'unlocked' => true];
        } else {
            $gates[$key]['unlocked'] = true;
        }
        $this->portalPieceDownloadGatesSave($gates);
    }
}
