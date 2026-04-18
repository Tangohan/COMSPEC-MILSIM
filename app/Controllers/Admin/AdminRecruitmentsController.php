<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\EnlistmentCannedMessageRepository;
use App\Repositories\EnlistmentRepository;
use App\Repositories\RecruitmentOpeningRepository;
use App\Repositories\TenantRepository;
use App\Services\Recruitment\EnlistmentAcceptanceProvisioningService;
use App\Services\Recruitment\TenantRecruitmentSettings;

class AdminRecruitmentsController
{
    public function __construct(
        private EnlistmentRepository $enlistmentRepository,
        private EnlistmentCannedMessageRepository $cannedMessageRepository,
        private EnlistmentAcceptanceProvisioningService $enlistmentAcceptanceProvisioningService,
        private TenantRepository $tenantRepository,
        private RecruitmentOpeningRepository $recruitmentOpeningRepository
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

        return Response::view('layout.main', [
            'content' => 'admin.recruitments.index',
            'title' => 'Candidatures',
            'enlistments' => $enlistments,
            'statusFilter' => $statusFilter,
            'enlistmentCounts' => $enlistmentCounts,
            'enlistmentSlaHours' => $slaHours,
            'submittedOlderThanSla' => $submittedOlderThanSla,
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

        return Response::view('layout.main', [
            'content' => 'admin.recruitments.show',
            'title' => 'Candidature #' . $id,
            'enlistment' => $row,
            'enlistmentCannedMessages' => $canned,
            'membershipRepairHint' => $this->enlistmentAcceptanceProvisioningService->membershipRepairHint((int) $tenantId, $row),
            'linkedRecruitmentOpening' => $linkedOpening,
            'communitySlug' => $communitySlug,
            'enlistmentSlaHours' => $slaHours,
            'showPortalFooter' => false,
        ]);
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
        Session::flash('success', 'SLA candidature mis à jour à ' . $slaHours . ' h.');

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

        return Response::view('layout.main', [
            'content' => 'admin.recruitments.settings',
            'title' => 'Paramètres recrutement',
            'enlistmentSlaHours' => $slaHours,
            'submittedCount' => count($submitted),
            'submittedOlderThanSla' => $breached,
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

        return Response::view('layout.main', [
            'content' => 'admin.recruitments.canned_messages',
            'title' => 'Messages préfaits — recrutement',
            'cannedMessages' => $rows,
            'cannedMessagesTableMissing' => !$this->cannedMessageRepository->tableExists(),
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
        if (!isset($map[$action])) {
            Session::flash('error', 'Décision inconnue.');

            return Response::redirect(url('back-office/recruitments/' . $id));
        }
        $comment = trim((string) $request->input('reviewer_comment', ''));
        $comment = $comment !== '' ? mb_substr($comment, 0, 4000) : null;

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

        if ($ok && $map[$action] === 'reviewed') {
            $provision = $this->enlistmentAcceptanceProvisioningService->provisionAfterAccept(
                (int) $tenantId,
                $id,
                $userId,
                $comment
            );
            if (!$provision['ok'] && $provision['message'] !== null && $provision['message'] !== '') {
                Session::flash('error', $provision['message']);
            } elseif ($provision['message'] !== null && $provision['message'] !== '') {
                Session::flash('success', 'Candidature acceptée. ' . $provision['message']);
            }
        }

        return Response::redirect(url('back-office/recruitments/' . $id));
    }
}
