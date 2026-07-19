<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Core\Validator;
use App\Core\Database;
use App\Services\Auth\AuthService;
use App\Repositories\UserRepository;
use App\Repositories\UserLegalIdentityRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\RecruitmentPresetRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserUiPreferencesRepository;
use App\Services\Email\EmailEvents;
use App\Support\TenantEmailKind;
use App\Services\Profile\RecruitmentPresetPayloadService;
use App\Services\Profile\UserUiPreferencesValidationService;
use App\Services\User\UserProfileSlugService;
use App\Services\Steam\SteamWebApiService;
use App\Services\Community\MemberOnboardingService;
use App\Services\Auth\LoginSecurityOtpService;
use PDO;

class AccountController
{
    public function __construct(
        private AuthService $authService,
        private UserRepository $userRepository,
        private UserLegalIdentityRepository $userLegalIdentityRepository,
        private UserProfileRepository $userProfileRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private RecruitmentPresetRepository $recruitmentPresetRepository,
        private RecruitmentPresetPayloadService $recruitmentPresetPayloadService,
        private UserUiPreferencesRepository $userUiPreferencesRepository,
        private UserNotificationPreferencesRepository $userNotificationPreferencesRepository,
        private UserUiPreferencesValidationService $userUiPreferencesValidationService,
        private SteamWebApiService $steamWebApiService,
        private LoginSecurityOtpService $loginSecurityOtpService,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    private function accountView(string $content, string $title, array $data = []): Response
    {
        return Response::view('layout.main', array_merge([
            'content' => $content,
            'title' => $title,
            'accountHubPage' => true,
        ], $data));
    }

    public function index(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $uid = (int) $user['id'];
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $freshUser = $tenantId > 0 ? $this->userRepository->findById($uid, $tenantId) : null;
        $accountUser = $freshUser ?? $user;
        $accountProfile = $this->userProfileRepository->getByUserId($uid) ?? [];
        $legalIdentity = $this->userLegalIdentityRepository->getByUserId($uid) ?? [];
        if ($legalIdentity !== []) {
            $accountProfile['first_name'] = $legalIdentity['first_name'] ?? ($accountProfile['first_name'] ?? '');
            $accountProfile['last_name'] = $legalIdentity['last_name'] ?? ($accountProfile['last_name'] ?? '');
            $accountProfile['phone'] = $legalIdentity['phone'] ?? ($accountProfile['phone'] ?? '');
            $accountProfile['birth_date'] = $legalIdentity['birth_date'] ?? ($accountProfile['birth_date'] ?? '');
            $accountProfile['nationality'] = $legalIdentity['nationality'] ?? ($accountProfile['nationality'] ?? '');
        }
        $accountSnapshot = $this->buildAccountSnapshot($accountUser, $accountProfile);
        $onboardingSnapshot = (new MemberOnboardingService())->buildMemberSnapshot(
            $uid,
            $tenantId,
            (string) ($accountUser['created_at'] ?? '')
        );

        return $this->accountView('account.index', 'Mon compte', [
            'accountUser' => $accountUser,
            'accountProfile' => $accountProfile,
            'accountSnapshot' => $accountSnapshot,
            'systemHealth' => $this->getSystemHealth($tenantId),
            'onboardingSnapshot' => $onboardingSnapshot,
        ]);
    }

    /**
     * État de santé : connexion base, API ATAK (sans détail des tables).
     */
    private function getSystemHealth(int $tenantId): array
    {
        $health = [
            'database' => ['ok' => false, 'message' => ''],
            'api' => ['ok' => false, 'message' => '', 'url' => null],
        ];

        try {
            $pdo = Database::getPdo();
            $pdo->query('SELECT 1');
            $health['database']['ok'] = true;
            $health['database']['message'] = 'Les services de données répondent normalement.';
        } catch (\Throwable) {
            $health['database']['ok'] = false;
            $health['database']['message'] = 'Les services de données sont momentanément indisponibles. Réessayez plus tard ou contactez le support.';
        }

        if ($health['database']['ok']) {
            try {
                $pdo = Database::getPdo();
                $stmt = $pdo->prepare('SELECT node_url FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1');
                $stmt->execute([$tenantId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $base = atak_client_base_url($row ?: null);
                $health['api']['url'] = $base;
                $testUrl = url('api/atak/ping');
                $ctx = stream_context_create([
                    'http' => ['timeout' => 3, 'ignore_errors' => true],
                ]);
                $body = @file_get_contents($testUrl, false, $ctx);
                if ($body !== false) {
                    $health['api']['ok'] = true;
                    $health['api']['message'] = 'Le service cartographique répond.';
                } else {
                    $health['api']['message'] = 'Le service cartographique ne répond pas pour l’instant (réseau ou maintenance).';
                }
            } catch (\Throwable) {
                $health['api']['message'] = 'La vérification du service cartographique a échoué.';
            }
        } else {
            $health['api']['message'] = 'Vérification impossible tant que les données ne sont pas accessibles.';
        }

        return $health;
    }

    public function preferences(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $uid = (int) $user['id'];
        $tenantId = (int) $user['tenant_id'];
        $profile = $this->userProfileRepository->getByUserId($uid) ?? [];
        $legalIdentity = $this->userLegalIdentityRepository->getByUserId($uid) ?? [];
        if ($legalIdentity !== []) {
            $profile['first_name'] = $legalIdentity['first_name'] ?? ($profile['first_name'] ?? '');
            $profile['last_name'] = $legalIdentity['last_name'] ?? ($profile['last_name'] ?? '');
            $profile['phone'] = $legalIdentity['phone'] ?? ($profile['phone'] ?? '');
            $profile['birth_date'] = $legalIdentity['birth_date'] ?? ($profile['birth_date'] ?? '');
            $profile['nationality'] = $legalIdentity['nationality'] ?? ($profile['nationality'] ?? '');
        }
        $errors = [];
        $success = Session::getFlash('success');
        $error = Session::getFlash('error');
        $steamSyncReport = Session::getFlash('steam_sync_report');

        $uiPrefs = $this->userUiPreferencesRepository->getOrDefaults($uid, $tenantId);
        $notifRows = $this->userNotificationPreferencesRepository->listForUser($uid);
        $notifEmailCatalog = $this->notificationEmailCatalog();
        $notifEmailState = [];
        foreach ($notifEmailCatalog as $item) {
            $notifEmailState[$item['key']] = $this->isEmailNotificationEnabled($notifRows, $item['key']);
        }

        if ($request->isPost()) {
            if (!Csrf::validate($request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée.');
                return Response::redirect(url('account/preferences'));
            }
            $v = new Validator($request->all(), [
                'display_name' => 'max:100',
                'callsign' => 'max:50',
                'steam_id' => 'max:512',
                'timezone' => 'max:50',
                'language' => 'max:10',
                'profile_slug' => 'max:40',
            ]);
            $themeIn = $request->input('ui_theme');
            $densityIn = $request->input('ui_density');
            $uiPatch = [
                'theme' => ($themeIn !== null && (string) $themeIn !== '')
                    ? (string) $themeIn
                    : (string) ($uiPrefs['theme'] ?? 'system'),
                'density' => ($densityIn !== null && (string) $densityIn !== '')
                    ? (string) $densityIn
                    : (string) ($uiPrefs['density'] ?? 'comfortable'),
                'sidebar_collapsed' => (string) $request->input('ui_sidebar_collapsed') === '1',
            ];
            $vUi = $this->userUiPreferencesValidationService->validatePatch($uiPatch);
            if (!$v->validate()) {
                $errors = $v->errors();
            } elseif (!$vUi['ok']) {
                Session::flash('error', implode(' ', $vUi['errors']));
            } else {
                $rawSteam = trim((string) $request->input('steam_id'));
                $resolvedSteam = $rawSteam === '' ? null : $this->steamWebApiService->resolveSteamIdFromUserInput($rawSteam);
                if ($rawSteam !== '' && $resolvedSteam === null) {
                    Session::flash(
                        'error',
                        'Impossible de reconnaître cet identifiant Steam. Utilisez le numéro à 17 chiffres, une adresse de profil se terminant par « …/profiles/… », ou un lien « …/id/votre-pseudo » si le service Steam du serveur est configuré.'
                    );

                    return Response::redirect(url('account/preferences'));
                }
                $updateUser = [
                    'display_name' => trim((string) $request->input('display_name')),
                    'callsign' => trim((string) $request->input('callsign')),
                    'steam_id' => $resolvedSteam,
                ];
                $rawSlug = trim((string) $request->input('profile_slug'));
                if ($rawSlug === '') {
                    $updateUser['profile_slug'] = null;
                } else {
                    $ps = strtolower($rawSlug);
                    if (!UserProfileSlugService::isValidFormat($ps)) {
                        Session::flash('error', 'L’adresse courte de votre fiche est invalide : lettres minuscules, chiffres et tirets uniquement, 40 caractères maximum.');
                        return Response::redirect(url('account/preferences'));
                    }
                    if (UserProfileSlugService::isReserved($ps)) {
                        Session::flash('error', 'Cette adresse courte de fiche est réservée.');
                        return Response::redirect(url('account/preferences'));
                    }
                    if ($this->userRepository->isProfileSlugTaken($tenantId, $ps, $uid)) {
                        Session::flash('error', 'Cette adresse courte de fiche est déjà utilisée dans votre communauté.');
                        return Response::redirect(url('account/preferences'));
                    }
                    $updateUser['profile_slug'] = $ps;
                }
                $this->userRepository->update($uid, $tenantId, $updateUser);
                $this->userProfileRepository->upsert($uid, [
                    'timezone' => trim((string) $request->input('timezone')),
                    'language' => trim((string) $request->input('language')),
                ]);
                $this->userLegalIdentityRepository->upsert($uid, $tenantId, [
                    'first_name' => trim((string) $request->input('first_name')),
                    'last_name' => trim((string) $request->input('last_name')),
                    'phone' => trim((string) $request->input('phone')),
                    'birth_date' => trim((string) ($profile['birth_date'] ?? '')),
                    'nationality' => trim((string) ($profile['nationality'] ?? '')),
                ]);
                if (!empty($vUi['normalized'])) {
                    $this->userUiPreferencesRepository->upsert($uid, $tenantId, $vUi['normalized']);
                }
                $rawNotif = $request->input('notif_email');
                $notifInput = is_array($rawNotif) ? $rawNotif : [];
                foreach ($notifEmailCatalog as $item) {
                    $key = $item['key'];
                    $enabled = isset($notifInput[$key]);
                    $this->userNotificationPreferencesRepository->setEnabled($uid, $tenantId, 'email', $key, $enabled);
                }
                Session::set('display_name', trim((string) $request->input('display_name')));
                Session::set('callsign', trim((string) $request->input('callsign')));
                Session::flash('success', 'Préférences enregistrées.');
                return Response::redirect(url('account/preferences'));
            }
        }

        $accountSnapshot = $this->buildAccountSnapshot($user, $profile);
        $freshForOtp = $this->userRepository->findById($uid, $tenantId);
        $loginOtpVoluntaryActive = $this->userRepository->hasEmailLoginOtpEnabledColumn()
            && $freshForOtp !== null
            && (int) ($freshForOtp['email_login_otp_enabled'] ?? 0) === 1;

        return $this->accountView('account.preferences', 'Préférences', [
            'user' => $user,
            'profile' => $profile,
            'uiPrefs' => $uiPrefs,
            'notifEmailCatalog' => $notifEmailCatalog,
            'notifEmailState' => $notifEmailState,
            'accountSnapshot' => $accountSnapshot,
            'timezoneSuggestions' => $this->timezoneSuggestions(),
            'errors' => $errors,
            'success' => $success,
            'error' => $error,
            'steamWebConfigured' => $this->steamWebApiService->isConfigured(),
            'steamSyncReport' => is_array($steamSyncReport) ? $steamSyncReport : null,
            'loginOtpMandatory' => $this->loginSecurityOtpService->isMandatoryForUserId($uid),
            'loginOtpVoluntaryActive' => $loginOtpVoluntaryActive,
            'loginOtpTtlMinutes' => LoginSecurityOtpService::TTL_MINUTES,
        ]);
    }

    public function sendLoginOtpMailboxTest(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user || !$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée ou accès refusé.');

            return Response::redirect(url('account/preferences'));
        }
        $uid = (int) ($user['id'] ?? 0);
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $result = $this->loginSecurityOtpService->sendMailboxSelfTest($uid, $tenantId);
        if ($result['ok']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }

        return Response::redirect(url('account/preferences'));
    }

    public function syncSteamProfile(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user || !$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée ou accès refusé.');

            return Response::redirect(url('account/preferences'));
        }
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $uid = (int) $user['id'];
        if ($tenantId < 1) {
            Session::flash('error', 'Communauté introuvable.');

            return Response::redirect(url('account/preferences'));
        }
        if (!$this->steamWebApiService->isConfigured()) {
            Session::flash('error', 'L’import depuis Steam n’est pas configuré sur ce serveur. Contactez l’administration.');

            return Response::redirect(url('account/preferences'));
        }
        $row = $this->userRepository->findById($uid, $tenantId);
        if (!$row) {
            Session::flash('error', 'Compte introuvable.');

            return Response::redirect(url('account/preferences'));
        }
        $dbSteam = trim((string) ($row['steam_id'] ?? ''));
        $steamId = $dbSteam;
        $postedSteam = trim((string) $request->input('steam_id'));
        $steamIdJustSavedFromForm = false;
        if ($postedSteam !== '') {
            $resolvedPosted = $this->steamWebApiService->resolveSteamIdFromUserInput($postedSteam);
            if ($resolvedPosted === null) {
                Session::flash('error', 'Impossible de reconnaître l’identifiant Steam indiqué dans le formulaire. Vérifiez le numéro ou l’adresse du profil public.');

                return Response::redirect(url('account/preferences'));
            }
            if ($resolvedPosted !== $steamId) {
                $this->userRepository->update($uid, $tenantId, ['steam_id' => $resolvedPosted]);
                $steamId = $resolvedPosted;
                $steamIdJustSavedFromForm = true;
            }
        }
        if ($steamId === '') {
            Session::flash('error', 'Indiquez un identifiant Steam dans le formulaire (numéro ou adresse de profil), puis relancez la synchronisation.');

            return Response::redirect(url('account/preferences'));
        }
        $applyName = $request->input('apply_steam_display_name') === '1';
        $steps = [];
        if ($steamIdJustSavedFromForm) {
            $steps[] = [
                'key' => 'persist_steam',
                'label' => 'Enregistrement de l’identifiant',
                'ok' => true,
                'detail' => 'La valeur saisie dans le formulaire a été enregistrée sur votre compte avant la lecture du profil public.',
            ];
        }
        $steps[] = [
            'key' => 'account',
            'label' => 'Lecture du compte',
            'ok' => true,
            'detail' => $steamIdJustSavedFromForm
                ? 'Identifiant Steam prêt pour la liaison avec le service.'
                : 'Identifiant Steam déjà enregistré sur votre dossier.',
        ];
        $summary = $this->steamWebApiService->fetchPublicPlayer($steamId);
        if ($summary === null) {
            $steps[] = [
                'key' => 'steam_api',
                'label' => 'Lecture du profil public',
                'ok' => false,
                'detail' => 'Le service n’a pas renvoyé de profil pour cet identifiant. Vérifiez-le ou réessayez plus tard.',
            ];
            Session::flash('steam_sync_report', [
                'ok' => false,
                'finished_at' => date('d/m/Y \à H:i'),
                'steps' => $steps,
                'data' => [],
            ]);
            Session::flash('error', 'Impossible de récupérer le profil public pour cet identifiant. Vérifiez l’identifiant ou réessayez plus tard.');

            return Response::redirect(url('account/preferences'));
        }
        $steps[] = [
            'key' => 'steam_api',
            'label' => 'Lecture du profil public',
            'ok' => true,
            'detail' => 'Pseudo et visuel du profil public récupérés.',
        ];
        $patch = [];
        if ($summary['avatar_url'] !== '') {
            $patch['avatar_url'] = function_exists('mb_substr')
                ? mb_substr($summary['avatar_url'], 0, 500)
                : substr($summary['avatar_url'], 0, 500);
        }
        if ($applyName && $summary['personaname'] !== '') {
            $patch['display_name'] = function_exists('mb_substr')
                ? mb_substr($summary['personaname'], 0, 100)
                : substr($summary['personaname'], 0, 100);
        }
        if ($patch === []) {
            $steps[] = [
                'key' => 'apply',
                'label' => 'Mise à jour du dossier',
                'ok' => false,
                'detail' => 'Aucune photo ni nom exploitable n’a été renvoyé pour ce profil.',
            ];
            Session::flash('steam_sync_report', [
                'ok' => false,
                'finished_at' => date('d/m/Y \à H:i'),
                'steps' => $steps,
                'data' => [
                    'public_pseudo' => $summary['personaname'],
                ],
            ]);
            Session::flash('error', 'Aucune donnée exploitable n’a été renvoyée pour ce profil.');

            return Response::redirect(url('account/preferences'));
        }
        $this->userRepository->update($uid, $tenantId, $patch);
        $fresh = $this->userRepository->findById($uid, $tenantId);
        if ($fresh) {
            Session::set('display_name', (string) ($fresh['display_name'] ?? ''));
            Session::set('callsign', (string) ($fresh['callsign'] ?? ''));
        }
        $steps[] = [
            'key' => 'apply',
            'label' => 'Mise à jour du dossier',
            'ok' => true,
            'detail' => isset($patch['avatar_url']) ? 'Photo du compte actualisée.' : 'Nom d’affichage actualisé.',
        ];
        Session::flash('steam_sync_report', [
            'ok' => true,
            'finished_at' => date('d/m/Y \à H:i'),
            'steps' => $steps,
            'data' => [
                'public_pseudo' => $summary['personaname'],
                'avatar_updated' => isset($patch['avatar_url']),
                'display_name_updated' => isset($patch['display_name']),
                'steam_id' => $summary['steam_id'],
            ],
        ]);
        Session::flash(
            'success',
            $applyName
                ? 'Photo et nom d’affichage mis à jour depuis le profil public Steam.'
                : 'Photo du compte mise à jour depuis le profil public Steam.'
        );

        return Response::redirect(url('account/preferences'));
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function isEmailNotificationEnabled(array $rows, string $eventKey): bool
    {
        foreach ($rows as $r) {
            if (($r['channel'] ?? '') === 'email' && ($r['event_key'] ?? '') === $eventKey) {
                return (bool) ((int) ($r['enabled'] ?? 0));
            }
        }

        return true;
    }

    /**
     * @return list<array{key: string, label: string, hint: string, group: string}>
     */
    private function notificationEmailCatalog(): array
    {
        $items = [
            [
                'key' => EmailEvents::NEW_DEVICE_LOGIN,
                'label' => 'Nouvel appareil ou navigateur',
                'hint' => 'Lorsqu’une connexion est détectée depuis un équipement inconnu.',
                'group' => 'Sécurité',
            ],
            [
                'key' => EmailEvents::MULTIPLE_LOGIN_ATTEMPTS,
                'label' => 'Tentatives de connexion multiples',
                'hint' => 'Alerter en cas d’échecs répétés sur votre identifiant.',
                'group' => 'Sécurité',
            ],
            [
                'key' => EmailEvents::PROFILE_INCOMPLETE_REMINDER,
                'label' => 'Rappel profil incomplet',
                'hint' => 'Relances pour finaliser votre dossier ou votre profil.',
                'group' => 'Compte',
            ],
            [
                'key' => EmailEvents::TENANT_USER_SETUP,
                'label' => 'Invitation à définir votre mot de passe',
                'hint' => 'Lorsque l’équipe crée votre compte ou vous ouvre un premier accès avec un lien sécurisé.',
                'group' => 'Compte',
            ],
            [
                'key' => EmailEvents::ATTENDANCE_REMINDER,
                'label' => 'Rappels d’événements (pointage)',
                'hint' => 'Avant les missions ou sessions auxquelles vous participez.',
                'group' => 'Événements',
            ],
            [
                'key' => EmailEvents::ATTENDANCE_RSVP_CONFIRM,
                'label' => 'Confirmation de participation (RSVP)',
                'hint' => 'Accusés de réception de vos inscriptions.',
                'group' => 'Événements',
            ],
            [
                'key' => EmailEvents::ATTENDANCE_EVENT_CANCELLED,
                'label' => 'Annulation d’événement',
                'hint' => 'Lorsqu’une activité à laquelle vous étiez inscrit est annulée.',
                'group' => 'Événements',
            ],
            [
                'key' => EmailEvents::ATTENDANCE_CHECKIN_CONFIRM,
                'label' => 'Confirmation de pointage',
                'hint' => 'Validation de votre présence enregistrée.',
                'group' => 'Événements',
            ],
            [
                'key' => EmailEvents::ATTENDANCE_RSVP_ORGANIZER,
                'label' => 'Participation mise à jour sur vos activités',
                'hint' => 'Lorsqu’un membre change sa réponse pour une activité que vous avez créée.',
                'group' => 'Événements',
            ],
            [
                'key' => EmailEvents::COMMUNITY_REPORT_RECEIPT,
                'label' => 'Accusé de réception de votre signalement',
                'hint' => 'Confirmation que votre signalement ou demande a bien été transmis à l’équipe.',
                'group' => 'Signalements et modération',
            ],
            [
                'key' => EmailEvents::COMMUNITY_REPORT_HANDLED,
                'label' => 'Clôture de votre signalement',
                'hint' => 'Lorsque l’équipe marque votre demande comme traitée.',
                'group' => 'Signalements et modération',
            ],
            [
                'key' => EmailEvents::COMMUNITY_REPORT_REOPENED_REPORTER,
                'label' => 'Réouverture de l’examen de votre signalement',
                'hint' => 'Lorsque l’équipe rouvre un dossier que vous aviez signalé.',
                'group' => 'Signalements et modération',
            ],
        ];
        if (function_exists('forum_user_can_moderate') && forum_user_can_moderate()) {
            $items[] = [
                'key' => EmailEvents::COMMUNITY_REPORT_NEW_STAFF,
                'label' => 'Nouveaux signalements pour l’équipe',
                'hint' => 'Lorsqu’un membre envoie un signalement ou une demande à traiter.',
                'group' => 'Signalements et modération',
            ];
            $items[] = [
                'key' => EmailEvents::COMMUNITY_REPORT_REOPENED_STAFF,
                'label' => 'Signalement rouvert (équipe de modération)',
                'hint' => 'Lorsqu’un dossier clos est remis dans la file à traiter.',
                'group' => 'Signalements et modération',
            ];
        }

        $items[] = [
            'key' => EmailEvents::TENANT_INTERNAL_MESSAGE_THREAD,
            'label' => 'Messagerie interne (nouveaux messages)',
            'hint' => 'Lorsqu’un autre participant écrit dans une conversation dont vous faites partie.',
            'group' => 'Communauté',
        ];

        $items[] = [
            'key' => EmailEvents::NEW_COMMUNITY_MEMBER,
            'label' => 'Nouveaux membres (équipe)',
            'hint' => 'Résumé pour les responsables lorsqu’un membre rejoint la communauté ou confirme son inscription.',
            'group' => 'Communauté',
        ];

        $formationItems = [
            [
                'key' => EmailEvents::TRAINING_ENROLLMENT_ASSIGNED,
                'label' => 'Formation qui vous est assignée',
                'hint' => 'Lorsque l’équipe vous inscrit à un parcours (hors simple auto-inscription).',
            ],
            [
                'key' => EmailEvents::TRAINING_COURSE_COMPLETED,
                'label' => 'Parcours de formation terminé',
                'hint' => 'Message de synthèse lorsque vous avez validé toutes les exigences d’un parcours.',
            ],
            [
                'key' => EmailEvents::TRAINING_CERTIFICATE_AVAILABLE,
                'label' => 'Attestation de formation disponible',
                'hint' => 'Lorsque votre document d’attestation est prêt à être consulté ou téléchargé.',
            ],
            [
                'key' => EmailEvents::TRAINING_ENROLLMENT_PENDING_APPROVAL,
                'label' => 'Demandes d’inscription à valider (formateurs)',
                'hint' => 'Lorsqu’un membre demande à rejoindre une formation soumise à validation.',
            ],
            [
                'key' => EmailEvents::TRAINING_SELF_ENROLL_APPROVED,
                'label' => 'Demande d’inscription acceptée',
                'hint' => 'Lorsque les formateurs acceptent votre demande pour un parcours.',
            ],
            [
                'key' => EmailEvents::TRAINING_SELF_ENROLL_DECLINED,
                'label' => 'Demande d’inscription refusée',
                'hint' => 'Lorsque les formateurs ne valident pas votre demande pour un parcours.',
            ],
            [
                'key' => EmailEvents::TRAINING_MODULE_BLOCKED_STAFF,
                'label' => 'Apprenant en difficulté sur un module (formateurs)',
                'hint' => 'Lorsqu’un inscrit signale un blocage et qu’une aide peut être nécessaire.',
            ],
            [
                'key' => EmailEvents::TRAINING_PUBLISH_ELEVATION_REQUEST,
                'label' => 'Demande de droit de publication (Studio)',
                'hint' => 'Lorsqu’un concepteur demande à pouvoir publier une fiche formation.',
            ],
            [
                'key' => EmailEvents::EFFECTIFS_ELEVATION_REQUEST,
                'label' => 'Demande d’élévation RH (effectifs)',
                'hint' => 'Lorsqu’un membre du bureau effectifs demande une évolution de grade, de rôle ou de droits pour un autre membre.',
            ],
            [
                'key' => EmailEvents::TRAINING_COURSE_SESSION_SCHEDULED_LEARNER,
                'label' => 'Nouveau créneau sur une formation suivie',
                'hint' => 'Lorsqu’une séance ou un créneau est ajouté sur un parcours auquel vous participez encore.',
            ],
        ];
        foreach ($formationItems as $fi) {
            $items[] = [
                'key' => $fi['key'],
                'label' => $fi['label'],
                'hint' => $fi['hint'],
                'group' => 'Formations',
            ];
        }

        $recrutementItems = [
            [
                'key' => EmailEvents::RECRUITMENT_OPENING_PUBLISHED_STAFF,
                'label' => 'Nouvelle offre de poste publiée (équipe recrutement)',
                'hint' => 'Lorsqu’une offre passe de brouillon à publiée sur la vitrine.',
            ],
            [
                'key' => EmailEvents::ENLISTMENT_SUBMITTED_STAFF,
                'label' => 'Nouvelle candidature (équipe recrutement)',
                'hint' => 'Lorsqu’un dossier de candidature est déposé pour votre communauté.',
            ],
            [
                'key' => EmailEvents::ENLISTMENT_PORTAL_CANDIDATE_REPLY_STAFF,
                'label' => 'Réponse candidat sur le suivi en ligne',
                'hint' => 'Lorsqu’un candidat écrit un message depuis son lien de suivi sécurisé (hors simple dépôt initial).',
            ],
            [
                'key' => EmailEvents::ENLISTMENT_PORTAL_AUTOMOD_ALERT,
                'label' => 'Modération automatique du portail recrutement',
                'hint' => 'Lorsque le filtre refuse un message (candidat ou équipe) : alerte et synthèse masquée.',
            ],
            [
                'key' => EmailEvents::ENLISTMENT_ACCEPTED_CANDIDATE,
                'label' => 'Candidature acceptée (candidat)',
                'hint' => 'Message de confirmation lorsque votre candidature est acceptée.',
            ],
            [
                'key' => EmailEvents::ENLISTMENT_ACCEPTED_STAFF,
                'label' => 'Candidature acceptée (équipe)',
                'hint' => 'Résumé pour les personnes habilitées au recrutement lorsqu’une candidature est acceptée.',
            ],
        ];
        foreach ($recrutementItems as $ri) {
            $items[] = [
                'key' => $ri['key'],
                'label' => $ri['label'],
                'hint' => $ri['hint'],
                'group' => 'Recrutement',
            ];
        }

        $items[] = [
            'key' => EmailEvents::ROLEPLAY_FOLLOWUP_UPDATED,
            'label' => 'Mises à jour du suivi roleplay et du tutorat',
            'hint' => 'Lorsque votre dossier d’immersion est modifié par l’équipe, ou lorsque vous suivez un membre en tant que tuteur.',
            'group' => 'Immersion',
        ];

        $encadrement = 'Messages de l’encadrement';
        $items[] = [
            'key' => TenantEmailKind::notificationPreferenceKey(TenantEmailKind::ORBAT),
            'label' => 'Informations sur la structure et les affectations',
            'hint' => 'Messages envoyés par les personnes habilitées concernant l’organisation des effectifs.',
            'group' => $encadrement,
        ];
        $items[] = [
            'key' => TenantEmailKind::notificationPreferenceKey(TenantEmailKind::MISSION),
            'label' => 'Pilotage opérationnel',
            'hint' => 'Messages liés au suivi des opérations et du planning interne.',
            'group' => $encadrement,
        ];
        $items[] = [
            'key' => TenantEmailKind::notificationPreferenceKey(TenantEmailKind::ACTIVITY),
            'label' => 'Activités et présence',
            'hint' => 'Messages concernant les activités collectives et la coordination.',
            'group' => $encadrement,
        ];
        $items[] = [
            'key' => TenantEmailKind::notificationPreferenceKey(TenantEmailKind::CUSTOM),
            'label' => 'Messages généraux à l’équipe',
            'hint' => 'Annonces libres adressées aux membres par l’encadrement.',
            'group' => $encadrement,
        ];

        return $items;
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed>|null $profile
     * @return array{email_masked: string, email_verified: bool, last_login_label: string|null}
     */
    private function buildAccountSnapshot(array $user, ?array $profile): array
    {
        $email = (string) ($user['email'] ?? '');
        $masked = $this->maskEmailForDisplay($email);
        $verifiedAt = $user['email_verified_at'] ?? null;
        $verified = $verifiedAt !== null && $verifiedAt !== '' && $verifiedAt !== '0000-00-00 00:00:00';

        $tz = trim((string) ($profile['timezone'] ?? 'Europe/Paris'));
        if ($tz === '') {
            $tz = 'Europe/Paris';
        }
        $lastLabel = null;
        $rawLast = $user['last_login_at'] ?? null;
        if (is_string($rawLast) && $rawLast !== '' && $rawLast !== '0000-00-00 00:00:00') {
            try {
                $dt = new \DateTimeImmutable($rawLast);
                $dt = $dt->setTimezone(new \DateTimeZone($tz));
                $lastLabel = $dt->format('d/m/Y H:i') . ' (' . $tz . ')';
            } catch (\Throwable) {
                $lastLabel = (string) $rawLast;
            }
        }

        return [
            'email_masked' => $masked,
            'email_verified' => $verified,
            'last_login_label' => $lastLabel,
        ];
    }

    private function maskEmailForDisplay(string $email): string
    {
        $email = trim($email);
        $at = strpos($email, '@');
        if ($at === false || $at < 1) {
            return $email === '' ? '—' : $email;
        }
        $local = substr($email, 0, $at);
        $domain = substr($email, $at + 1);
        $n = strlen($local);
        $keep = min(2, $n);
        $prefix = $keep > 0 ? substr($local, 0, $keep) : '';

        return $prefix . '•••@' . $domain;
    }

    /** @return list<string> */
    private function timezoneSuggestions(): array
    {
        return [
            'UTC',
            'Europe/Paris',
            'Europe/Brussels',
            'Europe/Zurich',
            'Europe/Berlin',
            'Europe/London',
            'Europe/Madrid',
            'America/Montreal',
            'America/New_York',
            'America/Los_Angeles',
            'Pacific/Tahiti',
            'Asia/Tokyo',
            'Australia/Sydney',
        ];
    }

    public function mail(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $uid = (int) ($user['id'] ?? 0);
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $fresh = $this->userRepository->findById($uid, $tenantId);
        $user = $fresh !== null ? array_merge($user, $fresh) : $user;

        $errors = [];
        $otpErrors = [];
        $success = Session::getFlash('success');
        $error = Session::getFlash('error');
        $hasOtpColumn = $this->userRepository->hasEmailLoginOtpEnabledColumn();
        $loginOtpForcedByRole = $this->loginSecurityOtpService->isMandatoryForUserId($uid);
        $emailLoginOtpEnabled = $hasOtpColumn && (int) ($user['email_login_otp_enabled'] ?? 0) === 1;

        if ($request->isPost()) {
            if (!Csrf::validate($request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée.');

                return Response::redirect(url('account/mail'));
            }

            if ($hasOtpColumn && (string) $request->input('account_mail_section') === 'email_login_otp') {
                $otpPassword = (string) $request->input('otp_toggle_password');
                if ($otpPassword === '' || !password_verify($otpPassword, (string) ($user['password_hash'] ?? ''))) {
                    $otpErrors['otp_toggle_password'] = ['Mot de passe actuel incorrect.'];
                } else {
                    $want = $request->input('email_login_otp_enabled') !== null
                        && (string) $request->input('email_login_otp_enabled') === '1';
                    if ($loginOtpForcedByRole && !$want) {
                        $otpErrors['email_login_otp_enabled'] = ['Votre rôle impose déjà cette protection : elle ne peut pas être désactivée.'];
                    } else {
                        $this->userRepository->update($uid, $tenantId, ['email_login_otp_enabled' => $want ? 1 : 0]);
                        Session::flash(
                            'success',
                            $want
                                ? 'Double vérification par e-mail activée. Un code vous sera demandé à chaque connexion.'
                                : 'Double vérification par e-mail désactivée.'
                        );

                        return Response::redirect(url('account/mail'));
                    }
                }
            } else {
                $email = trim((string) $request->input('email'));
                $email_confirmation = trim((string) $request->input('email_confirmation'));
                $password = $request->input('password');

                $v = new Validator([
                    'email' => $email,
                    'email_confirmation' => $email_confirmation,
                    'password' => $password,
                ], [
                    'email' => 'required|email',
                    'email_confirmation' => 'required',
                    'password' => 'required',
                ]);
                if (!$v->validate()) {
                    $errors = $v->errors();
                } elseif ($email !== $email_confirmation) {
                    $errors['email_confirmation'] = ['Les deux adresses doivent être identiques.'];
                } elseif (!password_verify((string) $password, (string) ($user['password_hash'] ?? ''))) {
                    $errors['password'] = ['Mot de passe actuel incorrect.'];
                } elseif ($this->userRepository->emailExistsInTenant($tenantId, $email, $uid)) {
                    $errors['email'] = ['Cette adresse est déjà utilisée par un autre compte.'];
                } else {
                    $this->userRepository->update($uid, $tenantId, ['email' => $email]);
                    Session::set('email', $email);
                    Session::flash('success', 'Adresse e-mail mise à jour.');

                    return Response::redirect(url('account/mail'));
                }
            }
        }

        return $this->accountView('account.mail', 'Adresse e-mail', [
            'user' => $user,
            'errors' => $errors,
            'otpErrors' => $otpErrors,
            'success' => $success,
            'error' => $error,
            'hasOtpColumn' => $hasOtpColumn,
            'loginOtpForcedByRole' => $loginOtpForcedByRole,
            'emailLoginOtpEnabled' => $emailLoginOtpEnabled,
        ]);
    }

    public function image(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $errors = [];
        $success = Session::getFlash('success');
        $error = Session::getFlash('error');

        if ($request->isPost()) {
            if (!Csrf::validate($request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée.');
                return Response::redirect(url('account/image'));
            }
            $file = $_FILES['avatar'] ?? null;
            if (!$file || ($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
                $errors['avatar'] = ['Veuillez sélectionner une image (JPG, PNG ou WebP, max 2 Mo).'];
            } else {
                $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime, $allowed, true) || $file['size'] > 2 * 1024 * 1024) {
                    $errors['avatar'] = ['Format non autorisé ou fichier trop volumineux (max 2 Mo).'];
                } else {
                    $dir = base_path('public/uploads/avatars');
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    $ext = match ($mime) {
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        default => 'jpg',
                    };
                    $name = $user['id'] . '_' . time() . '.' . $ext;
                    $path = $dir . DIRECTORY_SEPARATOR . $name;
                    if (move_uploaded_file($file['tmp_name'], $path)) {
                        $urlPath = 'uploads/avatars/' . $name;
                        $this->userRepository->update((int) $user['id'], (int) $user['tenant_id'], ['avatar_url' => $urlPath]);
                        Session::flash('success', 'Photo de profil mise à jour.');
                        return Response::redirect(url('account/image'));
                    }
                    $errors['avatar'] = ['Impossible d\'enregistrer le fichier.'];
                }
            }
        }

        return $this->accountView('account.image', 'Photo de compte', [
            'user' => $user,
            'errors' => $errors,
            'success' => $success,
            'error' => $error,
        ]);
    }

    /** Portrait personnage (fiche, ORBAT, briefing) — distinct de l'avatar compte. */
    public function portrait(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $errors = [];
        $success = Session::getFlash('success');
        $error = Session::getFlash('error');
        $personnelProfile = $this->personnelProfileRepository->getByUserId((int) $user['id']);

        if ($request->isPost()) {
            if (!Csrf::validate($request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée.');
                return Response::redirect(url('account/portrait'));
            }
            $file = $_FILES['portrait'] ?? null;
            if (!$file || ($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
                $errors['portrait'] = ['Veuillez sélectionner une image (JPG, PNG ou WebP, max 2 Mo).'];
            } else {
                $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime, $allowed, true) || $file['size'] > 2 * 1024 * 1024) {
                    $errors['portrait'] = ['Format non autorisé ou fichier trop volumineux (max 2 Mo).'];
                } else {
                    $dir = base_path('public/uploads/portraits');
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    $ext = match ($mime) {
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        default => 'jpg',
                    };
                    $name = $user['id'] . '_' . time() . '.' . $ext;
                    $path = $dir . DIRECTORY_SEPARATOR . $name;
                    if (move_uploaded_file($file['tmp_name'], $path)) {
                        $urlPath = 'uploads/portraits/' . $name;
                        $this->personnelProfileRepository->updatePortraitPath((int) $user['id'], $urlPath);
                        Session::flash('success', 'Portrait opérateur mis à jour.');
                        return Response::redirect(url('account/portrait'));
                    }
                    $errors['portrait'] = ['Impossible d\'enregistrer le fichier.'];
                }
            }
        }

        return $this->accountView('account.portrait', 'Portrait opérateur', [
            'user' => $user,
            'personnelProfile' => $personnelProfile,
            'errors' => $errors,
            'success' => $success,
            'error' => $error,
        ]);
    }

    /** Bannière du bandeau haut du menu session / profil. */
    public function banner(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        if (!$this->userRepository->hasProfileBannerUrlColumn()) {
            Session::flash('error', 'La personnalisation de la couverture n’est pas encore disponible. Relancez les mises à jour de la base, puis réessayez.');
            return Response::redirect(url('account'));
        }

        $errors = [];
        $success = Session::getFlash('success');
        $error = Session::getFlash('error');
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $freshUser = $tenantId > 0 ? $this->userRepository->findById((int) $user['id'], $tenantId) : null;
        $accountUser = $freshUser ?? $user;

        if ($request->isPost()) {
            if (!Csrf::validate($request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée.');
                return Response::redirect(url('account/banner'));
            }

            if ($request->input('remove_banner') === '1') {
                $this->userRepository->update((int) $user['id'], $tenantId, ['profile_banner_url' => null]);
                Session::flash('success', 'Couverture du menu session retirée.');
                return Response::redirect(url('account/banner'));
            }

            $file = $_FILES['banner'] ?? null;
            if (!$file || ($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
                $errors['banner'] = ['Veuillez sélectionner une image (JPG, PNG ou WebP, max 2 Mo).'];
            } else {
                $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime, $allowed, true) || $file['size'] > 2 * 1024 * 1024) {
                    $errors['banner'] = ['Format non autorisé ou fichier trop volumineux (max 2 Mo).'];
                } else {
                    $dir = base_path('public/uploads/banners');
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    $ext = match ($mime) {
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        default => 'jpg',
                    };
                    $name = $user['id'] . '_' . time() . '.' . $ext;
                    $path = $dir . DIRECTORY_SEPARATOR . $name;
                    if (move_uploaded_file($file['tmp_name'], $path)) {
                        $urlPath = 'uploads/banners/' . $name;
                        $this->userRepository->update((int) $user['id'], $tenantId, ['profile_banner_url' => $urlPath]);
                        Session::flash('success', 'Couverture du menu session mise à jour.');
                        return Response::redirect(url('account/banner'));
                    }
                    $errors['banner'] = ['Impossible d\'enregistrer le fichier.'];
                }
            }
        }

        return $this->accountView('account.banner', 'Couverture du menu session', [
            'user' => $accountUser,
            'errors' => $errors,
            'success' => $success,
            'error' => $error,
        ]);
    }

    public function password(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $errors = [];
        $success = Session::getFlash('success');
        $error = Session::getFlash('error');

        if ($request->isPost()) {
            if (!Csrf::validate($request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée.');
                return Response::redirect(url('account/password'));
            }
            $current = $request->input('current_password');
            $new = $request->input('new_password');
            $confirm = $request->input('new_password_confirmation');

            $v = new Validator([
                'current_password' => $current,
                'new_password' => $new,
                'new_password_confirmation' => $confirm,
            ], [
                'current_password' => 'required',
                'new_password' => 'required|min:8',
                'new_password_confirmation' => 'required',
            ]);
            if (!$v->validate()) {
                $errors = $v->errors();
            } elseif (!password_verify((string) $current, $user['password_hash'])) {
                $errors['current_password'] = ['Mot de passe actuel incorrect.'];
            } elseif ($new !== $confirm) {
                $errors['new_password_confirmation'] = ['Les deux mots de passe doivent être identiques.'];
            } else {
                $hash = password_hash((string) $new, PASSWORD_ARGON2ID);
                $this->userRepository->update((int) $user['id'], (int) $user['tenant_id'], ['password_hash' => $hash]);
                Session::flash('success', 'Mot de passe modifié.');
                return Response::redirect(url('account/password'));
            }
        }

        return $this->accountView('account.password', 'Mot de passe', [
            'errors' => $errors,
            'success' => $success,
            'error' => $error,
        ]);
    }

    public function recruitmentPresets(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $presets = $this->recruitmentPresetRepository->listForUser((int) $user['id']);
        $success = Session::getFlash('success');
        $error = Session::getFlash('error');

        return $this->accountView('account.recruitment_presets', 'Profils de candidature', [
            'presets' => $presets,
            'success' => $success,
            'error' => $error,
        ]);
    }

    public function recruitmentPresetsCreate(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $uid = (int) $user['id'];
        $errors = [];

        if ($request->isPost()) {
            if (!Csrf::validate($request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée.');
                return Response::redirect(url('account/recruitment-presets/create'));
            }
            $label = trim((string) $request->input('label'));
            if ($label === '') {
                $errors['label'] = ['Nom du profil obligatoire.'];
            }
            $existing = $this->recruitmentPresetPayloadService->normalizeDecodedPayload([]);
            $removeImage = (string) $request->input('remove_character_image') === '1';
            $payload = $this->recruitmentPresetPayloadService->buildPayloadFromRequest($request, $existing, $removeImage);
            $this->applyRecruitmentPresetImageUpload($uid, $payload, null, $errors);
            if (empty($errors)) {
                $this->recruitmentPresetRepository->create($uid, $label, $payload);
                $this->applyPersonnelAutoFillFromRecruitmentPreset($uid, $payload);
                Session::flash('success', 'Profil enregistré.');
                return Response::redirect(url('account/recruitment-presets'));
            }
        }

        return $this->accountView('account.recruitment_presets_form', 'Nouveau profil de candidature', [
            'preset' => null,
            'formAction' => url('account/recruitment-presets/create'),
            'errors' => $errors,
            'payloadDefaults' => $this->recruitmentPresetPayloadService->normalizeDecodedPayload([]),
        ]);
    }

    public function recruitmentPresetsEdit(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $uid = (int) $user['id'];
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->recruitmentPresetRepository->findForUser($id, $uid) : null;
        if (!$row) {
            Session::flash('error', 'Profil introuvable.');
            return Response::redirect(url('account/recruitment-presets'));
        }
        $errors = [];

        if ($request->isPost()) {
            if (!Csrf::validate($request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée.');
                return Response::redirect(url('account/recruitment-presets/' . $id . '/edit'));
            }
            $label = trim((string) $request->input('label'));
            if ($label === '') {
                $errors['label'] = ['Nom du profil obligatoire.'];
            }
            $prevPayload = is_array($row['payload'] ?? null) ? $row['payload'] : [];
            $existing = $this->recruitmentPresetPayloadService->normalizeDecodedPayload($prevPayload);
            $removeImage = (string) $request->input('remove_character_image') === '1';
            if ($removeImage) {
                $prevUrl = is_array($existing['rp'] ?? null) ? trim((string) ($existing['rp']['image_url'] ?? '')) : '';
                if ($prevUrl !== '') {
                    $this->recruitmentPresetPayloadService->deleteCharacterImageFile($prevUrl);
                }
            }
            $payload = $this->recruitmentPresetPayloadService->buildPayloadFromRequest($request, $existing, $removeImage);
            $this->applyRecruitmentPresetImageUpload($uid, $payload, $existing, $errors);
            if (empty($errors)) {
                $this->recruitmentPresetRepository->update($id, $uid, $label, $payload);
                $this->applyPersonnelAutoFillFromRecruitmentPreset($uid, $payload);
                Session::flash('success', 'Profil mis à jour.');
                return Response::redirect(url('account/recruitment-presets'));
            }
        }

        return $this->accountView('account.recruitment_presets_form', 'Modifier le profil', [
            'preset' => $row,
            'formAction' => url('account/recruitment-presets/' . $id . '/edit'),
            'errors' => $errors,
            'payloadDefaults' => $this->recruitmentPresetPayloadService->normalizeDecodedPayload(is_array($row['payload'] ?? null) ? $row['payload'] : []),
        ]);
    }

    public function recruitmentPresetsDelete(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        if (!$request->isPost()) {
            return Response::redirect(url('account/recruitment-presets'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');
            return Response::redirect(url('account/recruitment-presets'));
        }
        $uid = (int) $user['id'];
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1 || !$this->recruitmentPresetRepository->delete($id, $uid)) {
            Session::flash('error', 'Suppression impossible.');
        } else {
            Session::flash('success', 'Profil supprimé.');
        }

        return Response::redirect(url('account/recruitment-presets'));
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $previousNormalized
     * @param array<string, list<string>> $errors
     */
    /**
     * @param array<string, mixed> $payload
     */
    private function applyPersonnelAutoFillFromRecruitmentPreset(int $userId, array $payload): void
    {
        $normalized = $this->recruitmentPresetPayloadService->normalizeDecodedPayload($payload);
        $patch = $this->recruitmentPresetPayloadService->personnelAutoFillPatchFromPayload($normalized);
        if ($patch === []) {
            return;
        }
        $this->personnelProfileRepository->ensureRecord($userId);
        $cur = $this->personnelProfileRepository->getByUserId($userId) ?? [];
        $apply = [];
        if (isset($patch['character_name']) && trim((string) ($cur['character_name'] ?? '')) === '') {
            $apply['character_name'] = $patch['character_name'];
        }
        if (isset($patch['nationality']) && trim((string) ($cur['nationality'] ?? '')) === '') {
            $apply['nationality'] = $patch['nationality'];
        }
        if ($apply !== []) {
            $this->personnelProfileRepository->update($userId, $apply);
        }
    }

    private function applyRecruitmentPresetImageUpload(int $userId, array &$payload, ?array $previousNormalized, array &$errors): void
    {
        $file = $_FILES['rp_character_image'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return;
        }
        $res = $this->recruitmentPresetPayloadService->saveCharacterImage($userId, $file);
        if (!$res['ok']) {
            $errors['rp_character_image'] = [$res['error'] ?? 'Upload impossible.'];

            return;
        }
        if ($previousNormalized !== null) {
            $rp = is_array($previousNormalized['rp'] ?? null) ? $previousNormalized['rp'] : [];
            $old = trim((string) ($rp['image_url'] ?? ''));
            if ($old !== '') {
                $this->recruitmentPresetPayloadService->deleteCharacterImageFile($old);
            }
        }
        if (!isset($payload['rp']) || !is_array($payload['rp'])) {
            $payload['rp'] = [];
        }
        $payload['rp']['image_url'] = $res['path'] ?? '';
    }
}
