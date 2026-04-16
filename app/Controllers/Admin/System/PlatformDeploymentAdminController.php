<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\DeploymentCampaignRepository;
use App\Repositories\PlatformModuleReleaseRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Platform\DeploymentCampaignProcessor;
use App\Services\Platform\DeploymentChannelReleaseService;
use App\Support\Audit\AuditFieldSnapshot;

final class PlatformDeploymentAdminController
{
    public function __construct(
        private PlatformModuleReleaseRepository $repo,
        private UserRepository $userRepository,
        private ?AuditService $auditService = null,
        private ?DeploymentCampaignRepository $campaignRepository = null,
        private ?DeploymentCampaignProcessor $campaignProcessor = null,
        private ?DeploymentChannelReleaseService $channelReleaseService = null,
    ) {
        $this->auditService ??= new AuditService();
        $this->campaignRepository ??= new DeploymentCampaignRepository();
        $this->channelReleaseService ??= new DeploymentChannelReleaseService($this->repo, $this->auditService);
        $this->campaignProcessor ??= new DeploymentCampaignProcessor($this->campaignRepository, $this->channelReleaseService);
    }

    private function auditActorId(): ?int
    {
        $u = Session::get('user_id');

        return $u ? (int) $u : null;
    }

    private function auditTesterMemberAdded(int $communityId, int $userId, ?string $context = null): void
    {
        $aid = $this->auditActorId();
        if ($aid === null || $communityId < 1 || $userId < 1) {
            return;
        }
        $payload = ['user_id' => $userId];
        if ($context !== null && $context !== '') {
            $payload['contexte'] = $context;
        }
        $this->auditService->logChange(
            AuditAction::DEPLOYMENT_TESTER_MEMBER_ADDED,
            null,
            $aid,
            'tester_community',
            $communityId,
            [],
            $payload,
        );
    }

    private function auditTesterMemberRemoved(int $communityId, int $userId): void
    {
        $aid = $this->auditActorId();
        if ($aid === null || $communityId < 1 || $userId < 1) {
            return;
        }
        $this->auditService->logChange(
            AuditAction::DEPLOYMENT_TESTER_MEMBER_REMOVED,
            null,
            $aid,
            'tester_community',
            $communityId,
            ['user_id' => $userId],
            [],
        );
    }

    public function index(Request $request, array $params = []): Response
    {
        if (!$this->repo->schemaReady()) {
            Session::flash('error', 'Les tables de déploiement ne sont pas présentes. Exécutez les migrations prévues pour cette fonctionnalité.');

            return Response::view('layout.main', [
                'title' => 'Déploiement et préqualification',
                'content' => 'admin.system.deployment_index',
                'deploymentSchemaReady' => false,
                'deploymentChannels' => [],
                'deploymentMatrix' => [],
                'deploymentModules' => [],
                'deploymentCampaignSchemaReady' => false,
            ]);
        }

        $channels = $this->repo->listDeploymentChannels();
        $releases = $this->repo->listCurrentReleasesAllModules();
        $matrix = [];
        foreach ($releases as $row) {
            $mid = (int) ($row['module_id'] ?? 0);
            if ($mid < 1) {
                continue;
            }
            $code = strtoupper(trim((string) ($row['channel_code'] ?? '')));
            if (!isset($matrix[$mid])) {
                $matrix[$mid] = [
                    'module_name' => (string) ($row['module_name'] ?? ''),
                    'channels' => [],
                ];
            }
            if ($code !== '') {
                $matrix[$mid]['channels'][$code] = (string) ($row['version'] ?? '');
            }
        }
        foreach ($this->repo->listPlatformModules() as $m) {
            $mid = (int) ($m['id'] ?? 0);
            if ($mid < 1) {
                continue;
            }
            if (!isset($matrix[$mid])) {
                $matrix[$mid] = [
                    'module_name' => (string) ($m['name'] ?? ''),
                    'channels' => [],
                ];
            }
        }

        return Response::view('layout.main', [
            'title' => 'Déploiement et préqualification',
            'content' => 'admin.system.deployment_index',
            'deploymentSchemaReady' => true,
            'deploymentChannels' => $channels,
            'deploymentMatrix' => $matrix,
            'deploymentModules' => $this->repo->listPlatformModules(),
            'deploymentCsrf' => Csrf::token(),
            'deploymentCampaignSchemaReady' => $this->campaignRepository->schemaReady(),
        ]);
    }

    public function modulesStore(Request $request, array $params = []): Response
    {
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, réessayez.');

            return Response::redirect(url('admin/system/deployment'));
        }
        if (!$this->repo->schemaReady()) {
            return Response::redirect(url('admin/system/deployment'));
        }
        $code = strtoupper(trim((string) $request->input('code', '')));
        $name = trim((string) $request->input('name', ''));
        if ($code === '' || strlen($code) > 120 || $name === '' || !preg_match('/^[A-Z0-9_-]+$/', $code)) {
            Session::flash('error', 'Indiquez une référence technique (lettres majuscules, chiffres, tirets) et un nom lisible.');

            return Response::redirect(url('admin/system/deployment'));
        }
        if ($this->repo->findModuleByCode($code) !== null) {
            Session::flash('error', 'Cette référence technique est déjà utilisée.');

            return Response::redirect(url('admin/system/deployment'));
        }
        $desc = trim((string) $request->input('description', ''));
        try {
            $newId = $this->repo->insertPlatformModule($code, $name, $desc !== '' ? $desc : null, true, false);
            Session::flash('success', 'Fonctionnalité déployable enregistrée.');
            $aid = $this->auditActorId();
            if ($aid !== null && $newId > 0) {
                $this->auditService->logChange(
                    AuditAction::DEPLOYMENT_MODULE_CREATED,
                    null,
                    $aid,
                    'platform_module',
                    $newId,
                    [],
                    ['code' => $code, 'name' => $name, 'description' => $desc !== '' ? $desc : null],
                );
            }
        } catch (\Throwable) {
            Session::flash('error', 'Enregistrement impossible.');
        }

        return Response::redirect(url('admin/system/deployment'));
    }

    public function moduleShow(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $mod = $this->repo->findModuleById($id);
        if ($mod === null) {
            Session::flash('error', 'Élément introuvable.');

            return Response::redirect(url('admin/system/deployment'));
        }
        $versions = $this->repo->listModuleVersions($id);
        $channels = $this->repo->listDeploymentChannels();
        $releasesByChannel = $this->repo->findCurrentReleasesByChannelForModule($id);
        $rules = $this->repo->listAccessRulesForModule($id);
        $communities = $this->repo->listTesterCommunities();

        return Response::view('layout.main', [
            'title' => 'Fonctionnalité — ' . (string) ($mod['name'] ?? ''),
            'content' => 'admin.system.deployment_module',
            'deploymentModule' => $mod,
            'deploymentVersions' => $versions,
            'deploymentChannels' => $channels,
            'deploymentCurrentReleases' => $releasesByChannel,
            'deploymentAccessRules' => $rules,
            'deploymentTesterCommunities' => $communities,
            'deploymentCsrf' => Csrf::token(),
            'deploymentVersionStatusLabels' => self::versionStatusLabels(),
            'deploymentRuleTypeLabels' => self::ruleTypeLabels(),
        ]);
    }

    public function moduleUpdate(Request $request, array $params = []): Response
    {
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/deployment'));
        }
        $id = (int) ($params['id'] ?? 0);
        $mod = $this->repo->findModuleById($id);
        if ($mod === null) {
            return Response::redirect(url('admin/system/deployment'));
        }
        $name = trim((string) $request->input('name', ''));
        $desc = trim((string) $request->input('description', ''));
        $isActive = $request->input('is_active') === '1';
        $isPublic = $request->input('is_public') === '1';
        if ($name === '') {
            Session::flash('error', 'Le nom affiché est obligatoire.');

            return Response::redirect(url('admin/system/deployment/modules/' . $id));
        }
        $before = [
            'name' => (string) ($mod['name'] ?? ''),
            'description' => (string) ($mod['description'] ?? ''),
            'is_active' => (bool) ($mod['is_active'] ?? false),
            'is_public' => (bool) ($mod['is_public'] ?? false),
        ];
        $after = [
            'name' => $name,
            'description' => $desc !== '' ? $desc : '',
            'is_active' => $isActive,
            'is_public' => $isPublic,
        ];
        try {
            $this->repo->updatePlatformModule($id, $name, $desc !== '' ? $desc : null, $isActive, $isPublic);
            Session::flash('success', 'Modifications enregistrées.');
            $aid = $this->auditActorId();
            if ($aid !== null) {
                [$o, $n] = AuditFieldSnapshot::diffOnly($before, $after, ['name', 'description', 'is_active', 'is_public']);
                $this->auditService->logChange(
                    AuditAction::DEPLOYMENT_MODULE_UPDATED,
                    null,
                    $aid,
                    'platform_module',
                    $id,
                    $o,
                    $n,
                );
            }
        } catch (\Throwable) {
            Session::flash('error', 'Mise à jour impossible.');
        }

        return Response::redirect(url('admin/system/deployment/modules/' . $id));
    }

    public function moduleVersionStore(Request $request, array $params = []): Response
    {
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/deployment'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($this->repo->findModuleById($id) === null) {
            return Response::redirect(url('admin/system/deployment'));
        }
        $version = trim((string) $request->input('version', ''));
        $status = trim((string) $request->input('status', 'draft'));
        $allowed = ['draft', 'validated', 'published', 'rollback_ready', 'deprecated'];
        if (!in_array($status, $allowed, true) || $version === '' || strlen($version) > 80) {
            Session::flash('error', 'Version ou état non valide.');

            return Response::redirect(url('admin/system/deployment/modules/' . $id));
        }
        $uid = Session::get('user_id') ? (int) Session::get('user_id') : null;
        try {
            $vid = $this->repo->insertModuleVersion($id, $version, $status, $uid);
            Session::flash('success', 'Nouvelle version créée.');
            if ($uid !== null && $vid > 0) {
                $this->auditService->logChange(
                    AuditAction::DEPLOYMENT_VERSION_CREATED,
                    null,
                    $uid,
                    'platform_module',
                    $id,
                    [],
                    ['module_version_id' => $vid, 'version' => $version, 'status' => $status],
                );
            }
        } catch (\Throwable) {
            Session::flash('error', 'Création impossible (doublon de version ?).');
        }

        return Response::redirect(url('admin/system/deployment/modules/' . $id));
    }

    public function releaseSet(Request $request, array $params = []): Response
    {
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/deployment'));
        }
        $moduleId = (int) $request->input('module_id', 0);
        $channelId = (int) $request->input('channel_id', 0);
        $versionId = (int) $request->input('module_version_id', 0);
        $ver = $this->repo->findVersionById($versionId);
        if ($ver === null || (int) ($ver['module_id'] ?? 0) !== $moduleId) {
            Session::flash('error', 'Version incompatible avec cette fonctionnalité.');

            return Response::redirect(url('admin/system/deployment/modules/' . max(1, $moduleId)));
        }
        if ($this->repo->findChannelById($channelId) === null) {
            Session::flash('error', 'Canal inconnu.');

            return Response::redirect(url('admin/system/deployment/modules/' . $moduleId));
        }
        $uid = Session::get('user_id') ? (int) Session::get('user_id') : null;
        try {
            $this->channelReleaseService->publishVersionOnChannel($moduleId, $channelId, $versionId, $uid);
            Session::flash('success', 'Publication sur le canal mise à jour.');
        } catch (\Throwable) {
            Session::flash('error', 'Mise à jour de la publication impossible.');
        }

        return Response::redirect(url('admin/system/deployment/modules/' . $moduleId));
    }

    public function campaignsIndex(Request $request, array $params = []): Response
    {
        if (!$this->repo->schemaReady() || !$this->campaignRepository->schemaReady()) {
            Session::flash('error', 'Les campagnes de publication ne sont pas disponibles sur cette base. Exécutez les migrations prévues.');

            return Response::redirect(url('admin/system/deployment'));
        }

        return Response::view('layout.main', [
            'title' => 'Campagnes de publication',
            'content' => 'admin.system.deployment_campaigns_index',
            'deploymentCampaigns' => $this->campaignRepository->listCampaignsRecent(50),
            'deploymentCsrf' => Csrf::token(),
            'deploymentCampaignStatusLabels' => self::campaignStatusLabels(),
        ]);
    }

    public function campaignsNew(Request $request, array $params = []): Response
    {
        if (!$this->repo->schemaReady() || !$this->campaignRepository->schemaReady()) {
            Session::flash('error', 'Les campagnes de publication ne sont pas disponibles sur cette base.');

            return Response::redirect(url('admin/system/deployment'));
        }
        $moduleId = (int) $request->query('module_id', 0);
        $modules = $this->repo->listPlatformModules();
        $versions = $moduleId > 0 && $this->repo->findModuleById($moduleId) !== null
            ? $this->repo->listModuleVersions($moduleId)
            : [];
        $channels = $this->repo->listDeploymentChannels();

        return Response::view('layout.main', [
            'title' => 'Nouvelle campagne de publication',
            'content' => 'admin.system.deployment_campaign_new',
            'deploymentCampaignModules' => $modules,
            'deploymentCampaignSelectedModuleId' => $moduleId,
            'deploymentCampaignVersions' => $versions,
            'deploymentCampaignChannels' => $channels,
            'deploymentCsrf' => Csrf::token(),
            'deploymentVersionStatusLabels' => self::versionStatusLabels(),
        ]);
    }

    public function campaignsStore(Request $request, array $params = []): Response
    {
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, réessayez.');

            return Response::redirect(url('admin/system/deployment/campaigns/nouveau'));
        }
        if (!$this->repo->schemaReady() || !$this->campaignRepository->schemaReady()) {
            return Response::redirect(url('admin/system/deployment'));
        }
        $moduleId = (int) $request->input('module_id', 0);
        $versionId = (int) $request->input('module_version_id', 0);
        $rawChannels = (array) $request->input('channel_ids', []);
        $channelIds = array_values(array_unique(array_filter(array_map('intval', $rawChannels), static fn (int $v): bool => $v > 0)));
        $mod = $this->repo->findModuleById($moduleId);
        $ver = $this->repo->findVersionById($versionId);
        if ($mod === null || $ver === null || (int) ($ver['module_id'] ?? 0) !== $moduleId) {
            Session::flash('error', 'Choisissez une fonctionnalité et une version compatibles.');

            return Response::redirect(url('admin/system/deployment/campaigns/nouveau'));
        }
        if ($channelIds === []) {
            Session::flash('error', 'Sélectionnez au moins un environnement cible pour cette campagne.');

            return Response::redirect(url('admin/system/deployment/campaigns/nouveau?module_id=' . $moduleId));
        }
        $priorityById = [];
        foreach ($this->repo->listDeploymentChannels() as $ch) {
            $cid = (int) ($ch['id'] ?? 0);
            if ($cid > 0) {
                $priorityById[$cid] = (int) ($ch['priority'] ?? 0);
            }
        }
        foreach ($channelIds as $cid) {
            if (!isset($priorityById[$cid])) {
                Session::flash('error', 'Un des environnements sélectionnés n’est pas reconnu.');

                return Response::redirect(url('admin/system/deployment/campaigns/nouveau?module_id=' . $moduleId));
            }
        }
        usort($channelIds, static fn (int $a, int $b): int => ($priorityById[$a] ?? 0) <=> ($priorityById[$b] ?? 0));
        $channelIdsOrdered = $channelIds;
        $uid = Session::get('user_id') ? (int) Session::get('user_id') : null;
        try {
            $campaignId = $this->campaignRepository->createCampaignWithJobs($moduleId, $versionId, $uid, $channelIdsOrdered);
            Session::flash('success', 'Campagne enregistrée. Vous pouvez maintenant exécuter les étapes une par une ou par lots.');
            if ($uid !== null && $campaignId > 0) {
                $chNames = [];
                foreach ($channelIdsOrdered as $cid) {
                    $row = $this->repo->findChannelById($cid);
                    if ($row !== null) {
                        $chNames[] = (string) ($row['name'] ?? '');
                    }
                }
                $this->auditService->logChange(
                    AuditAction::DEPLOYMENT_CAMPAIGN_CREATED,
                    null,
                    $uid,
                    'deployment_campaign',
                    $campaignId,
                    [],
                    [
                        'fonctionnalite' => (string) ($mod['name'] ?? ''),
                        'version' => (string) ($ver['version'] ?? ''),
                        'environnements' => $chNames,
                    ],
                );
            }

            return Response::redirect(url('admin/system/deployment/campaigns/' . $campaignId));
        } catch (\Throwable) {
            Session::flash('error', 'Création de la campagne impossible pour le moment.');
        }

        return Response::redirect(url('admin/system/deployment/campaigns/nouveau?module_id=' . $moduleId));
    }

    public function campaignsShow(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->repo->schemaReady() || !$this->campaignRepository->schemaReady() || $id < 1) {
            return Response::redirect(url('admin/system/deployment'));
        }
        $camp = $this->campaignRepository->findCampaign($id);
        if ($camp === null) {
            Session::flash('error', 'Campagne introuvable.');

            return Response::redirect(url('admin/system/deployment/campaigns'));
        }
        $jobs = $this->campaignRepository->listJobsForCampaign($id);

        return Response::view('layout.main', [
            'title' => 'Campagne de publication',
            'content' => 'admin.system.deployment_campaign_show',
            'deploymentCampaign' => $camp,
            'deploymentCampaignJobs' => $jobs,
            'deploymentCsrf' => Csrf::token(),
            'deploymentCampaignStatusLabels' => self::campaignStatusLabels(),
            'deploymentCampaignJobStatusLabels' => self::campaignJobStatusLabels(),
        ]);
    }

    public function campaignsProcess(Request $request, array $params = []): Response
    {
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, réessayez.');

            return Response::redirect(url('admin/system/deployment/campaigns'));
        }
        $id = (int) ($params['id'] ?? 0);
        if (!$this->repo->schemaReady() || !$this->campaignRepository->schemaReady() || $id < 1) {
            return Response::redirect(url('admin/system/deployment'));
        }
        $maxSteps = max(1, min(25, (int) $request->input('max_steps', 5)));
        $uid = Session::get('user_id') ? (int) Session::get('user_id') : null;
        $res = $this->campaignProcessor->processCampaignSteps($id, $uid, $maxSteps);
        if ($res['last_error'] !== null) {
            Session::flash('error', $res['last_error']);
            if ($uid !== null && !empty($res['should_audit_failure'])) {
                $this->auditService->logChange(
                    AuditAction::DEPLOYMENT_CAMPAIGN_FAILED,
                    null,
                    $uid,
                    'deployment_campaign',
                    $id,
                    [],
                    ['message' => $res['last_error']],
                );
            }
        } elseif (($res['processed'] ?? 0) > 0) {
            if (($res['stopped_reason'] ?? '') === 'batch_limit') {
                Session::flash('success', sprintf(
                    '%d étape(s) exécutée(s). Relancez l’opération pour poursuivre vers les environnements restants.',
                    (int) $res['processed'],
                ));
            } elseif (($res['stopped_reason'] ?? '') === 'campaign_done' && ($res['campaign_status'] ?? '') === 'completed') {
                Session::flash('success', 'Toutes les étapes de la campagne sont terminées.');
            } else {
                Session::flash('success', sprintf('%d étape(s) exécutée(s).', (int) $res['processed']));
            }
        } elseif (($res['campaign_status'] ?? '') === 'completed') {
            Session::flash('success', 'Cette campagne est déjà terminée.');
        } else {
            Session::flash('error', 'Aucune étape en attente n’a pu être lancée pour cette campagne.');
        }

        return Response::redirect(url('admin/system/deployment/campaigns/' . $id));
    }

    public function accessRuleStore(Request $request, array $params = []): Response
    {
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/deployment'));
        }
        $moduleId = (int) ($params['id'] ?? 0);
        if ($this->repo->findModuleById($moduleId) === null) {
            return Response::redirect(url('admin/system/deployment'));
        }
        $ruleType = trim((string) $request->input('rule_type', ''));
        $allowedTypes = ['public', 'deny_all', 'allow_community', 'deny_community'];
        if (!in_array($ruleType, $allowedTypes, true)) {
            Session::flash('error', 'Type de règle non reconnu.');

            return Response::redirect(url('admin/system/deployment/modules/' . $moduleId));
        }
        $communityId = (int) $request->input('community_id', 0);
        $envChannelId = (int) $request->input('environment_channel_id', 0);
        $versionScopeId = (int) $request->input('applies_to_version_id', 0);
        if (in_array($ruleType, ['allow_community', 'deny_community'], true) && $communityId < 1) {
            Session::flash('error', 'Choisissez une communauté de test pour ce type de règle.');

            return Response::redirect(url('admin/system/deployment/modules/' . $moduleId));
        }
        if (!in_array($ruleType, ['allow_community', 'deny_community'], true)) {
            $communityId = 0;
        }
        $priority = (int) $request->input('priority', 100);
        $isActive = $request->input('is_active') === '1';
        try {
            $ruleId = $this->repo->insertModuleAccessRule(
                $moduleId,
                $ruleType,
                $communityId > 0 ? $communityId : null,
                $versionScopeId > 0 ? $versionScopeId : null,
                $envChannelId > 0 ? $envChannelId : null,
                $priority,
                $isActive,
            );
            Session::flash('success', 'Règle ajoutée.');
            $aid = $this->auditActorId();
            if ($aid !== null && $ruleId > 0) {
                $this->auditService->logChange(
                    AuditAction::DEPLOYMENT_ACCESS_RULE_ADDED,
                    null,
                    $aid,
                    'platform_module_access_rule',
                    $ruleId,
                    [],
                    [
                        'module_id' => $moduleId,
                        'rule_type' => $ruleType,
                        'community_id' => $communityId > 0 ? $communityId : null,
                        'environment_channel_id' => $envChannelId > 0 ? $envChannelId : null,
                        'applies_to_version_id' => $versionScopeId > 0 ? $versionScopeId : null,
                        'priority' => $priority,
                        'is_active' => $isActive,
                    ],
                );
            }
        } catch (\Throwable) {
            Session::flash('error', 'Impossible d’ajouter la règle.');
        }

        return Response::redirect(url('admin/system/deployment/modules/' . $moduleId));
    }

    public function accessRuleDelete(Request $request, array $params = []): Response
    {
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/deployment'));
        }
        $moduleId = (int) $request->input('module_id', 0);
        $ruleId = (int) $request->input('rule_id', 0);
        if ($this->repo->findModuleById($moduleId) === null || $ruleId < 1) {
            return Response::redirect(url('admin/system/deployment'));
        }
        $ruleRow = $this->repo->findModuleAccessRuleById($ruleId);
        try {
            $this->repo->deleteModuleAccessRule($ruleId);
            Session::flash('success', 'Règle supprimée.');
            $aid = $this->auditActorId();
            if ($aid !== null && is_array($ruleRow)) {
                $old = [
                    'rule_id' => $ruleId,
                    'module_id' => (int) ($ruleRow['module_id'] ?? 0),
                    'rule_type' => (string) ($ruleRow['rule_type'] ?? ''),
                    'community_id' => isset($ruleRow['community_id']) ? (int) $ruleRow['community_id'] : null,
                    'environment_channel_id' => isset($ruleRow['environment_channel_id']) ? (int) $ruleRow['environment_channel_id'] : null,
                    'priority' => (int) ($ruleRow['priority'] ?? 0),
                ];
                $this->auditService->logChange(
                    AuditAction::DEPLOYMENT_ACCESS_RULE_REMOVED,
                    null,
                    $aid,
                    'platform_module_access_rule',
                    $ruleId,
                    $old,
                    [],
                );
            }
        } catch (\Throwable) {
            Session::flash('error', 'Suppression impossible.');
        }

        return Response::redirect(url('admin/system/deployment/modules/' . $moduleId));
    }

    public function communitiesIndex(Request $request, array $params = []): Response
    {
        if (!$this->repo->schemaReady()) {
            Session::flash('error', 'Schéma non disponible.');

            return Response::redirect(url('admin/system/deployment'));
        }

        $list = $this->repo->listTesterCommunities();
        $enriched = [];
        foreach ($list as $row) {
            $cid = (int) ($row['id'] ?? 0);
            if ($cid > 0) {
                $row['stats_member_count'] = $this->repo->countTesterCommunityMembersActive($cid);
                $row['stats_feedback_open'] = $this->repo->countTesterFeedbackForCommunity($cid, true);
                $row['stats_feedback_total'] = $this->repo->countTesterFeedbackForCommunity($cid, false);
            } else {
                $row['stats_member_count'] = 0;
                $row['stats_feedback_open'] = 0;
                $row['stats_feedback_total'] = 0;
            }
            $enriched[] = $row;
        }

        return Response::view('layout.main', [
            'title' => 'Communautés de préqualification',
            'content' => 'admin.system.deployment_communities',
            'deploymentCommunities' => $enriched,
            'deploymentRecentFeedback' => $this->repo->listRecentTesterFeedbackAll(22),
        ]);
    }

    public function communityEdit(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $row = $this->repo->findTesterCommunityById($id);
        if ($row === null) {
            Session::flash('error', 'Communauté introuvable.');

            return Response::redirect(url('admin/system/deployment/communities'));
        }
        $members = $this->repo->listTesterCommunityMembers($id);
        $memberRows = [];
        foreach ($members as $m) {
            $uid = (int) ($m['user_id'] ?? 0);
            $u = $uid > 0 ? $this->userRepository->findById($uid, null) : null;
            $memberRows[] = [
                'membership' => $m,
                'user' => $u,
            ];
        }

        $fbOpen = $this->repo->countTesterFeedbackForCommunity($id, true);
        $fbTotal = $this->repo->countTesterFeedbackForCommunity($id, false);
        $activeMembers = $this->repo->countTesterCommunityMembersActive($id);

        return Response::view('layout.main', [
            'title' => 'Communauté — ' . (string) ($row['name'] ?? ''),
            'content' => 'admin.system.deployment_community_form',
            'deploymentCommunity' => $row,
            'deploymentCommunityMembers' => $memberRows,
            'deploymentCommunityActiveMemberCount' => $activeMembers,
            'deploymentCommunityFeedbacks' => $this->repo->listTesterFeedbackForCommunity($id, 100),
            'deploymentCommunityFeedbackOpen' => $fbOpen,
            'deploymentCommunityFeedbackTotal' => $fbTotal,
            'deploymentFeedbackLabels' => self::testerFeedbackLabels(),
            'deploymentCsrf' => Csrf::token(),
        ]);
    }

    public function communityUpdate(Request $request, array $params = []): Response
    {
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/deployment/communities'));
        }
        $id = (int) ($params['id'] ?? 0);
        $prev = $this->repo->findTesterCommunityById($id);
        if ($prev === null) {
            return Response::redirect(url('admin/system/deployment/communities'));
        }
        $name = trim((string) $request->input('name', ''));
        $desc = trim((string) $request->input('description', ''));
        $priority = (int) $request->input('priority', 100);
        $isActive = $request->input('is_active') === '1';
        $validFrom = trim((string) $request->input('valid_from', ''));
        $validUntil = trim((string) $request->input('valid_until', ''));
        if ($name === '') {
            Session::flash('error', 'Le nom est obligatoire.');

            return Response::redirect(url('admin/system/deployment/communities/' . $id . '/edit'));
        }
        $before = [
            'name' => (string) ($prev['name'] ?? ''),
            'description' => (string) ($prev['description'] ?? ''),
            'is_active' => (bool) ($prev['is_active'] ?? false),
            'priority' => (int) ($prev['priority'] ?? 0),
            'valid_from' => (string) ($prev['valid_from'] ?? ''),
            'valid_until' => (string) ($prev['valid_until'] ?? ''),
        ];
        $after = [
            'name' => $name,
            'description' => $desc !== '' ? $desc : '',
            'is_active' => $isActive,
            'priority' => $priority,
            'valid_from' => $validFrom !== '' ? $validFrom : '',
            'valid_until' => $validUntil !== '' ? $validUntil : '',
        ];
        try {
            $this->repo->updateTesterCommunityMeta(
                $id,
                $name,
                $desc !== '' ? $desc : null,
                $isActive,
                $validFrom !== '' ? $validFrom : null,
                $validUntil !== '' ? $validUntil : null,
                $priority,
            );
            Session::flash('success', 'Communauté mise à jour.');
            $aid = $this->auditActorId();
            if ($aid !== null) {
                $keys = ['name', 'description', 'is_active', 'priority', 'valid_from', 'valid_until'];
                [$o, $n] = AuditFieldSnapshot::diffOnly($before, $after, $keys);
                $this->auditService->logChange(
                    AuditAction::DEPLOYMENT_TESTER_COMMUNITY_UPDATED,
                    null,
                    $aid,
                    'tester_community',
                    $id,
                    $o,
                    $n,
                );
            }
        } catch (\Throwable) {
            Session::flash('error', 'Enregistrement impossible.');
        }

        return Response::redirect(url('admin/system/deployment/communities/' . $id . '/edit'));
    }

    public function communityMemberAdd(Request $request, array $params = []): Response
    {
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/deployment/communities'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($this->repo->findTesterCommunityById($id) === null) {
            return Response::redirect(url('admin/system/deployment/communities'));
        }
        $expires = trim((string) $request->input('expires_at', ''));
        $expiresVal = $expires !== '' ? $expires : null;

        $email = strtolower(trim((string) $request->input('member_email', '')));
        if ($email !== '') {
            $ids = $this->userRepository->listIdsByEmailNormalized($email);
            if ($ids === []) {
                Session::flash('error', 'Aucun compte trouvé pour cette adresse e-mail.');

                return Response::redirect(url('admin/system/deployment/communities/' . $id . '/edit'));
            }
            $ok = 0;
            foreach ($ids as $uid) {
                if ($uid < 1) {
                    continue;
                }
                try {
                    $this->repo->insertTesterCommunityMember($id, $uid, $expiresVal);
                    $ok++;
                } catch (\Throwable) {
                }
            }
            if ($ok > 0) {
                Session::flash('success', $ok > 1
                    ? $ok . ' comptes rattachés à cette adresse ont été ajoutés ou réactivés.'
                    : 'Membre ajouté ou réactivé dans la communauté.');
                foreach ($ids as $uid) {
                    if ($uid > 0) {
                        $this->auditTesterMemberAdded($id, $uid, $ok > 1 ? 'Ajout par e-mail (plusieurs comptes)' : 'Ajout par e-mail');
                    }
                }
            } else {
                Session::flash('error', 'Ajout impossible pour les comptes correspondants.');
            }

            return Response::redirect(url('admin/system/deployment/communities/' . $id . '/edit'));
        }

        $userId = (int) $request->input('user_id', 0);
        if ($userId < 1 || $this->userRepository->findById($userId, null) === null) {
            Session::flash('error', 'Indiquez un e-mail de membre ou un identifiant numérique valide.');

            return Response::redirect(url('admin/system/deployment/communities/' . $id . '/edit'));
        }
        try {
            $this->repo->insertTesterCommunityMember($id, $userId, $expiresVal);
            Session::flash('success', 'Membre ajouté ou réactivé dans la communauté.');
            $this->auditTesterMemberAdded($id, $userId, 'Ajout par référence de compte');
        } catch (\Throwable) {
            Session::flash('error', 'Ajout impossible.');
        }

        return Response::redirect(url('admin/system/deployment/communities/' . $id . '/edit'));
    }

    public function communityMemberRemove(Request $request, array $params = []): Response
    {
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/deployment/communities'));
        }
        $id = (int) ($params['id'] ?? 0);
        $userId = (int) $request->input('user_id', 0);
        if ($this->repo->findTesterCommunityById($id) === null || $userId < 1) {
            return Response::redirect(url('admin/system/deployment/communities'));
        }
        try {
            $this->repo->removeTesterCommunityMember($id, $userId);
            Session::flash('success', 'Membre retiré de la communauté.');
            $this->auditTesterMemberRemoved($id, $userId);
        } catch (\Throwable) {
            Session::flash('error', 'Retrait impossible.');
        }

        return Response::redirect(url('admin/system/deployment/communities/' . $id . '/edit'));
    }

    /** @return array<string, string> */
    public static function campaignStatusLabels(): array
    {
        return [
            'queued' => 'En attente',
            'in_progress' => 'En cours',
            'completed' => 'Terminée',
            'failed' => 'Échouée',
            'cancelled' => 'Annulée',
        ];
    }

    /** @return array<string, string> */
    public static function campaignJobStatusLabels(): array
    {
        return [
            'queued' => 'En attente',
            'running' => 'En cours d’exécution',
            'success' => 'Réussie',
            'failed' => 'Échouée',
            'rolled_back' => 'Annulée (retour arrière)',
        ];
    }

    /** @return array<string, string> */
    public static function versionStatusLabels(): array
    {
        return [
            'draft' => 'Brouillon',
            'validated' => 'Validée',
            'published' => 'Publiée',
            'rollback_ready' => 'Prête pour retour arrière',
            'deprecated' => 'Retirée',
        ];
    }

    /** @return array<string, string> */
    public static function ruleTypeLabels(): array
    {
        return [
            'public' => 'Ouvert à tous les environnements couverts',
            'deny_all' => 'Accès fermé par défaut',
            'allow_community' => 'Autoriser une communauté de test',
            'deny_community' => 'Refuser une communauté de test',
        ];
    }

    /**
     * @return array{type: array<string, string>, severity: array<string, string>, status: array<string, string>}
     */
    public static function testerFeedbackLabels(): array
    {
        return [
            'type' => [
                'bug' => 'Anomalie',
                'ui' => 'Interface',
                'ux' => 'Parcours',
                'idea' => 'Idée',
                'regression' => 'Régression',
                'performance' => 'Performance',
            ],
            'severity' => [
                'low' => 'Faible',
                'medium' => 'Moyenne',
                'high' => 'Élevée',
                'critical' => 'Critique',
            ],
            'status' => [
                'new' => 'Nouveau',
                'triaged' => 'Trié',
                'in_progress' => 'En cours',
                'fixed' => 'Corrigé',
                'rejected' => 'Rejeté',
                'closed' => 'Clôturé',
            ],
        ];
    }
}
