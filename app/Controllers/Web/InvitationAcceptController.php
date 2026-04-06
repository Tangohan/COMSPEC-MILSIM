<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\CommunityInvitationRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Auth\AuthService;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;
use App\Services\Platform\FeatureGateService;
use App\Services\Rbac\RbacService;
use App\Services\Moderation\IndicatorBlocklistService;

final class InvitationAcceptController
{
    public function __construct(
        private CommunityInvitationRepository $invitations,
        private TenantRepository $tenantRepository,
        private UserRepository $users,
        private AuthService $authService,
        private RbacService $rbacService,
        private AuditService $auditService,
        private FeatureGateService $featureGate,
        private EmailService $emailService,
        private UnitRepository $unitRepository,
        private PersonnelAssignmentRepository $personnelAssignmentRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private PersonnelJobRoleRepository $personnelJobRoleRepository,
        private IndicatorBlocklistService $indicatorBlocklist,
        private UserNotificationPreferencesRepository $notificationPreferencesRepository
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        $token = trim((string) $request->query('token', ''));
        if ($token === '') {
            Session::flash('error', 'Lien d’invitation invalide.');

            return Response::redirect(url(''));
        }
        $hash = hash('sha256', $token);
        $inv = $this->invitations->findValidByTokenHash($hash);
        if (!$inv) {
            Session::flash('error', 'Invitation expirée ou déjà utilisée.');

            return Response::redirect(url(''));
        }

        return Response::view('layout.main', [
            'title' => 'Accepter l’invitation',
            'content' => 'invitations.accept',
            'invitation' => $inv,
            'token' => $token,
        ]);
    }

    public function accept(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url(''));
        }
        $token = trim((string) $request->input('token'));
        $password = (string) $request->input('password');
        $confirm = (string) $request->input('password_confirmation');
        $hash = hash('sha256', $token);
        $inv = $this->invitations->findValidByTokenHash($hash);
        if (!$inv) {
            Session::flash('error', 'Invitation expirée ou déjà utilisée.');

            return Response::redirect(url(''));
        }
        $tenantId = (int) $inv['tenant_id'];
        $inviteEmail = strtolower(trim((string) ($inv['email'] ?? '')));
        if ($inviteEmail !== '' && $this->indicatorBlocklist->isEmailBlockedForTenant($tenantId, $inviteEmail)) {
            Session::flash('error', 'Cette invitation ne peut pas être utilisée : l’adresse e-mail est restreinte pour cette communauté.');

            return Response::redirect(url(''));
        }
        $ipAccept = trim($request->ip());
        if ($ipAccept !== '' && $this->indicatorBlocklist->isIpBlockedForLogin($tenantId, $ipAccept)) {
            Session::flash('error', 'L’acceptation n’est pas possible depuis cet équipement pour le moment.');

            return Response::redirect(url(''));
        }
        if (!$this->featureGate->canAddMember($tenantId)) {
            Session::flash('error', 'Cette communauté a atteint la limite de membres pour son plan.');

            return Response::redirect(url(''));
        }
        $v = new Validator(
            ['password' => $password, 'password_confirmation' => $confirm],
            ['password' => 'required|min:8', 'password_confirmation' => 'required']
        );
        if (!$v->validate() || $password !== $confirm) {
            Session::flash('error', 'Mot de passe invalide ou confirmation différente.');

            return Response::redirect(url('invitations/accept') . '?token=' . rawurlencode($token));
        }

        $email = $inviteEmail !== '' ? $inviteEmail : (string) $inv['email'];
        if ($this->users->emailExistsInTenant($tenantId, $email)) {
            Session::flash('error', 'Vous avez déjà un compte dans cette communauté.');

            return Response::redirect(url('login'));
        }

        $pdo = Database::getPdo();
        $gradeStmt = $pdo->prepare('SELECT id FROM grades WHERE tenant_id = ? ORDER BY rank_order ASC LIMIT 1');
        $gradeStmt->execute([$tenantId]);
        $gradeId = (int) ($gradeStmt->fetchColumn() ?: 0);
        if ($gradeId <= 0) {
            Session::flash('error', 'Configuration de la communauté incomplète (grades).');

            return Response::redirect(url(''));
        }

        $rstmt = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $rstmt->execute([$tenantId, 'member']);
        $memberRoleId = (int) ($rstmt->fetchColumn() ?: 0);
        $invRole = $inv['role_id'] !== null && $inv['role_id'] !== '' ? (int) $inv['role_id'] : 0;
        $roleId = $invRole > 0 ? $invRole : $memberRoleId;
        if ($roleId <= 0) {
            Session::flash('error', 'Rôle par défaut introuvable pour cette communauté.');

            return Response::redirect(url(''));
        }

        $existingGlobal = $this->users->findFirstByEmailGlobal($email);
        if ($existingGlobal) {
            if (!password_verify($password, (string) $existingGlobal['password_hash'])) {
                Session::flash('error', 'Mot de passe incorrect pour ce compte email.');

                return Response::redirect(url('invitations/accept') . '?token=' . rawurlencode($token));
            }
            $newId = $this->users->cloneUserToTenant((int) $existingGlobal['id'], $tenantId, $roleId, $gradeId);
        } else {
            $ph = password_hash($password, PASSWORD_ARGON2ID);
            $newId = $this->users->create($tenantId, [
                'email' => $email,
                'password_hash' => $ph,
                'role_id' => $roleId,
                'grade_id' => $gradeId,
                'status' => 'active',
            ]);
        }
        if (!$existingGlobal) {
            $this->users->syncOrganizationRoles($newId, $tenantId, [$roleId], null, true);
        }

        $this->applyInvitationPayload($tenantId, $newId, $inv);

        $this->invitations->markAccepted((int) $inv['id'], $newId);
        $this->auditService->log(AuditAction::INVITATION_ACCEPTED, $tenantId, $newId, 'invitation', (int) $inv['id']);

        $tenantRow = $this->tenantRepository->findById($tenantId);
        $tenantName = (string) ($tenantRow['name'] ?? '');
        $staffEmails = $this->users->listGovernanceEmailsForTenant($tenantId);
        $ip = trim($request->ip());
        foreach ($staffEmails as $adminEmail) {
            $em = strtolower(trim($adminEmail));
            $u = $em !== '' ? $this->users->findByEmail($tenantId, $em) : null;
            if ($u && !$this->notificationPreferencesRepository->isEmailEventEnabled((int) ($u['id'] ?? 0), EmailEvents::NEW_COMMUNITY_MEMBER)) {
                continue;
            }
            $this->emailService->sendNewCommunityMemberStaff(
                $adminEmail,
                $tenantName,
                $email,
                $ip !== '' ? $ip : '—',
                'Invitation acceptée',
                $tenantId
            );
        }

        $u = $this->users->findById($newId, $tenantId);
        if ($u) {
            $this->authService->loginUser($u);
            $this->rbacService->setPermissionsForGateFromUserRow($u, $this->users);
        }
        Session::flash('success', 'Bienvenue dans la communauté.');

        return Response::redirect(url('dashboard'));
    }

    /**
     * Unité ORBAT + libellé d’affectation + rôle métier dossier, prévus sur l’invitation.
     *
     * @param array<string, mixed> $inv
     */
    private function applyInvitationPayload(int $tenantId, int $userId, array $inv): void
    {
        $raw = $inv['invitation_payload'] ?? null;
        if ($raw === null || $raw === '') {
            return;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
        } else {
            $decoded = is_array($raw) ? $raw : null;
        }
        if (!is_array($decoded)) {
            return;
        }

        $unitId = isset($decoded['unit_id']) ? (int) $decoded['unit_id'] : 0;
        $assignmentLabel = isset($decoded['assignment_label']) ? trim((string) $decoded['assignment_label']) : '';
        if ($assignmentLabel === '') {
            $assignmentLabel = 'Membre';
        }
        $jobRoleId = isset($decoded['personnel_job_role_id']) ? (int) $decoded['personnel_job_role_id'] : 0;

        try {
            if (
                $jobRoleId > 0
                && $this->personnelJobRoleRepository->tablesExist()
                && $this->personnelJobRoleRepository->personnelProfilesHaveJobRoleColumns()
            ) {
                $jr = $this->personnelJobRoleRepository->findRoleById($jobRoleId, $tenantId);
                if ($jr) {
                    $this->personnelProfileRepository->ensureRecord($userId);
                    $roleName = trim((string) ($jr['name'] ?? ''));
                    $this->personnelProfileRepository->update($userId, [
                        'personnel_job_role_id' => $jobRoleId,
                        'primary_role' => function_exists('mb_substr') ? mb_substr($roleName, 0, 100) : substr($roleName, 0, 100),
                    ]);
                    if ($this->personnelJobRoleRepository->pivotTableExists()) {
                        try {
                            $this->personnelJobRoleRepository->replaceUserPivotJobRoles($tenantId, $userId, [[
                                'personnel_job_role_id' => $jobRoleId,
                                'role_detail' => '',
                                'is_primary' => true,
                            ]]);
                        } catch (\Throwable) {
                        }
                    }
                }
            }
            if ($unitId > 0) {
                $u = $this->unitRepository->findById($unitId, $tenantId);
                if ($u) {
                    $this->personnelAssignmentRepository->syncPrimaryAssignmentFromDossier(
                        $userId,
                        $unitId,
                        mb_substr($assignmentLabel, 0, 120)
                    );
                }
            }
        } catch (\Throwable $e) {
            error_log('InvitationAcceptController::applyInvitationPayload: ' . $e->getMessage());
        }
    }
}
