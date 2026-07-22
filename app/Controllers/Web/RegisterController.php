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
use App\Repositories\UserLegalIdentityRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Services\Profile\RecruitmentPresetPayloadService;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Auth\AuthService;
use App\Services\Email\EmailTokenPurpose;
use App\Services\EmailService;
use App\Services\Moderation\IndicatorBlocklistService;
use App\Services\Rbac\RbacService;
use App\Services\Steam\SteamWebApiService;
use Throwable;

final class RegisterController
{
    public function __construct(
        private AuthService $authService,
        private TenantRepository $tenantRepository,
        private UserRepository $userRepository,
        private UserLegalIdentityRepository $userLegalIdentityRepository,
        private UserProfileRepository $userProfileRepository,
        private RoleRepository $roleRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private RbacService $rbacService,
        private AuditService $auditService,
        private EmailService $emailService,
        private EmailTokenRepository $emailTokens,
        private IndicatorBlocklistService $indicatorBlocklist,
        private SteamWebApiService $steamWebApiService
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
        $email = trim((string) $request->input('email'));
        $password = (string) $request->input('password');
        $confirm = (string) $request->input('password_confirmation');
        $displayName = trim((string) $request->input('display_name'));
        $steamProfile = trim((string) $request->input('steam_profile'));
        $legalFirstName = trim((string) $request->input('legal_first_name'));
        $legalLastName = trim((string) $request->input('legal_last_name'));
        $legalBirthDate = RecruitmentPresetPayloadService::normalizeRpBirthDate((string) $request->input('legal_birth_date'));
        $legalCountry = trim((string) $request->input('legal_country'));
        if (function_exists('mb_substr')) {
            $legalCountry = mb_substr($legalCountry, 0, 100);
        } else {
            $legalCountry = substr($legalCountry, 0, 100);
        }
        $discordHandle = trim((string) $request->input('discord_handle'));
        if (function_exists('mb_substr')) {
            $discordHandle = mb_substr($discordHandle, 0, 120);
        } else {
            $discordHandle = substr($discordHandle, 0, 120);
        }
        $acceptTerms = (string) $request->input('accept_terms') === '1';
        $acceptIdentitySplit = (string) $request->input('accept_identity_split') === '1';
        $communityCodeInput = trim((string) $request->input('community_code'));

        $oldPayload = [
            'email' => $email,
            'display_name' => $displayName,
            'steam_profile' => $steamProfile,
            'legal_first_name' => $legalFirstName,
            'legal_last_name' => $legalLastName,
            'legal_birth_date' => (string) $request->input('legal_birth_date'),
            'legal_country' => $legalCountry,
            'discord_handle' => $discordHandle,
            'community_code' => $communityCodeInput,
            'accept_terms' => $acceptTerms ? '1' : '',
            'accept_identity_split' => $acceptIdentitySplit ? '1' : '',
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
                'display_name' => $displayName,
                'steam_profile' => $steamProfile,
                'legal_first_name' => $legalFirstName,
                'legal_last_name' => $legalLastName,
                'legal_birth_date' => $legalBirthDate,
                'legal_country' => $legalCountry,
                'discord_handle' => $discordHandle,
            ],
            [
                'email' => 'required|email',
                'password' => 'required|min:8',
                'password_confirmation' => 'required',
                'display_name' => 'required|min:2|max:100',
                'steam_profile' => 'max:512',
                'legal_first_name' => 'required|min:2|max:100',
                'legal_last_name' => 'required|min:2|max:100',
                'legal_birth_date' => 'max:12',
                'legal_country' => 'max:100',
                'discord_handle' => 'max:120',
            ]
        );
        if (!$v->validate()) {
            $step = 1;
            if ($legalFirstName === '' || strlen($legalFirstName) < 2 || $legalLastName === '' || strlen($legalLastName) < 2) {
                $step = 1;
            } elseif ($email === '' || $displayName === '' || strlen($password) < 8) {
                $step = 2;
            } else {
                $step = 2;
            }
            $flashBack('Vérifiez les informations saisies (champs obligatoires et formats).', $step);

            return Response::redirect(url('register'));
        }
        if ($password !== $confirm) {
            $flashBack('Les deux mots de passe ne sont pas identiques.', 2);

            return Response::redirect(url('register'));
        }
        if (!$acceptIdentitySplit || !$acceptTerms) {
            $flashBack('Merci de cocher les deux confirmations pour terminer l’inscription.', 3);

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
            $this->userLegalIdentityRepository->upsert($userId, $tenantId, [
                'first_name' => $legalFirstName,
                'last_name' => $legalLastName,
                'birth_date' => $legalBirthDate,
            ]);
            $this->userProfileRepository->ensureRow($userId);
            $profileUpsert = [
                'first_name' => $legalFirstName,
                'last_name' => $legalLastName,
            ];
            if ($legalBirthDate !== '') {
                $profileUpsert['birth_date'] = $legalBirthDate;
            }
            if ($legalCountry !== '') {
                $profileUpsert['country_of_residence'] = $legalCountry;
            }
            if ($discordHandle !== '') {
                $profileUpsert['discord_handle'] = $discordHandle;
            }
            $this->userProfileRepository->upsert($userId, $profileUpsert);
            if ($resolvedSteamId !== null) {
                $steamPatch = ['steam_id' => $resolvedSteamId];
                $steamPlayer = $this->steamWebApiService->fetchPublicPlayer($resolvedSteamId);
                if ($steamPlayer && trim((string) ($steamPlayer['avatar_url'] ?? '')) !== '') {
                    $steamPatch['avatar_url'] = trim((string) $steamPlayer['avatar_url']);
                }
                $this->userRepository->update($userId, $tenantId, $steamPatch);
            }
            $pdo->commit();
        } catch (Throwable) {
            $pdo->rollBack();
            Session::flash('error', 'Inscription impossible pour le moment. Réessayez.');

            return Response::redirect(url('register'));
        }

        $this->auditService->log(AuditAction::AUTH_REGISTER, $tenantId, $userId, 'user', $userId, null, $email);

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expires = new \DateTimeImmutable('+15 minutes');
        $verifyUrl = url('verify-email') . '?token=' . rawurlencode($rawToken);
        $tenantName = (string) ($tenant['name'] ?? 'Communauté');
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
            $detail = trim((string) ($this->emailService->getLastSendError() ?? ''));
            Session::flash(
                'error',
                'Compte créé, mais l’e-mail de confirmation n’a pas pu être envoyé'
                    . ($detail !== '' ? ' : ' . $detail : '')
                    . '. Vous pouvez utiliser « Renvoyer le lien » ci-dessous une fois la configuration corrigée.'
            );

            return Response::redirect(url('register/check-email'));
        }

        $this->emailTokens->deletePendingForUserPurpose($userId, EmailTokenPurpose::REGISTER_CONFIRM);
        $this->emailTokens->create(
            $tenantId,
            $userId,
            EmailTokenPurpose::REGISTER_CONFIRM,
            $tokenHash,
            bin2hex(random_bytes(16)),
            $expires
        );

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
