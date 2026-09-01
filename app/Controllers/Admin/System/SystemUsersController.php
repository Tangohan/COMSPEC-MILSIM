<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelExtrasRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserLegalIdentityRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Services\Account\AccountDeletionService;
use App\Services\Account\AccountPurgeService;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Repositories\AccountPurgeRequestRepository;

/**
 * Annuaire global des comptes (toutes communautés) — administration plateforme.
 */
final class SystemUsersController
{
    public function __construct(
        private UserRepository $users,
        private TenantRepository $tenants,
        private ?AuditService $audit = null,
        private ?AccountDeletionService $accountDeletion = null,
        private ?AccountPurgeService $accountPurge = null,
        private ?AccountPurgeRequestRepository $purgeRequests = null,
    ) {
        $this->audit ??= new AuditService();
        $this->purgeRequests ??= new AccountPurgeRequestRepository();
    }

    private function deletionService(): AccountDeletionService
    {
        return $this->accountDeletion ??= \App\Core\Container::get(AccountDeletionService::class);
    }

    private function purgeService(): AccountPurgeService
    {
        return $this->accountPurge ??= new AccountPurgeService();
    }

    public function index(Request $request, array $params = []): Response
    {
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $tenantFilter = (int) $request->query('tenant_id', 0);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;
        $allowedStatus = ['active', 'inactive', 'pending_verification', 'deleted'];
        if ($status !== '' && !in_array($status, $allowedStatus, true)) {
            $status = '';
        }

        $result = $this->users->listGroupedAccountsForPlatformDirectory(
            $q !== '' ? $q : null,
            $status !== '' ? $status : null,
            $tenantFilter > 0 ? $tenantFilter : null,
            $page,
            $perPage
        );
        $total = (int) ($result['total'] ?? 0);
        $pages = max(1, (int) ceil($total / $perPage));

        return Response::view('layout.main', [
            'title' => 'Comptes utilisateurs',
            'content' => 'admin.system.users',
            'platformUserGroups' => $result['groups'] ?? [],
            'platformUsers' => [], // rétrocompat partielle si une vue externe lit encore la clé
            'platformUsersTotal' => $total,
            'platformUsersPage' => $page,
            'platformUsersPages' => $pages,
            'platformUsersQ' => $q,
            'platformUsersStatus' => $status,
            'platformUsersTenantId' => $tenantFilter,
            'platformTenants' => $this->tenants->listOverviewForPlatform(),
            'pendingPurgeRequests' => $this->purgeRequests->listPending(50),
            'pendingPurgeRequestsCount' => $this->purgeRequests->countPending(),
        ]);
    }

    /**
     * Dossier personne multi-communautés (toutes les fiches users du même e-mail).
     */
    public function showPerson(Request $request, array $params = []): Response
    {
        $email = strtolower(trim((string) $request->query('email', '')));
        $userId = (int) $request->query('user_id', 0);
        if ($email === '' && $userId > 0) {
            $anchor = $this->users->findById($userId, null);
            if ($anchor !== null) {
                $email = strtolower(trim((string) ($anchor['email'] ?? '')));
            }
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || str_ends_with($email, '@deleted.invalid')) {
            Session::flash('error', 'Personne introuvable.');

            return Response::redirect(url('admin/users'));
        }

        $memberships = $this->users->listAllMembershipsByEmail($email);
        if ($memberships === []) {
            Session::flash('error', 'Aucune appartenance trouvée pour cette adresse.');

            return Response::redirect(url('admin/users'));
        }

        /** @var UserProfileRepository $profiles */
        $profiles = Container::get(UserProfileRepository::class);
        /** @var UserLegalIdentityRepository $legalRepo */
        $legalRepo = Container::get(UserLegalIdentityRepository::class);
        /** @var PersonnelProfileRepository $personnelProfiles */
        $personnelProfiles = Container::get(PersonnelProfileRepository::class);
        /** @var PersonnelExtrasRepository $personnelExtras */
        $personnelExtras = Container::get(PersonnelExtrasRepository::class);
        /** @var PersonnelAssignmentRepository $assignments */
        $assignments = Container::get(PersonnelAssignmentRepository::class);
        /** @var GradeRepository $grades */
        $grades = Container::get(GradeRepository::class);

        $dossierMemberships = [];
        $globalSteam = '';
        $globalAthena = '';
        $displayName = '';
        $callsign = '';
        foreach ($memberships as $m) {
            $uid = (int) ($m['id'] ?? 0);
            $tid = (int) ($m['tenant_id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $steam = trim((string) ($m['steam_id'] ?? ''));
            if ($steam !== '' && $globalSteam === '') {
                $globalSteam = $steam;
            }
            $athena = trim((string) ($m['athena_identifier'] ?? ''));
            if ($athena !== '' && $globalAthena === '') {
                $globalAthena = $athena;
            }
            $dn = trim((string) ($m['display_name'] ?? ''));
            if ($dn !== '' && $dn !== 'Compte supprimé' && $displayName === '') {
                $displayName = $dn;
            }
            $cs = trim((string) ($m['callsign'] ?? ''));
            if ($cs !== '' && $callsign === '') {
                $callsign = $cs;
            }

            $gradeId = (int) ($m['grade_id'] ?? 0);
            $grade = $gradeId > 0 ? $grades->findById($gradeId, $tid) : null;
            $pp = $personnelProfiles->getByUserId($uid) ?? [];
            $extras = $personnelExtras->getByUserId($uid) ?? [];
            $primaryAssignment = $assignments->getPrimaryAssignment($uid);
            $roleIds = $this->users->listOrganizationRoleIdsForUser($uid);
            $roleNames = [];
            $roleName = trim((string) ($m['role_name'] ?? ''));
            if ($roleName !== '') {
                $roleNames[] = $roleName;
            }

            $dossierMemberships[] = [
                'user' => $m,
                'profile' => $profiles->getByUserId($uid) ?? [],
                'legal' => $legalRepo->getByUserId($uid) ?? [],
                'personnel_profile' => is_array($pp) ? $pp : [],
                'personnel_extras' => is_array($extras) ? $extras : [],
                'grade' => is_array($grade) ? $grade : null,
                'primary_assignment' => is_array($primaryAssignment) ? $primaryAssignment : null,
                'role_names' => $roleNames,
                'org_role_ids' => $roleIds,
            ];
        }

        // Identité civile : première fiche légale non vide
        $civil = ['first_name' => '', 'last_name' => '', 'phone' => '', 'birth_date' => '', 'nationality' => ''];
        foreach ($dossierMemberships as $pack) {
            $legal = is_array($pack['legal'] ?? null) ? $pack['legal'] : [];
            foreach (array_keys($civil) as $k) {
                $v = trim((string) ($legal[$k] ?? ''));
                if ($v !== '' && $civil[$k] === '') {
                    $civil[$k] = $v;
                }
            }
            $prof = is_array($pack['profile'] ?? null) ? $pack['profile'] : [];
            foreach (['first_name', 'last_name', 'phone'] as $k) {
                $v = trim((string) ($prof[$k] ?? ''));
                if ($v !== '' && $civil[$k] === '') {
                    $civil[$k] = $v;
                }
            }
        }

        $hasLiveOrg = $this->users->emailHasActiveNonDefaultMembership($email);

        return Response::view('layout.main', [
            'title' => 'Dossier personne',
            'content' => 'admin.system.user_person',
            'personEmail' => $email,
            'personDisplayName' => $displayName,
            'personCallsign' => $callsign,
            'personSteamId' => $globalSteam,
            'personAthenaId' => $globalAthena,
            'personCivil' => $civil,
            'personHasLiveOrg' => $hasLiveOrg,
            'personMemberships' => $dossierMemberships,
        ]);
    }

    public function setStatus(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($this->backUrl($request));
        }
        $actorTenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        $userId = (int) $request->input('user_id');
        $tenantId = (int) $request->input('tenant_id');
        $status = trim((string) $request->input('status', ''));
        if ($userId < 1 || $tenantId < 1 || !in_array($status, ['active', 'inactive'], true)) {
            Session::flash('error', 'Demande invalide.');

            return Response::redirect($this->backUrl($request));
        }
        if ($userId === $actorId && $status === 'inactive') {
            Session::flash('error', 'Vous ne pouvez pas désactiver votre propre compte depuis cet écran.');

            return Response::redirect($this->backUrl($request));
        }

        $target = $this->users->findById($userId, $tenantId);
        if ($target === null) {
            Session::flash('error', 'Compte introuvable.');

            return Response::redirect($this->backUrl($request));
        }
        if (!empty($target['deleted_at'])) {
            Session::flash('error', 'Ce compte a déjà été supprimé.');

            return Response::redirect($this->backUrl($request));
        }

        $ok = $this->users->update($userId, $tenantId, ['status' => $status]);
        if (!$ok) {
            Session::flash('error', 'Impossible de mettre à jour ce compte.');

            return Response::redirect($this->backUrl($request));
        }

        $this->audit->logChange(
            AuditAction::USER_STATUS_UPDATED,
            $actorTenantId > 0 ? $actorTenantId : $tenantId,
            $actorId,
            'user',
            $userId,
            ['status' => (string) ($target['status'] ?? '')],
            ['status' => $status, 'platform_directory' => true],
        );

        Session::flash(
            'success',
            $status === 'active'
                ? 'Le compte a été réactivé.'
                : 'Le compte a été désactivé. La personne ne pourra plus se connecter avec cet accès.'
        );

        return Response::redirect($this->backUrl($request));
    }

    /**
     * Suppression douce (anonymisation).
     *
     * scope=org  → uniquement la fiche de cette communauté
     * scope=site → toutes les fiches partageant le même e-mail (site entier)
     */
    public function delete(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($this->backUrl($request));
        }
        $actorTenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        $userId = (int) $request->input('user_id');
        $tenantId = (int) $request->input('tenant_id');
        $scope = strtolower(trim((string) $request->input('scope', 'site')));
        if (!in_array($scope, ['org', 'site'], true)) {
            $scope = 'site';
        }
        if ($userId < 1 || $tenantId < 1) {
            Session::flash('error', 'Demande invalide.');

            return Response::redirect($this->backUrl($request));
        }
        if ($userId === $actorId) {
            Session::flash('error', 'Vous ne pouvez pas supprimer votre propre compte depuis cet écran.');

            return Response::redirect($this->backUrl($request));
        }

        $target = $this->users->findById($userId, $tenantId);
        if ($target === null) {
            Session::flash('error', 'Compte introuvable.');

            return Response::redirect($this->backUrl($request));
        }

        $result = $scope === 'org'
            ? $this->deletionService()->softDeleteMembership($userId, $tenantId, $actorId)
            : $this->deletionService()->softDeleteAccount($userId, $tenantId, $actorId);
        if (!$result['ok']) {
            Session::flash('error', 'Impossible de supprimer ce compte.');

            return Response::redirect($this->backUrl($request));
        }

        $this->audit->logChange(
            AuditAction::USER_DELETED,
            $actorTenantId > 0 ? $actorTenantId : $tenantId,
            $actorId,
            'user',
            $userId,
            ['status' => (string) ($target['status'] ?? ''), 'email' => (string) ($target['email'] ?? '')],
            [
                'status' => 'deleted',
                'platform_directory' => true,
                'scope' => $scope,
                'anonymized_user_ids' => $result['anonymized_user_ids'],
            ],
        );

        Session::flash(
            'success',
            $scope === 'org'
                ? 'Appartenance retirée de cette communauté (anonymisation). Les autres communautés de la personne sont intactes.'
                : 'Compte anonymisé sur tout le site. Ses données personnelles ont été effacées. L’adresse e-mail peut être réutilisée.'
        );

        return Response::redirect($this->backUrl($request));
    }

    /**
     * Suppression définitive.
     *
     * scope=org  → purge uniquement cette fiche users (une communauté)
     * scope=site → purge toutes les fiches du même e-mail
     */
    public function purge(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($this->backUrl($request));
        }
        $actorTenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        $userId = (int) $request->input('user_id');
        $tenantId = (int) $request->input('tenant_id');
        $scope = strtolower(trim((string) $request->input('scope', 'site')));
        if (!in_array($scope, ['org', 'site'], true)) {
            $scope = 'site';
        }
        if ($userId < 1 || $tenantId < 1) {
            Session::flash('error', 'Demande invalide.');

            return Response::redirect($this->backUrl($request));
        }
        if ($userId === $actorId) {
            Session::flash('error', 'Vous ne pouvez pas supprimer votre propre compte depuis cet écran.');

            return Response::redirect($this->backUrl($request));
        }

        $target = $this->users->findById($userId, $tenantId);
        if ($target === null) {
            Session::flash('error', 'Compte introuvable.');

            return Response::redirect($this->backUrl($request));
        }

        $email = strtolower(trim((string) ($target['email'] ?? '')));
        $confirmation = strtolower(trim((string) $request->input('confirm_email', '')));
        if ($email === '' || $confirmation !== $email) {
            Session::flash('error', 'Adresse de confirmation incorrecte : le compte n’a pas été touché.');

            return Response::redirect($this->backUrl($request));
        }

        $siblingIds = [$userId];
        if ($scope === 'site' && $email !== '' && !str_ends_with($email, '@deleted.invalid')) {
            $siblingIds = $this->users->listIdsByEmailNormalized($email);
            if ($siblingIds === []) {
                $siblingIds = [$userId];
            }
        }

        $this->audit->logChange(
            AuditAction::USER_PURGED,
            $actorTenantId > 0 ? $actorTenantId : $tenantId,
            $actorId,
            'user',
            $userId,
            [
                'status' => (string) ($target['status'] ?? ''),
                'email' => $email,
                'display_name' => (string) ($target['display_name'] ?? ''),
            ],
            [
                'status' => 'purged',
                'platform_directory' => true,
                'scope' => $scope,
                'target_user_ids' => array_map('intval', $siblingIds),
            ],
        );

        $report = $this->purgeService()->purge($userId, $scope === 'org' ? [] : $siblingIds);
        if (!$report['ok'] && $report['purged_user_ids'] === []) {
            Session::flash(
                'error',
                'Suppression définitive impossible : ' . ($report['errors'][0] ?? 'erreur inconnue') . '.'
            );

            return Response::redirect($this->backUrl($request));
        }

        $scopeLabel = $scope === 'org' ? 'dans cette communauté' : 'sur tout le site';
        $message = 'Compte supprimé définitivement ' . $scopeLabel . ' — '
            . count($report['purged_user_ids']) . ' fiche' . (count($report['purged_user_ids']) > 1 ? 's' : '')
            . ', ' . $report['rows_deleted'] . ' ligne' . ($report['rows_deleted'] > 1 ? 's' : '') . ' effacée'
            . ($report['rows_deleted'] > 1 ? 's' : '')
            . ', ' . $report['rows_detached'] . ' référence' . ($report['rows_detached'] > 1 ? 's' : '')
            . ' détachée' . ($report['rows_detached'] > 1 ? 's' : '') . '.';
        if ($report['errors'] !== []) {
            $message .= ' ' . count($report['errors']) . ' avertissement'
                . (count($report['errors']) > 1 ? 's' : '') . ' — voir le journal serveur.';
            foreach (array_slice($report['errors'], 0, 20) as $error) {
                error_log('[account_purge] user #' . $userId . ' : ' . $error);
            }
        }
        Session::flash('success', $message);

        return Response::redirect($this->backUrl($request));
    }

    /**
     * Purge en série les fiches « Compte supprimé » laissées par l’anonymisation.
     */
    public function purgeAnonymized(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($this->backUrl($request));
        }
        if (strtolower(trim((string) $request->input('confirm_phrase', ''))) !== 'supprimer definitivement') {
            Session::flash('error', 'Phrase de confirmation incorrecte : aucun compte n’a été touché.');

            return Response::redirect($this->backUrl($request));
        }

        $actorTenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');

        $this->audit->logChange(
            AuditAction::USER_PURGED,
            $actorTenantId > 0 ? $actorTenantId : 0,
            $actorId,
            'user',
            0,
            ['scope' => 'anonymized_accounts'],
            ['status' => 'purged', 'platform_directory' => true, 'bulk' => true],
        );

        $result = $this->purgeService()->purgeAnonymizedAccounts();
        foreach (array_slice($result['errors'], 0, 20) as $error) {
            error_log('[account_purge_bulk] ' . $error);
        }

        if ($result['purged'] === 0 && $result['failed'] === 0) {
            Session::flash('success', 'Aucune fiche anonymisée à retirer des annuaires.');

            return Response::redirect($this->backUrl($request));
        }

        Session::flash(
            $result['failed'] > 0 ? 'error' : 'success',
            $result['purged'] . ' fiche' . ($result['purged'] > 1 ? 's' : '') . ' anonymisée'
            . ($result['purged'] > 1 ? 's' : '') . ' retirée' . ($result['purged'] > 1 ? 's' : '')
            . ' des annuaires — historique conservé sous « Ancien membre »'
            . ($result['rows_reassigned'] > 0
                ? ' (' . $result['rows_reassigned'] . ' lien' . ($result['rows_reassigned'] > 1 ? 's' : '') . ' réassigné'
                . ($result['rows_reassigned'] > 1 ? 's' : '') . ')'
                : '')
            . ($result['failed'] > 0 ? ' · ' . $result['failed'] . ' en échec, voir le journal serveur.' : '.')
        );

        return Response::redirect($this->backUrl($request));
    }

    /**
     * Approuve une demande organisateur : retire la fiche anonymisée de l’orga en
     * conservant l’historique sous « Ancien membre ».
     */
    public function approvePurgeRequest(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($this->backUrl($request));
        }
        $actorTenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        $requestId = (int) $request->input('request_id', $params['id'] ?? 0);
        if ($requestId < 1) {
            Session::flash('error', 'Demande invalide.');

            return Response::redirect($this->backUrl($request));
        }
        $row = $this->purgeRequests->findById($requestId);
        if ($row === null || (string) ($row['status'] ?? '') !== 'pending') {
            Session::flash('error', 'Demande introuvable ou déjà traitée.');

            return Response::redirect($this->backUrl($request));
        }
        $targetUserId = (int) ($row['target_user_id'] ?? 0);
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        if ($targetUserId < 1 || $tenantId < 1) {
            Session::flash('error', 'Demande invalide.');

            return Response::redirect($this->backUrl($request));
        }
        if ($targetUserId === $actorId) {
            Session::flash('error', 'Vous ne pouvez pas purger votre propre compte.');

            return Response::redirect($this->backUrl($request));
        }

        $target = $this->users->findById($targetUserId, $tenantId);
        if ($target === null) {
            $this->purgeRequests->resolve($requestId, 'approved', $actorId, 'Cible déjà absente — demande clôturée.');
            Session::flash('success', 'La fiche ciblée n’existe plus ; la demande a été clôturée.');

            return Response::redirect($this->backUrl($request));
        }
        if (!AccountDeletionService::isAnonymizedUser($target)) {
            $anon = $this->deletionService()->softDeleteMembership($targetUserId, $tenantId, $actorId);
            if (!$anon['ok']) {
                Session::flash('error', 'Impossible d’anonymiser ce compte avant de le retirer de la communauté.');

                return Response::redirect($this->backUrl($request));
            }
        }

        $resolutionNote = trim((string) $request->input('resolution_note', ''));
        if (!$this->purgeRequests->resolve(
            $requestId,
            'approved',
            $actorId,
            $resolutionNote !== '' ? $resolutionNote : 'Approuvée — purge orga'
        )) {
            Session::flash('error', 'Impossible de clôturer la demande (peut-être déjà traitée).');

            return Response::redirect($this->backUrl($request));
        }

        $this->audit->logChange(
            AuditAction::USER_PURGED,
            $actorTenantId > 0 ? $actorTenantId : $tenantId,
            $actorId,
            'user',
            $targetUserId,
            [
                'status' => (string) ($target['status'] ?? ''),
                'email' => (string) ($target['email'] ?? ''),
                'display_name' => (string) ($target['display_name'] ?? ''),
                'purge_request_id' => $requestId,
            ],
            [
                'status' => 'purged',
                'platform_directory' => true,
                'scope' => 'org',
                'via' => 'org_purge_request',
                'purge_request_id' => $requestId,
            ],
        );

        $report = $this->purgeService()->purgeFromTenantPreservingHistory($targetUserId);
        if (!$report['ok'] && $report['purged_user_ids'] === []) {
            Session::flash(
                'error',
                'Demande approuvée, mais la purge a échoué : ' . ($report['errors'][0] ?? 'erreur inconnue') . '.'
            );

            return Response::redirect($this->backUrl($request));
        }

        $message = 'Demande approuvée — fiche retirée de la communauté, historique conservé sous « Ancien membre »'
            . ($report['rows_reassigned'] > 0
                ? ' (' . $report['rows_reassigned'] . ' lien' . ($report['rows_reassigned'] > 1 ? 's' : '')
                . ' réassigné' . ($report['rows_reassigned'] > 1 ? 's' : '') . ')'
                : '')
            . '.';
        if ($report['errors'] !== []) {
            $message .= ' ' . count($report['errors']) . ' avertissement'
                . (count($report['errors']) > 1 ? 's' : '') . ' — voir le journal serveur.';
            foreach (array_slice($report['errors'], 0, 20) as $error) {
                error_log('[account_purge_request] request #' . $requestId . ' : ' . $error);
            }
        }
        Session::flash('success', $message);

        return Response::redirect($this->backUrl($request));
    }

    /**
     * Refuse une demande organisateur de suppression définitive.
     */
    public function rejectPurgeRequest(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($this->backUrl($request));
        }
        $actorId = (int) Session::get('user_id');
        $requestId = (int) $request->input('request_id', $params['id'] ?? 0);
        if ($requestId < 1) {
            Session::flash('error', 'Demande invalide.');

            return Response::redirect($this->backUrl($request));
        }
        $row = $this->purgeRequests->findById($requestId);
        if ($row === null || (string) ($row['status'] ?? '') !== 'pending') {
            Session::flash('error', 'Demande introuvable ou déjà traitée.');

            return Response::redirect($this->backUrl($request));
        }
        $resolutionNote = trim((string) $request->input('resolution_note', ''));
        if (!$this->purgeRequests->resolve(
            $requestId,
            'rejected',
            $actorId,
            $resolutionNote !== '' ? $resolutionNote : 'Refusée par la plateforme'
        )) {
            Session::flash('error', 'Impossible de refuser la demande.');

            return Response::redirect($this->backUrl($request));
        }
        Session::flash('success', 'Demande de suppression définitive refusée.');

        return Response::redirect($this->backUrl($request));
    }

    private function backUrl(Request $request): string
    {
        $q = trim((string) $request->input('return_q', ''));
        $status = trim((string) $request->input('return_status', ''));
        $tenantId = (int) $request->input('return_tenant_id', 0);
        $page = max(1, (int) $request->input('return_page', 1));
        $bits = [];
        if ($q !== '') {
            $bits[] = 'q=' . rawurlencode($q);
        }
        if ($status !== '') {
            $bits[] = 'status=' . rawurlencode($status);
        }
        if ($tenantId > 0) {
            $bits[] = 'tenant_id=' . $tenantId;
        }
        if ($page > 1) {
            $bits[] = 'page=' . $page;
        }
        $qs = $bits !== [] ? ('?' . implode('&', $bits)) : '';

        return url('admin/users') . $qs;
    }
}
