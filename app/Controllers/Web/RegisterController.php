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
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Account\AccountDeletionService;
use App\Services\Auth\AuthService;
use App\Services\Email\EmailTokenPurpose;
use App\Services\EmailService;
use App\Services\Moderation\IndicatorBlocklistService;
use App\Services\Rbac\RbacService;
use App\Services\Steam\SteamWebApiService;
use App\Support\UserFacingExceptionMapper;
use Throwable;

final class RegisterController
{
    public function __construct(
        private AuthService $authService,
        private TenantRepository $tenantRepository,
        private UserRepository $userRepository,
        private UserProfileRepository $userProfileRepository,
        private RoleRepository $roleRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private RbacService $rbacService,
        private AuditService $auditService,
        private EmailService $emailService,
        private EmailTokenRepository $emailTokens,
        private IndicatorBlocklistService $indicatorBlocklist,
        private SteamWebApiService $steamWebApiService,
        private AccountDeletionService $accountDeletionService,
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

        $registerOld = Session::getFlash('register_old');
        $registerStep = Session::getFlash('register_step');
        $queryCode = trim((string) $request->query('community_code'));

        return Response::view('auth.register', [
            'title' => 'Créer un compte',
            'prefill_community_code' => $queryCode !== ''
                ? $queryCode
                : (is_array($registerOld) ? trim((string) ($registerOld['community_code'] ?? '')) : ''),
            'prefill_tenant_slug' => trim((string) $request->query('tenant_slug')),
            'register_old' => is_array($registerOld) ? $registerOld : [],
            'register_step' => is_numeric($registerStep) ? (int) $registerStep : 1,
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
        $email = strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');
        $confirm = (string) $request->input('password_confirmation');
        $firstName = trim((string) $request->input('first_name'));
        $lastName = trim((string) $request->input('last_name'));
        if (function_exists('mb_substr')) {
            $firstName = mb_substr($firstName, 0, 100);
            $lastName = mb_substr($lastName, 0, 100);
        } else {
            $firstName = substr($firstName, 0, 100);
            $lastName = substr($lastName, 0, 100);
        }
        $displayName = trim($firstName . ' ' . $lastName);
        $steamProfile = trim((string) $request->input('steam_profile'));
        $discordHandle = trim((string) $request->input('discord_handle'));
        if (function_exists('mb_substr')) {
            $discordHandle = mb_substr($discordHandle, 0, 120);
        } else {
            $discordHandle = substr($discordHandle, 0, 120);
        }
        $acceptTerms = (string) $request->input('accept_terms') === '1';
        $communityCodeInput = trim((string) $request->input('community_code'));

        $oldPayload = [
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'steam_profile' => $steamProfile,
            'discord_handle' => $discordHandle,
            'community_code' => $communityCodeInput,
            'accept_terms' => $acceptTerms ? '1' : '',
        ];
        $flashBack = static function (string $message, int $step = 1) use ($oldPayload): void {
            Session::flash('error', $message);
            Session::flash('register_old', $oldPayload);
            Session::flash('register_step', $step);
        };

        $v = new Validator(
            [
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $confirm,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'steam_profile' => $steamProfile,
                'discord_handle' => $discordHandle,
            ],
            [
                'email' => 'required|email',
                'password' => 'required|min:8',
                'password_confirmation' => 'required',
                'first_name' => 'required|min:1|max:100',
                'last_name' => 'required|min:1|max:100',
                'steam_profile' => 'max:512',
                'discord_handle' => 'max:120',
            ]
        );
        if (!$v->validate() || $displayName === '' || (function_exists('mb_strlen') ? mb_strlen($displayName) : strlen($displayName)) < 2) {
            $flashBack('Vérifiez les informations saisies (prénom, nom, e-mail et mot de passe).', 1);

            return Response::redirect(url('register'));
        }
        if ($password !== $confirm) {
            $flashBack('Les deux mots de passe ne sont pas identiques.', 1);

            return Response::redirect(url('register'));
        }
        if (!$acceptTerms) {
            $flashBack('Merci d’accepter les conditions pour terminer l’inscription.', 2);

            return Response::redirect(url('register'));
        }
        $resolvedSteamId = null;
        if ($steamProfile !== '') {
            $resolvedSteamId = $this->steamWebApiService->resolveSteamIdFromUserInput($steamProfile);
            if ($resolvedSteamId === null) {
                $flashBack(
                    'Profil Steam non reconnu. Indiquez le lien de votre profil, votre numéro Steam, un identifiant classique (STEAM_0:…), ou laissez le champ vide.',
                    2
                );

                return Response::redirect(url('register'));
            }
        }

        $tenant = $this->tenantRepository->getDefaultTenant();
        if ($communityCodeInput !== '') {
            $resolved = $this->tenantRepository->findByCommunityCode($communityCodeInput);
            if (!$resolved) {
                $flashBack('Ce code d’invitation n’est pas reconnu. Vérifiez-le ou laissez le champ vide.', 2);

                return Response::redirect(url('register'));
            }
            $tenant = $resolved;
        }
        if (!$tenant) {
            $flashBack('Aucune communauté de base n’est disponible pour le moment. Réessayez plus tard.', 2);

            return Response::redirect(url('register'));
        }
        $tenantId = (int) $tenant['id'];
        if (strcasecmp($email, UserRepository::SYSTEM_MODERATOR_EMAIL) === 0) {
            $flashBack('Cette adresse e-mail ne peut pas être utilisée.', 2);

            return Response::redirect(url('register'));
        }
        // Un même e-mail ne doit pas créer un 2ᵉ « identité » (autre mot de passe) sur une autre communauté.
        // Rejoindre une communauté se fait après connexion (invitation / candidature / code).
        // Les comptes déjà anonymisés / soft-deleted ne bloquent plus l’adresse.
        $this->accountDeletionService->releaseEmailHeldByDeletedAccounts($email);
        if ($this->userRepository->emailExistsGlobally($email)) {
            if ($this->userRepository->emailPendingDeletionGlobally($email)) {
                $flashBack(
                    'Un compte avec cette adresse est en cours de suppression. Reconnectez-vous pour annuler la demande, ou attendez la fin du délai de rétractation avant de créer un nouveau compte.',
                    2
                );

                return Response::redirect(url('login'));
            }
            $flashBack(
                'Un compte existe déjà avec cette adresse e-mail. Connectez-vous, puis rejoignez la communauté via une invitation ou une candidature — ne créez pas un second compte.',
                2
            );

            return Response::redirect(url('login'));
        }
        if ($this->userRepository->emailExistsInTenant($tenantId, $email)) {
            $flashBack('Cette adresse e-mail est déjà utilisée. Connectez-vous ou choisissez une autre adresse.', 2);

            return Response::redirect(url('register'));
        }
        if ($this->indicatorBlocklist->isEmailBlockedForTenant($tenantId, $email)) {
            $flashBack('Cette adresse ne peut pas être utilisée pour rejoindre cette communauté pour le moment.', 2);

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
                'callsign' => null,
                'role_id' => $roleId,
                'status' => 'pending_verification',
            ]);
            if ($roleId > 0) {
                $this->userRepository->syncOrganizationRoles($userId, $tenantId, [$roleId], null, true);
            }
            $this->personnelProfileRepository->ensureRecord($userId);
            $this->userProfileRepository->ensureRow($userId);
            $profilePatch = [
                'first_name' => $firstName !== '' ? $firstName : null,
                'last_name' => $lastName !== '' ? $lastName : null,
            ];
            if ($discordHandle !== '') {
                $profilePatch['discord_handle'] = $discordHandle;
            }
            $this->userProfileRepository->upsert($userId, $profilePatch);
            try {
                $this->personnelProfileRepository->update($userId, [
                    'character_name' => $displayName,
                ]);
            } catch (Throwable) {
            }
            if ($resolvedSteamId !== null) {
                $steamPatch = ['steam_id' => $resolvedSteamId];
                $steamPlayer = $this->steamWebApiService->fetchPublicPlayer($resolvedSteamId);
                if ($steamPlayer && trim((string) ($steamPlayer['avatar_url'] ?? '')) !== '') {
                    $steamPatch['avatar_url'] = trim((string) $steamPlayer['avatar_url']);
                }
                $this->userRepository->update($userId, $tenantId, $steamPatch);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('[auth.register] ' . $e->getMessage());
            $flashBack(UserFacingExceptionMapper::registrationMessage($e), 2);

            return Response::redirect(url('register'));
        }

        $this->auditService->log(AuditAction::AUTH_REGISTER, $tenantId, $userId, 'user', $userId, null, $email);

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expires = new \DateTimeImmutable('+15 minutes');
        $verifyUrl = url('verify-email') . '?token=' . rawurlencode($rawToken);
        $tenantName = email_community_label($tenant, (string) ($tenant['name'] ?? ''));
        $emailNorm = strtolower(trim($email));
        $sentOk = $this->emailService->sendUserRegisterConfirmation(
            $emailNorm,
            $displayName,
            $tenantName,
            $verifyUrl,
            15,
            $tenantId
        );

        Session::set('register_pending_email', $emailNorm);

        if (!$sentOk) {
            Session::flash(
                'error',
                'Votre compte a été créé, mais l’e-mail de confirmation n’a pas pu être envoyé. Utilisez « Renvoyer le lien » ci-dessous ou contactez le support si le problème persiste.'
            );

            return Response::redirect(url('register/check-email'));
        }

        try {
            $this->emailTokens->deletePendingForUserPurpose($userId, EmailTokenPurpose::REGISTER_CONFIRM);
            $this->emailTokens->create(
                $tenantId,
                $userId,
                EmailTokenPurpose::REGISTER_CONFIRM,
                $tokenHash,
                bin2hex(random_bytes(16)),
                $expires
            );
        } catch (Throwable $e) {
            error_log('[auth.register.token] ' . $e->getMessage());
            Session::flash(
                'warning',
                'Compte créé, mais le lien de confirmation n’a pas pu être enregistré. Utilisez « Renvoyer le lien » depuis la page suivante.'
            );

            return Response::redirect(url('register/check-email'));
        }

        $this->emailService->sendRegisterSecurityCompanion(
            $emailNorm,
            $displayName,
            $tenantName,
            url('account/preferences'),
            url('communities/create'),
            $tenantId
        );

        return Response::redirect(url('register/check-email'));
    }
}
