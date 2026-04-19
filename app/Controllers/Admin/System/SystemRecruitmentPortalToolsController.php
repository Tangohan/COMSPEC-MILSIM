<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\BlockedIndicatorRepository;
use App\Repositories\EnlistmentRepository;
use App\Repositories\EnlistmentTimelineRepository;
use App\Repositories\PlatformSettingsRepository;
use App\Repositories\TenantRepository;
use App\Services\Auth\AuthService;
use App\Services\EmailService;
use App\Services\Moderation\IndicatorBlocklistService;
use App\Services\Recruitment\EnlistmentPortalAutoModerationCoordinator;

/**
 * Hub plateforme : assistance portail recrutement (réouverture après modération auto, blocages, mails d’alerte).
 */
final class SystemRecruitmentPortalToolsController
{
    public function __construct(
        private AuthService $authService,
        private BlockedIndicatorRepository $blockedIndicatorRepository,
        private IndicatorBlocklistService $indicatorBlocklistService,
        private EnlistmentRepository $enlistmentRepository,
        private TenantRepository $tenantRepository,
        private EmailService $emailService,
        private EnlistmentTimelineRepository $enlistmentTimelineRepository,
        private PlatformSettingsRepository $platformSettingsRepository,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = max(0, (int) $request->query('tenant_id', 0));
        $enlistmentId = max(0, (int) $request->query('enlistment_id', 0));

        $tenantIdsPortalBlock = $this->blockedIndicatorRepository->distinctTenantIdsWithActivePortalRecruitmentBlocks(300);
        $portalBlockSet = array_fill_keys($tenantIdsPortalBlock, true);

        $tenantRows = $this->tenantRepository->listBasicAll();
        usort($tenantRows, static function (array $a, array $b) use ($portalBlockSet): int {
            $ida = (int) ($a['id'] ?? 0);
            $idb = (int) ($b['id'] ?? 0);
            $ha = isset($portalBlockSet[$ida]);
            $hb = isset($portalBlockSet[$idb]);
            if ($ha !== $hb) {
                return $ha ? -1 : 1;
            }

            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        $enlistmentSummaries = [];
        if ($tenantId > 0) {
            $enlistmentSummaries = $this->enlistmentRepository->listPortalAssistSelectSummariesForTenant($tenantId, 400);
            $seenIds = [];
            foreach ($enlistmentSummaries as $r) {
                $seenIds[(int) ($r['id'] ?? 0)] = true;
            }
            if ($enlistmentId > 0 && !isset($seenIds[$enlistmentId])) {
                $one = $this->enlistmentRepository->findForTenant($tenantId, $enlistmentId);
                if ($one !== null) {
                    array_unshift($enlistmentSummaries, [
                        'id' => (int) ($one['id'] ?? 0),
                        'email' => (string) ($one['email'] ?? ''),
                        'status' => (string) ($one['status'] ?? ''),
                        'first_name' => (string) ($one['first_name'] ?? ''),
                        'last_name' => (string) ($one['last_name'] ?? ''),
                    ]);
                }
            }
        }

        $lookup = $this->buildLookupPayload($tenantId, $enlistmentId);
        $automodMailEnabled = $this->platformSettingsRepository->getBool(EnlistmentPortalAutoModerationCoordinator::SETTING_AUTOMOD_ALERT_EMAILS_ENABLED, true);

        return Response::view('layout.main', [
            'title' => 'Portail recrutement — modération & accès',
            'content' => 'admin.system.recruitment_portal_tools',
            'lookup' => $lookup,
            'automodMailEnabled' => $automodMailEnabled,
            'automodMailSettingKey' => EnlistmentPortalAutoModerationCoordinator::SETTING_AUTOMOD_ALERT_EMAILS_ENABLED,
            'tenantSelectRows' => $tenantRows,
            'tenantIdsWithPortalBlocks' => $tenantIdsPortalBlock,
            'enlistmentSelectRows' => $enlistmentSummaries,
        ]);
    }

    public function saveAutomodMailSetting(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/recruitment-portal-tools'));
        }
        $enabled = (string) $request->input('automod_alerts_enabled', '0') === '1';
        $this->platformSettingsRepository->setMany([
            EnlistmentPortalAutoModerationCoordinator::SETTING_AUTOMOD_ALERT_EMAILS_ENABLED => $enabled ? '1' : '0',
        ]);
        Session::flash('success', $enabled
            ? 'Les courriels d’alerte modération automatique du portail recrutement sont activés sur la plateforme.'
            : 'Les courriels d’alerte modération automatique du portail recrutement sont désactivés sur la plateforme (les blocages restent appliqués).');

        return Response::redirect(url('admin/system/recruitment-portal-tools'));
    }

    public function revokeIndicator(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/recruitment-portal-tools'));
        }
        $actor = $this->authService->user();
        if (!$actor) {
            return Response::redirect(url('login'));
        }
        $indicatorId = (int) $request->input('indicator_id');
        $tenantId = (int) $request->input('tenant_id');
        if ($indicatorId < 1 || $tenantId < 1) {
            Session::flash('error', 'Paramètres invalides.');

            return Response::redirect(url('admin/system/recruitment-portal-tools'));
        }
        $row = $this->blockedIndicatorRepository->findById($indicatorId);
        if (!is_array($row) || (string) ($row['scope'] ?? '') !== 'tenant' || (int) ($row['tenant_id'] ?? 0) !== $tenantId) {
            Session::flash('error', 'Entrée introuvable ou hors périmètre communauté.');

            return Response::redirect(url('admin/system/recruitment-portal-tools'));
        }
        if ($this->indicatorBlocklistService->revokeIndicator((int) $actor['id'], $indicatorId, $tenantId)) {
            Session::flash('success', 'Blocage levé.');
        } else {
            Session::flash('error', 'Impossible de lever ce blocage (déjà clos ?).');
        }

        return Response::redirect(url('admin/system/recruitment-portal-tools?' . http_build_query([
            'tenant_id' => $tenantId,
            'enlistment_id' => max(0, (int) $request->input('return_enlistment_id')),
        ])));
    }

    public function reopenEnlistmentPortal(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/recruitment-portal-tools'));
        }
        $actor = $this->authService->user();
        if (!$actor) {
            return Response::redirect(url('login'));
        }
        $actorId = (int) $actor['id'];
        $tenantId = (int) $request->input('tenant_id');
        $enlistmentId = (int) $request->input('enlistment_id');
        $alsoIp = (string) $request->input('also_revoke_ip_candidate', '0') === '1';
        $refreshToken = (string) $request->input('refresh_token_and_email', '0') === '1';
        if ($tenantId < 1 || $enlistmentId < 1) {
            Session::flash('error', 'Indiquez un identifiant de communauté et de dossier valides.');

            return Response::redirect(url('admin/system/recruitment-portal-tools'));
        }
        $row = $this->enlistmentRepository->findForTenant($tenantId, $enlistmentId);
        if (!$row) {
            Session::flash('error', 'Dossier introuvable pour cette communauté.');

            return Response::redirect(url('admin/system/recruitment-portal-tools'));
        }
        $email = strtolower(trim((string) ($row['email'] ?? '')));
        $emailHash = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)
            ? BlockedIndicatorRepository::hashEmail($email)
            : '';
        $nEmail = $emailHash !== '' ? $this->blockedIndicatorRepository->revokeActiveTenantEmailHash($tenantId, $emailHash) : 0;
        $nIp = $alsoIp ? $this->blockedIndicatorRepository->revokeActiveTenantIpPortalCandidateViolations($tenantId) : 0;
        $lines = [];
        if ($nEmail > 0) {
            $lines[] = 'Blocage e-mail dossier levé (' . $nEmail . ' entrée(s)).';
        }
        if ($alsoIp && $nIp > 0) {
            $lines[] = 'Blocages réseau « portail candidat » levés (' . $nIp . ' entrée(s)).';
        }
        if ($lines === []) {
            $lines[] = 'Aucun blocage actif correspondant n’a été trouvé (e-mail dossier ou critères IP).';
        }
        $mailOk = false;
        if ($refreshToken && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $trow = $this->tenantRepository->findById($tenantId);
            $tenantName = trim((string) ((is_array($trow) ? $trow : [])['name'] ?? ''));
            if ($tenantName === '') {
                $tenantName = 'Communauté';
            }
            $token = $this->enlistmentRepository->ensureCandidatePortalToken($tenantId, $enlistmentId, 24 * 7);
            $portalUrl = $token !== null ? url('enlistment/suivi/' . rawurlencode($token)) : url('enlistment');
            $statusLabel = 'Mise à jour — lien de suivi';
            $comment = "Accès au suivi en ligne rétabli par l’assistance plateforme.\n\nLien de suivi : " . $portalUrl;
            $msgBody = 'Statut : ' . $statusLabel . "\n\n" . $comment;
            $this->enlistmentRepository->appendCandidatePortalMessage($tenantId, $enlistmentId, 'staff', $msgBody);
            $mailOk = $this->emailService->sendEnlistmentRecruitmentStatusCandidate(
                $email,
                $tenantName,
                $statusLabel,
                $comment,
                $portalUrl,
                $tenantId,
                'pending',
                $enlistmentId
            );
            if ($mailOk) {
                $lines[] = 'Jeton de suivi régénéré ou prolongé et courriel envoyé au candidat.';
            } else {
                $err = $this->emailService->getLastSendError();
                $lines[] = $err !== null && $err !== ''
                    ? ('Jeton de suivi régénéré ou prolongé ; courriel non envoyé : ' . $err)
                    : 'Jeton de suivi régénéré ou prolongé ; l’envoi du courriel a échoué ou est désactivé.';
            }
        }
        if ($this->enlistmentTimelineRepository->tableExists()) {
            $this->enlistmentTimelineRepository->append(
                $tenantId,
                $enlistmentId,
                'system',
                'portal',
                'Assistance site — réouverture portail après modération',
                implode("\n", $lines),
                $actorId > 0 ? $actorId : null,
                [
                    'timeline_family' => 'platform_assist',
                    'also_revoke_ip' => $alsoIp,
                    'refresh_token' => $refreshToken,
                    'mail_ok' => $mailOk,
                ],
                null
            );
        }
        Session::flash('success', implode(' ', $lines));

        return Response::redirect(url('admin/system/recruitment-portal-tools?' . http_build_query([
            'tenant_id' => $tenantId,
            'enlistment_id' => $enlistmentId,
        ])));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLookupPayload(int $tenantId, int $enlistmentId): array
    {
        $out = [
            'tenant_id' => $tenantId,
            'enlistment_id' => $enlistmentId,
            'tenant_name' => null,
            'enlistment' => null,
            'portal_blocks' => [],
            'error' => null,
        ];
        if ($tenantId < 1 || $enlistmentId < 1) {
            return $out;
        }
        $t = $this->tenantRepository->findById($tenantId);
        if (!$t) {
            $out['error'] = 'Communauté introuvable.';

            return $out;
        }
        $out['tenant_name'] = trim((string) ($t['name'] ?? '')) ?: ('Communauté n°' . $tenantId);
        $e = $this->enlistmentRepository->findForTenant($tenantId, $enlistmentId);
        if (!$e) {
            $out['error'] = 'Dossier introuvable pour cette communauté.';

            return $out;
        }
        $out['enlistment'] = $e;
        $out['portal_blocks'] = $this->blockedIndicatorRepository->listActiveTenantPortalRecruitmentRelated($tenantId, 120);

        return $out;
    }
}
