<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlatformModuleReleaseRepository;
use App\Repositories\UserRepository;

final class PlatformDeploymentAdminController
{
    public function __construct(
        private PlatformModuleReleaseRepository $repo,
        private UserRepository $userRepository,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        if (!$this->repo->schemaReady()) {
            Session::flash('error', 'Les tables de déploiement ne sont pas présentes. Exécutez les migrations prévues pour cette fonctionnalité.');

            return Response::view('layout.main', [
                'title' => 'Déploiement & préqualification',
                'content' => 'admin.system.deployment_index',
                'deploymentSchemaReady' => false,
                'deploymentChannels' => [],
                'deploymentMatrix' => [],
                'deploymentModules' => [],
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
            'title' => 'Déploiement & préqualification',
            'content' => 'admin.system.deployment_index',
            'deploymentSchemaReady' => true,
            'deploymentChannels' => $channels,
            'deploymentMatrix' => $matrix,
            'deploymentModules' => $this->repo->listPlatformModules(),
            'deploymentCsrf' => Csrf::token(),
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
            $this->repo->insertPlatformModule($code, $name, $desc !== '' ? $desc : null, true, false);
            Session::flash('success', 'Fonctionnalité déployable enregistrée.');
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
        try {
            $this->repo->updatePlatformModule($id, $name, $desc !== '' ? $desc : null, $isActive, $isPublic);
            Session::flash('success', 'Modifications enregistrées.');
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
            $this->repo->insertModuleVersion($id, $version, $status, $uid);
            Session::flash('success', 'Nouvelle version créée.');
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
            $this->repo->setCurrentReleaseForModuleChannel($moduleId, $channelId, $versionId, $uid);
            Session::flash('success', 'Publication sur le canal mise à jour.');
        } catch (\Throwable) {
            Session::flash('error', 'Mise à jour de la publication impossible.');
        }

        return Response::redirect(url('admin/system/deployment/modules/' . $moduleId));
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
            $this->repo->insertModuleAccessRule(
                $moduleId,
                $ruleType,
                $communityId > 0 ? $communityId : null,
                $versionScopeId > 0 ? $versionScopeId : null,
                $envChannelId > 0 ? $envChannelId : null,
                $priority,
                $isActive,
            );
            Session::flash('success', 'Règle ajoutée.');
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
        try {
            $this->repo->deleteModuleAccessRule($ruleId);
            Session::flash('success', 'Règle supprimée.');
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

        return Response::view('layout.main', [
            'title' => 'Communautés de préqualification',
            'content' => 'admin.system.deployment_communities',
            'deploymentCommunities' => $this->repo->listTesterCommunities(),
            'deploymentCsrf' => Csrf::token(),
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

        return Response::view('layout.main', [
            'title' => 'Communauté — ' . (string) ($row['name'] ?? ''),
            'content' => 'admin.system.deployment_community_form',
            'deploymentCommunity' => $row,
            'deploymentCommunityMembers' => $memberRows,
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
        if ($this->repo->findTesterCommunityById($id) === null) {
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
        $userId = (int) $request->input('user_id', 0);
        if ($userId < 1 || $this->userRepository->findById($userId, null) === null) {
            Session::flash('error', 'Identifiant membre inconnu ou invalide.');

            return Response::redirect(url('admin/system/deployment/communities/' . $id . '/edit'));
        }
        $expires = trim((string) $request->input('expires_at', ''));
        try {
            $this->repo->insertTesterCommunityMember($id, $userId, $expires !== '' ? $expires : null);
            Session::flash('success', 'Membre ajouté ou réactivé dans la communauté.');
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
        } catch (\Throwable) {
            Session::flash('error', 'Retrait impossible.');
        }

        return Response::redirect(url('admin/system/deployment/communities/' . $id . '/edit'));
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
}
