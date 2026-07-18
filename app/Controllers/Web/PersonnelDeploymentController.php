<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CommunityEventRepository;
use App\Repositories\PersonnelDeploymentRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;

final class PersonnelDeploymentController
{
    public function __construct(
        private AuthService $authService,
        private UserRepository $userRepository,
        private PersonnelDeploymentRepository $deploymentRepository,
        private PersonnelProfileRepository $profileRepository,
        private TenantRepository $tenantRepository,
        private UnitRepository $unitRepository,
        private CommunityEventRepository $eventRepository,
        private EmailService $emailService
    ) {}

    private function canManageDeployments(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('personnel.profile.update') || $gate->allows('admin.organization') || $gate->allows('admin.access');
    }

    public function index(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        $tenantId = (int) Session::get('tenant_id');
        if (!$user || $tenantId < 1) {
            return Response::redirect(url('login'));
        }

        $this->deploymentRepository->ensureSchema();

        $isManager = $this->canManageDeployments();
        $search = trim((string) $request->query('q', ''));
        $campaignFilter = trim((string) $request->query('campagne', ''));
        $eventFilter = max(0, (int) $request->query('event_id', 0));
        $rows = $this->deploymentRepository->listDeployablePersonnel($tenantId, $isManager ? $search : '', $campaignFilter !== '' ? $campaignFilter : null, $eventFilter > 0 ? $eventFilter : null);

        if (!$isManager) {
            $rows = array_values(array_filter($rows, static fn (array $row): bool => (int) ($row['user_id'] ?? 0) === (int) ($user['id'] ?? 0)));
        }

        foreach ($rows as &$row) {
            $uid = (int) ($row['user_id'] ?? 0);
            $row['anomalies'] = $uid > 0 ? $this->deploymentRepository->listAnomalies($tenantId, $uid, 5) : [];
        }
        unset($row);

        $events = $this->eventRepository->upcomingForTenant($tenantId, 80);

        return Response::view('layout.main', [
            'title' => 'Déploiement du personnel',
            'content' => 'personnel.deployment',
            'deploymentRows' => $rows,
            'deploymentCanManage' => $isManager,
            'deploymentSearch' => $search,
            'deploymentCampaignFilter' => $campaignFilter,
            'deploymentEventFilter' => $eventFilter,
            'deploymentCampaignTags' => $this->deploymentRepository->listCampaignTagsForTenant($tenantId, 40),
            'deploymentEvents' => $events,
            'deploymentCsrf' => Csrf::token(),
        ]);
    }

    public function deploy(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        $tenantId = (int) Session::get('tenant_id');
        if (!$user || $tenantId < 1 || !$this->canManageDeployments()) {
            return Response::redirect(url('deploiement'));
        }
        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('deploiement'));
        }

        $targetUserId = (int) ($params['id'] ?? 0);
        $target = $this->userRepository->findById($targetUserId, $tenantId);
        if (!$target) {
            Session::flash('error', 'Personnel introuvable.');

            return Response::redirect(url('deploiement'));
        }

        $campaignTag = trim((string) $request->input('campaign_tag', ''));
        $eventId = max(0, (int) $request->input('event_id', 0));
        $event = $eventId > 0 ? $this->eventRepository->findByIdForTenant($eventId, $tenantId) : null;
        if ($eventId > 0 && $event === null) {
            Session::flash('error', 'Événement sélectionné introuvable.');

            return Response::redirect(url('deploiement'));
        }
        if ($campaignTag === '' && $event !== null) {
            $campaignTag = trim((string) ($event['campaign_tag'] ?? ''));
        }

        $profile = $this->profileRepository->getByUserId($targetUserId) ?? [];
        $missing = [];
        if ((int) ($profile['deployable'] ?? 1) !== 1) {
            $missing[] = 'profil marqué non déployable';
        }
        if (trim((string) ($profile['primary_role'] ?? '')) === '') {
            $missing[] = 'rôle principal';
        }
        if ((int) ($profile['primary_unit_id'] ?? 0) < 1) {
            $missing[] = 'unité principale';
        }
        if (trim((string) ($profile['matricule_internal'] ?? '')) === '') {
            $missing[] = 'matricule';
        }
        if (trim((string) ($profile['blood_type'] ?? '')) === '') {
            $missing[] = 'groupe sanguin';
        }
        if ($missing !== []) {
            Session::flash(
                'error',
                'Déploiement impossible — complètez d’abord : ' . implode(', ', $missing) . '. Ouvrez le dossier pour corriger.'
            );

            return Response::redirect(url('deploiement'));
        }

        $unit = (int) ($profile['primary_unit_id'] ?? 0) > 0 ? $this->unitRepository->findById((int) $profile['primary_unit_id'], $tenantId) : null;

        $this->deploymentRepository->upsertDeployment($tenantId, $targetUserId, (int) $user['id'], [
            'status' => 'deployed',
            'campaign_tag' => $campaignTag !== '' ? mb_substr($campaignTag, 0, 120) : null,
            'event_id' => $eventId > 0 ? $eventId : null,
            'blood_type' => trim((string) ($profile['blood_type'] ?? '')) ?: null,
            'matricule' => trim((string) ($profile['matricule_internal'] ?? '')) ?: null,
            'assignment_label' => trim((string) (($unit['name'] ?? '') ?: ($profile['primary_role'] ?? ''))) ?: null,
        ]);

        if ($event !== null) {
            $this->eventRepository->setRsvp((int) $event['id'], $targetUserId, 'yes');
        }

        $tenant = $this->tenantRepository->findById($tenantId);
        $tenantName = trim((string) ($tenant['name'] ?? 'Votre communauté'));
        $link = url('deploiement');

        $subject = 'Vous êtes déployé — check-up requis';
        if ($campaignTag !== '') {
            $subject .= ' [' . $campaignTag . ']';
        }

        $this->emailService->sendTemplated(
            EmailEvents::PERSONNEL_DEPLOYMENT_ASSIGNED,
            'personnel_deployment_assigned',
            (string) ($target['email'] ?? ''),
            $subject,
            [
                'displayName' => (string) ($target['display_name'] ?? 'Opérateur'),
                'tenantName' => $tenantName,
                'deploymentUrl' => $link,
                'campaignTag' => $campaignTag,
                'eventTitle' => (string) ($event['title'] ?? ''),
            ],
            $tenantId,
            null,
            ['purpose' => 'personnel_deployment_assigned', 'user_id' => $targetUserId, 'campaign_tag' => $campaignTag, 'event_id' => $eventId]
        );

        Session::flash('success', 'Personnel déployé. Liaison campagne/événement enregistrée et e-mail envoyé.');

        return Response::redirect(url('deploiement'));
    }

    public function saveCheckup(Request $request, array $params = []): Response
    {
        $actor = $this->authService->user();
        $tenantId = (int) Session::get('tenant_id');
        if (!$actor || $tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('deploiement'));
        }

        $targetUserId = (int) ($params['id'] ?? 0);
        $isManager = $this->canManageDeployments();
        if (!$isManager && $targetUserId !== (int) ($actor['id'] ?? 0)) {
            return Response::redirect(url('deploiement'));
        }

        $target = $this->userRepository->findById($targetUserId, $tenantId);
        if (!$target) {
            Session::flash('error', 'Utilisateur introuvable.');

            return Response::redirect(url('deploiement'));
        }

        $weight = trim((string) $request->input('weight_kg', ''));
        $assignment = trim((string) $request->input('assignment_label', ''));
        $bloodType = trim((string) $request->input('blood_type', ''));
        $matricule = trim((string) $request->input('matricule', ''));
        $campaignTag = trim((string) $request->input('campaign_tag', ''));
        $eventId = max(0, (int) $request->input('event_id', 0));

        $data = [
            'status' => 'deployed',
            'campaign_tag' => $campaignTag !== '' ? mb_substr($campaignTag, 0, 120) : null,
            'event_id' => $eventId > 0 ? $eventId : null,
            'mods_up_to_date' => (int) $request->input('mods_up_to_date', 0) === 1,
            'role_qualified_authorized' => (int) $request->input('role_qualified_authorized', 0) === 1,
            'recycling_alpha_bravo_up_to_date' => (int) $request->input('recycling_alpha_bravo_up_to_date', 0) === 1,
            'vmp_up_to_date' => (int) $request->input('vmp_up_to_date', 0) === 1,
            'last_interview_done' => (int) $request->input('last_interview_done', 0) === 1,
            'weight_kg' => $weight !== '' ? max(0.0, min(350.0, (float) str_replace(',', '.', $weight))) : null,
            'blood_type' => $bloodType !== '' ? mb_substr($bloodType, 0, 12) : null,
            'matricule' => $matricule !== '' ? mb_substr($matricule, 0, 80) : null,
            'assignment_label' => $assignment !== '' ? mb_substr($assignment, 0, 160) : null,
            'checkup_notes' => trim((string) $request->input('checkup_notes', '')) ?: null,
        ];

        $this->deploymentRepository->upsertDeployment($tenantId, $targetUserId, (int) $actor['id'], $data);

        if ($eventId > 0) {
            $event = $this->eventRepository->findByIdForTenant($eventId, $tenantId);
            if ($event !== null) {
                $this->eventRepository->setRsvp((int) $event['id'], $targetUserId, 'yes');
            }
        }

        // Mise à jour autonome de certaines données de dossier personnel.
        $this->profileRepository->update($targetUserId, [
            'blood_type' => $data['blood_type'],
            'matricule_internal' => $data['matricule'],
        ]);

        $isValid = $data['mods_up_to_date']
            && $data['role_qualified_authorized']
            && $data['recycling_alpha_bravo_up_to_date']
            && $data['vmp_up_to_date']
            && $data['last_interview_done']
            && $data['weight_kg'] !== null
            && $data['blood_type'] !== null
            && $data['matricule'] !== null
            && $data['assignment_label'] !== null;

        if ($isValid) {
            $this->deploymentRepository->validateCheckup($tenantId, $targetUserId, (int) $actor['id']);
            $tenant = $this->tenantRepository->findById($tenantId);
            $tenantName = trim((string) ($tenant['name'] ?? 'Votre communauté'));

            $recipients = array_unique(array_filter(array_merge(
                [(string) ($target['email'] ?? '')],
                $this->userRepository->listGovernanceEmailsForTenant($tenantId)
            )));

            foreach ($recipients as $to) {
                $this->emailService->sendTemplated(
                    EmailEvents::PERSONNEL_DEPLOYMENT_CHECKUP_VALIDATED,
                    'personnel_deployment_checkup_validated',
                    $to,
                    'Check-up de déploiement validé — ' . (string) ($target['display_name'] ?? 'Opérateur'),
                    [
                        'displayName' => (string) ($target['display_name'] ?? 'Opérateur'),
                        'tenantName' => $tenantName,
                        'deploymentUrl' => url('deploiement'),
                        'campaignTag' => $campaignTag,
                    ],
                    $tenantId,
                    null,
                    ['purpose' => 'personnel_deployment_checkup_validated', 'user_id' => $targetUserId, 'campaign_tag' => $campaignTag, 'event_id' => $eventId]
                );
            }

            Session::flash('success', 'Check-up complet et validé. Notifications e-mail envoyées.');
        } else {
            Session::flash('success', 'Check-up enregistré. Complétez les champs obligatoires pour validation.');
        }

        return Response::redirect(url('deploiement'));
    }

    public function reportAnomaly(Request $request, array $params = []): Response
    {
        $actor = $this->authService->user();
        $tenantId = (int) Session::get('tenant_id');
        if (!$actor || $tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('deploiement'));
        }

        $targetUserId = (int) ($params['id'] ?? 0);
        if (!$this->canManageDeployments() && $targetUserId !== (int) ($actor['id'] ?? 0)) {
            return Response::redirect(url('deploiement'));
        }

        $message = trim((string) $request->input('anomaly_message', ''));
        if ($message === '') {
            Session::flash('error', 'Merci de décrire l’anomalie.');

            return Response::redirect(url('deploiement'));
        }

        $this->deploymentRepository->createAnomaly($tenantId, $targetUserId, (int) ($actor['id'] ?? 0), mb_substr($message, 0, 3000));
        Session::flash('success', 'Anomalie signalée à l’encadrement.');

        return Response::redirect(url('deploiement'));
    }
}
