<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\EnlistmentCannedMessageRepository;
use App\Repositories\EnlistmentRepository;
use App\Repositories\EnlistmentTimelineRepository;
use App\Repositories\RecruitmentOpeningRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;
use App\Services\Recruitment\EnlistmentAcceptanceProvisioningService;
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
        ]);
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
                $this->notifyCandidate((int) $tenantId, $fresh, $action, $detail);
            }
            Session::flash('success', $action === 'interview'
                ? 'Demande d’entretien enregistrée. Le dossier reste en instruction.'
                : 'Mise en attente enregistrée. Le dossier reste en instruction.');

            return Response::redirect(url('back-office/recruitments/' . $id));
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
        Session::flash('success', $messages[$map[$action]]);

        $this->enlistmentTimelineRepository->logDecision((int) $tenantId, $id, $userId, $map[$action], $comment);
        $fresh = $this->enlistmentRepository->findForTenant((int) $tenantId, $id);
        if (is_array($fresh)) {
            $this->notifyCandidate((int) $tenantId, $fresh, $action, $comment ?? '');
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
                Session::flash('success', 'Candidature acceptée. ' . $provision['message']);
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
    private function notifyCandidate(int $tenantId, array $enlistment, string $action, string $comment): void
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
        $token = $this->enlistmentRepository->ensureCandidatePortalToken($tenantId, (int) ($enlistment['id'] ?? 0), 24 * 7);
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
        $this->enlistmentRepository->appendCandidatePortalMessage($tenantId, (int) ($enlistment['id'] ?? 0), 'staff', $msgBody);

        $this->emailService->sendEnlistmentRecruitmentStatusCandidate(
            $email,
            $tenantName,
            $statusLabel,
            $commentStr,
            $portalUrl,
            $tenantId,
            $action,
            (int) ($enlistment['id'] ?? 0)
        );
    }
}
