<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\BlockedIndicatorRepository;
use App\Repositories\EnlistmentRepository;
use App\Repositories\EnlistmentTimelineRepository;
use App\Repositories\RecruitmentOpeningRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\EmailService;
use App\Services\Recruitment\TenantRecruitmentSettings;

class RecruitmentWorkspaceController
{
    public function __construct(
        private EnlistmentRepository $enlistmentRepository,
        private TenantRepository $tenantRepository,
        private RecruitmentOpeningRepository $recruitmentOpeningRepository,
        private EnlistmentTimelineRepository $enlistmentTimelineRepository,
        private BlockedIndicatorRepository $blockedIndicatorRepository,
        private AuthService $authService,
        private EmailService $emailService,
        private UserRepository $userRepository,
    ) {}

    public function dashboard(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        $counts = $this->enlistmentRepository->countsByStatusForTenant($tenantId);
        $tenantSettings = $this->tenantRepository->getSettings($tenantId);
        $slaHours = TenantRecruitmentSettings::enlistmentSlaHoursFromSettings($tenantSettings);
        $submittedOlderThanSla = $this->enlistmentRepository->countSubmittedExceedingSlaHours($tenantId, $slaHours);
        $via = $this->enlistmentRepository->countsBySubmittedViaForTenant($tenantId);
        $weeks = $this->enlistmentRepository->countsCreatedByWeekForTenant($tenantId, 12);
        $topOpenings = $this->recruitmentOpeningRepository->tablesExist()
            ? $this->enlistmentRepository->topLinkedOpeningsByVolume($tenantId, 8)
            : [];

        $portalRecruitmentBlocks = $this->blockedIndicatorRepository->listActiveTenantPortalRecruitmentRelated($tenantId, 200);
        $automodDossiers = $this->buildAutomodDossierSummaries($tenantId);

        return Response::view('layout.recruitment_lms', [
            'content' => 'admin.recruitment_workspace.dashboard',
            'title' => 'Bureau recrutement',
            'recruitmentLmsTitle' => 'Vue d’ensemble recrutement',
            'recruitmentAdminNav' => 'dashboard',
            'enlistmentCounts' => $counts,
            'recruitmentSidebarCounts' => $counts,
            'enlistmentSlaHours' => $slaHours,
            'submittedOlderThanSla' => $submittedOlderThanSla,
            'submittedViaCounts' => $via,
            'weeklyCreated' => $weeks,
            'topOpenings' => $topOpenings,
            'showPortalFooter' => false,
            'automodDossiers' => $automodDossiers,
            'portalRecruitmentBlocks' => $portalRecruitmentBlocks,
            'canOpenSystemRecruitmentTools' => Gate::getInstance()->allows('admin.system'),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildAutomodDossierSummaries(int $tenantId): array
    {
        $raw = $this->enlistmentTimelineRepository->listRecentPortalAutomodForTenant($tenantId, 100);
        $byEid = [];
        foreach ($raw as $r) {
            $eid = (int) ($r['enlistment_id'] ?? 0);
            if ($eid < 1) {
                continue;
            }
            if (!isset($byEid[$eid])) {
                $byEid[$eid] = $r;
            }
        }
        $rows = array_values($byEid);
        usort($rows, static function (array $a, array $b): int {
            return strcmp((string) ($b['mod_at'] ?? ''), (string) ($a['mod_at'] ?? ''));
        });
        $rows = array_slice($rows, 0, 45);
        foreach ($rows as &$row) {
            $meta = $row['metadata'] ?? null;
            $side = (string) (is_array($meta) ? ($meta['moderation_side'] ?? '') : '');
            $actorId = (int) ($row['timeline_actor_id'] ?? 0);
            $emailForBlock = strtolower(trim((string) ($row['email'] ?? '')));
            if ($side === 'equipe' && $actorId > 0) {
                $u = $this->userRepository->findById($actorId, $tenantId);
                if (is_array($u)) {
                    $staffMail = strtolower(trim((string) ($u['email'] ?? '')));
                    if ($staffMail !== '' && filter_var($staffMail, FILTER_VALIDATE_EMAIL)) {
                        $emailForBlock = $staffMail;
                    }
                }
            }
            $row['portal_email_blocked'] = $emailForBlock !== '' && filter_var($emailForBlock, FILTER_VALIDATE_EMAIL)
                && $this->blockedIndicatorRepository->isEmailBlockedForTenant($tenantId, $emailForBlock);
            $row['display_contact_email'] = $emailForBlock;
            $row['moderation_side_fr'] = match ($side) {
                'equipe' => 'Équipe recrutement',
                'candidat' => 'Candidat',
                default => '—',
            };
            $row['moderation_code'] = is_array($meta) ? trim((string) ($meta['moderation_code'] ?? '')) : '';
        }
        unset($row);

        $rows = array_values(array_filter($rows, static fn (array $r): bool => !empty($r['portal_email_blocked'])));

        return $rows;
    }

    public function automodRestoreAccess(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Rechargez la page puis réessayez.');

            return Response::redirect(recruitment_workspace_url());
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $actor = $this->authService->user();
        $actorId = is_array($actor) ? (int) ($actor['id'] ?? 0) : 0;
        $enlistmentId = (int) $request->input('enlistment_id');
        $alsoIp = (string) $request->input('also_revoke_ip', '0') === '1';
        if ($enlistmentId < 1) {
            Session::flash('error', 'Dossier invalide.');

            return Response::redirect(recruitment_workspace_url());
        }
        $row = $this->enlistmentRepository->findForTenant($tenantId, $enlistmentId);
        if (!$row) {
            Session::flash('error', 'Dossier introuvable.');

            return Response::redirect(recruitment_workspace_url());
        }
        $email = strtolower(trim((string) ($row['email'] ?? '')));
        $emailHash = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)
            ? BlockedIndicatorRepository::hashEmail($email)
            : '';
        $nEmail = $emailHash !== '' ? $this->blockedIndicatorRepository->revokeActiveTenantEmailHash($tenantId, $emailHash) : 0;
        $nIp = $alsoIp ? $this->blockedIndicatorRepository->revokeActiveTenantIpPortalCandidateViolations($tenantId) : 0;
        $lines = [];
        if ($nEmail > 0) {
            $lines[] = 'Blocage e-mail du dossier levé sur la communauté (' . $nEmail . ' entrée(s)).';
        } else {
            $lines[] = 'Aucun blocage e-mail actif trouvé pour l’adresse de ce dossier (déjà levé ou absent).';
        }
        if ($alsoIp) {
            $lines[] = $nIp > 0
                ? 'Blocages réseau « portail candidat » levés (' . $nIp . ' entrée(s)).'
                : 'Aucun blocage réseau « portail candidat » actif à lever.';
        }
        if ($this->enlistmentTimelineRepository->tableExists()) {
            $this->enlistmentTimelineRepository->append(
                $tenantId,
                $enlistmentId,
                'system',
                'portal',
                'Accès portail — rétablissement après modération automatique',
                implode("\n", $lines),
                $actorId > 0 ? $actorId : null,
                [
                    'timeline_family' => 'moderation_override',
                    'also_revoke_ip' => $alsoIp,
                ],
                null
            );
        }
        Session::flash('success', implode(' ', $lines));

        if ((string) $request->input('return_to_dossier', '0') === '1') {
            return Response::redirect(url('back-office/recruitments/' . $enlistmentId));
        }

        return Response::redirect(recruitment_workspace_url());
    }

    public function automodEscalateToPlatform(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Rechargez la page puis réessayez.');

            return Response::redirect(recruitment_workspace_url());
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $actor = $this->authService->user();
        $actorId = is_array($actor) ? (int) ($actor['id'] ?? 0) : 0;
        $actorEmail = is_array($actor) ? strtolower(trim((string) ($actor['email'] ?? ''))) : '';
        $enlistmentId = (int) $request->input('enlistment_id');
        $note = trim((string) $request->input('staff_note', ''));
        if (mb_strlen($note) > 2000) {
            $note = mb_substr($note, 0, 2000);
        }
        if ($enlistmentId < 1) {
            Session::flash('error', 'Dossier invalide.');

            return Response::redirect(recruitment_workspace_url());
        }
        $row = $this->enlistmentRepository->findForTenant($tenantId, $enlistmentId);
        if (!$row) {
            Session::flash('error', 'Dossier introuvable.');

            return Response::redirect(recruitment_workspace_url());
        }
        $trow = $this->tenantRepository->findById($tenantId);
        $tenantName = trim((string) ((is_array($trow) ? $trow : [])['name'] ?? ''));
        if ($tenantName === '') {
            $tenantName = 'Communauté n°' . $tenantId;
        }
        $assistUrl = url('admin/system/recruitment-portal-tools?' . http_build_query([
            'tenant_id' => $tenantId,
            'enlistment_id' => $enlistmentId,
        ]));
        $body = "Une équipe recrutement demande l’assistance site pour un dossier ayant subi la modération automatique du portail.\n\n"
            . "Communauté : {$tenantName} (identifiant {$tenantId})\n"
            . "Dossier : {$enlistmentId}\n"
            . "Lien direct vers l’outil assistance : {$assistUrl}\n";
        if ($actorEmail !== '') {
            $body .= "\nDemandeur (compte connecté) : {$actorEmail}\n";
        }
        if ($note !== '') {
            $body .= "\nMessage de l’équipe :\n" . $note . "\n";
        }
        $recipients = $this->userRepository->listActiveEmailsHavingPermissionGlobally('admin.system', 40);
        $extra = trim((string) (function_exists('env') ? env('RECRUITMENT_PLATFORM_ESCALATION_EMAIL', '') : ''));
        if ($extra !== '') {
            foreach (array_map('trim', explode(',', $extra)) as $em) {
                if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL)) {
                    $recipients[] = strtolower($em);
                }
            }
        }
        $recipients = array_values(array_unique($recipients));
        if ($recipients === []) {
            Session::flash('error', 'Aucun destinataire site n’a été trouvé. Ajoutez RECRUITMENT_PLATFORM_ESCALATION_EMAIL dans l’environnement ou assurez-vous qu’au moins un compte dispose de la permission d’administration site.');

            return Response::redirect(recruitment_workspace_url());
        }
        $sent = 0;
        foreach ($recipients as $to) {
            if ($this->emailService->sendSecurityAlert($to, 'info', 'Recrutement — escale modération automatique', $body, $tenantId)) {
                ++$sent;
            }
        }
        if ($this->enlistmentTimelineRepository->tableExists()) {
            $this->enlistmentTimelineRepository->append(
                $tenantId,
                $enlistmentId,
                'system',
                'portal',
                'Assistance site sollicitée (modération automatique)',
                'Notification envoyée aux équipes disposant de l’administration site (' . $sent . ' courriel(s)).',
                $actorId > 0 ? $actorId : null,
                [
                    'timeline_family' => 'platform_escalation',
                    'recipients_count' => $sent,
                ],
                null
            );
        }
        Session::flash('success', 'Votre demande a été transmise par courriel à l’équipe du site (' . $sent . ' envoi(s)).');

        return Response::redirect(recruitment_workspace_url());
    }

    public function analytics(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        $weeks = $this->enlistmentRepository->countsCreatedByWeekForTenant($tenantId, 12);
        $via = $this->enlistmentRepository->countsBySubmittedViaForTenant($tenantId);
        $statusCounts = $this->enlistmentRepository->countsByStatusForTenant($tenantId);
        $topOpenings = $this->recruitmentOpeningRepository->tablesExist()
            ? $this->enlistmentRepository->topLinkedOpeningsByVolume($tenantId, 15)
            : [];

        return Response::view('layout.recruitment_lms', [
            'content' => 'admin.recruitment_workspace.analytics',
            'title' => 'Analyses candidatures',
            'recruitmentLmsTitle' => 'Analyses candidatures',
            'recruitmentAdminNav' => 'analytics',
            'weeklyCreated' => $weeks,
            'submittedViaCounts' => $via,
            'enlistmentCounts' => $statusCounts,
            'recruitmentSidebarCounts' => $statusCounts,
            'topOpenings' => $topOpenings,
            'showPortalFooter' => false,
        ]);
    }
}
