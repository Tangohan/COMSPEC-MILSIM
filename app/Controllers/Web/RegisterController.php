<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\EmailTokenRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Auth\AuthService;
use App\Services\Email\EmailTokenPurpose;
use App\Services\EmailService;
use App\Services\Rbac\RbacService;
use Throwable;

final class RegisterController
{
    public function __construct(
        private AuthService $authService,
        private TenantRepository $tenantRepository,
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private RbacService $rbacService,
        private AuditService $auditService,
        private EmailService $emailService,
        private EmailTokenRepository $emailTokens
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        if ($this->authService->check()) {
            return Response::redirect(url('dashboard'));
        }
        $ref = trim((string) $request->query('ref'));
        if ($ref !== '') {
            Session::set('pending_referrer_code', $ref);
        }

        return Response::view('auth.register', [
            'title' => 'Créer un compte',
            'prefill_community_code' => trim((string) $request->query('community_code')),
            'prefill_tenant_slug' => trim((string) $request->query('tenant_slug')),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        if ($this->authService->check()) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('register'));
        }
        $email = trim((string) $request->input('email'));
        $password = (string) $request->input('password');
        $confirm = (string) $request->input('password_confirmation');
        $displayName = trim((string) $request->input('display_name'));
        $characterName = trim((string) $request->input('character_name'));
        $callsign = trim((string) $request->input('callsign'));
        $primaryRole = trim((string) $request->input('primary_role'));
        $secondaryRole = trim((string) $request->input('secondary_role'));

        $v = new Validator(
            [
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $confirm,
                'display_name' => $displayName,
                'character_name' => $characterName,
                'callsign' => $callsign,
                'primary_role' => $primaryRole,
                'secondary_role' => $secondaryRole,
            ],
            [
                'email' => 'required|email',
                'password' => 'required|min:8',
                'password_confirmation' => 'required',
                'display_name' => 'required|min:2|max:100',
                'character_name' => 'required|min:2|max:150',
                'callsign' => 'required|min:2|max:50',
                'primary_role' => 'required|min:2|max:100',
                'secondary_role' => 'max:100',
            ]
        );
        if (!$v->validate() || $password !== $confirm) {
            Session::flash(
                'error',
                'Vérifiez les champs : email valide, mot de passe 8+ caractères, confirmation identique, nom affiché et identité RP (nom RP, callsign, rôle principal) correctement renseignés.'
            );

            return Response::redirect(url('register'));
        }

        $communityCodeInput = trim((string) $request->input('community_code'));
        $tenant = $this->tenantRepository->getDefaultTenant();
        if ($communityCodeInput !== '') {
            $resolved = $this->tenantRepository->findByCommunityCode($communityCodeInput);
            if (!$resolved) {
                Session::flash('error', 'Code communauté invalide.');

                return Response::redirect(url('register'));
            }
            $tenant = $resolved;
        }
        if (!$tenant) {
            Session::flash('error', 'Aucune organisation de base configurée.');

            return Response::redirect(url('register'));
        }
        $tenantId = (int) $tenant['id'];
        if (strcasecmp($email, UserRepository::SYSTEM_MODERATOR_EMAIL) === 0) {
            Session::flash('error', 'Cet email est réservé au système.');

            return Response::redirect(url('register'));
        }
        if ($this->userRepository->emailExistsInTenant($tenantId, $email)) {
            Session::flash('error', 'Cet email est déjà utilisé.');

            return Response::redirect(url('register'));
        }
        if ($this->userRepository->callsignExistsInTenant($tenantId, $callsign)) {
            Session::flash('error', 'Ce callsign est déjà utilisé dans cette communauté.');

            return Response::redirect(url('register'));
        }

        $roleId = $this->roleRepository->getIdBySlug($tenantId, 'member');
        $hash = password_hash($password, PASSWORD_ARGON2ID);

        $pdo = Database::getPdo();
        $pdo->beginTransaction();
        try {
            $userId = $this->userRepository->create($tenantId, [
                'email' => $email,
                'password_hash' => $hash,
                'display_name' => $displayName,
                'callsign' => $callsign,
                'role_id' => $roleId,
                'status' => 'pending_verification',
            ]);
            $this->personnelProfileRepository->ensureRecord($userId);
            $profileData = [
                'character_name' => $characterName,
                'callsign' => $callsign,
                'primary_role' => $primaryRole,
            ];
            if ($secondaryRole !== '') {
                $profileData['secondary_role'] = $secondaryRole;
            }
            $this->personnelProfileRepository->update($userId, $profileData);
            $pdo->commit();
        } catch (Throwable) {
            $pdo->rollBack();
            Session::flash('error', 'Inscription impossible pour le moment. Réessayez.');

            return Response::redirect(url('register'));
        }

        $this->auditService->log(AuditAction::AUTH_REGISTER, $tenantId, $userId, 'user', $userId, null, $email);

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $this->emailTokens->deletePendingForUserPurpose($userId, EmailTokenPurpose::REGISTER_CONFIRM);
        $expires = new \DateTimeImmutable('+15 minutes');
        $this->emailTokens->create(
            $tenantId,
            $userId,
            EmailTokenPurpose::REGISTER_CONFIRM,
            $tokenHash,
            bin2hex(random_bytes(16)),
            $expires
        );
        $verifyUrl = url('verify-email') . '?token=' . rawurlencode($rawToken);
        $tenantName = (string) ($tenant['name'] ?? 'Communauté');
        $this->emailService->sendUserRegisterConfirmation(
            strtolower(trim($email)),
            $displayName,
            $tenantName,
            $verifyUrl,
            15,
            $tenantId
        );

        Session::set('register_pending_email', strtolower(trim($email)));

        return Response::redirect(url('register/check-email'));
    }
}
