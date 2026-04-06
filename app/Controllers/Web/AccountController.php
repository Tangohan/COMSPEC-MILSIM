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
use App\Repositories\UserProfileRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\RecruitmentPresetRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserUiPreferencesRepository;
use App\Services\Email\EmailEvents;
use App\Services\Profile\RecruitmentPresetPayloadService;
use App\Services\Profile\UserUiPreferencesValidationService;
use App\Services\User\UserProfileSlugService;
use PDO;

class AccountController
{
    public function __construct(
        private AuthService $authService,
        private UserRepository $userRepository,
        private UserProfileRepository $userProfileRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private RecruitmentPresetRepository $recruitmentPresetRepository,
        private RecruitmentPresetPayloadService $recruitmentPresetPayloadService,
        private UserUiPreferencesRepository $userUiPreferencesRepository,
        private UserNotificationPreferencesRepository $userNotificationPreferencesRepository,
        private UserUiPreferencesValidationService $userUiPreferencesValidationService
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        return Response::view('layout.main', [
            'content' => 'account.index',
            'title' => 'Mon compte',
            'systemHealth' => $this->getSystemHealth((int) $user['tenant_id']),
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
            $health['database']['message'] = 'Connecté';
        } catch (\Throwable $e) {
            $health['database']['ok'] = false;
            $health['database']['message'] = $e->getMessage();
        }

        if ($health['database']['ok']) {
            try {
                $pdo = Database::getPdo();
                $stmt = $pdo->prepare('SELECT node_url FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1');
                $stmt->execute([$tenantId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $nodeUrl = $row['node_url'] ?? null;
                $health['api']['url'] = $nodeUrl ?: null;

                if ($nodeUrl === null || $nodeUrl === '') {
                    $health['api']['message'] = 'Non configurée (node_url vide)';
                } else {
                    $base = rtrim($nodeUrl, '/');
                    $testUrl = $base . '/api/atak/markers?mapId=default';
                    $ctx = stream_context_create([
                        'http' => ['timeout' => 3, 'ignore_errors' => true],
                    ]);
                    $body = @file_get_contents($testUrl, false, $ctx);
                    if ($body !== false) {
                        $health['api']['ok'] = true;
                        $health['api']['message'] = 'Réponse OK';
                    } else {
                        $health['api']['message'] = 'Pas de réponse (timeout ou erreur)';
                    }
                }
            } catch (\Throwable $e) {
                $health['api']['message'] = $e->getMessage();
            }
        } else {
            $health['api']['message'] = 'Non vérifiée (base indisponible)';
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
        $profile = $this->userProfileRepository->getByUserId($uid);
        $errors = [];
        $success = Session::getFlash('success');
        $error = Session::getFlash('error');

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
                'steam_id' => 'max:20',
                'timezone' => 'max:50',
                'language' => 'max:10',
                'profile_slug' => 'max:40',
            ]);
            $uiPatch = [
                'theme' => (string) $request->input('ui_theme'),
                'density' => (string) $request->input('ui_density'),
                'sidebar_collapsed' => (string) $request->input('ui_sidebar_collapsed') === '1',
            ];
            $vUi = $this->userUiPreferencesValidationService->validatePatch($uiPatch);
            if (!$v->validate()) {
                $errors = $v->errors();
            } elseif (!$vUi['ok']) {
                Session::flash('error', implode(' ', $vUi['errors']));
            } else {
                $updateUser = [
                    'display_name' => trim((string) $request->input('display_name')),
                    'callsign' => trim((string) $request->input('callsign')),
                    'steam_id' => trim((string) $request->input('steam_id')) ?: null,
                ];
                $rawSlug = trim((string) $request->input('profile_slug'));
                if ($rawSlug === '') {
                    $updateUser['profile_slug'] = null;
                } else {
                    $ps = strtolower($rawSlug);
                    if (!UserProfileSlugService::isValidFormat($ps)) {
                        Session::flash('error', 'L’identifiant profil (slug) est invalide : lettres minuscules, chiffres, tirets, max. 40 caractères.');
                        return Response::redirect(url('account/preferences'));
                    }
                    if (UserProfileSlugService::isReserved($ps)) {
                        Session::flash('error', 'Cet identifiant profil est réservé.');
                        return Response::redirect(url('account/preferences'));
                    }
                    if ($this->userRepository->isProfileSlugTaken($tenantId, $ps, $uid)) {
                        Session::flash('error', 'Cet identifiant profil est déjà utilisé dans votre communauté.');
                        return Response::redirect(url('account/preferences'));
                    }
                    $updateUser['profile_slug'] = $ps;
                }
                $this->userRepository->update($uid, $tenantId, $updateUser);
                $this->userProfileRepository->upsert($uid, [
                    'timezone' => trim((string) $request->input('timezone')),
                    'language' => trim((string) $request->input('language')),
                    'first_name' => trim((string) $request->input('first_name')),
                    'last_name' => trim((string) $request->input('last_name')),
                    'phone' => trim((string) $request->input('phone')),
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

        return Response::view('layout.main', [
            'content' => 'account.preferences',
            'title' => 'Préférences',
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
        ]);
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
        return [
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
        ];
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
        $errors = [];
        $success = Session::getFlash('success');
        $error = Session::getFlash('error');

        if ($request->isPost()) {
            if (!Csrf::validate($request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée.');
                return Response::redirect(url('account/mail'));
            }
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
            } elseif (!password_verify($password, $user['password_hash'])) {
                $errors['password'] = ['Mot de passe actuel incorrect.'];
            } elseif ($this->userRepository->emailExistsInTenant((int) $user['tenant_id'], $email, (int) $user['id'])) {
                $errors['email'] = ['Cette adresse est déjà utilisée par un autre compte.'];
            } else {
                $this->userRepository->update((int) $user['id'], (int) $user['tenant_id'], ['email' => $email]);
                Session::set('email', $email);
                Session::flash('success', 'Adresse email mise à jour.');
                return Response::redirect(url('account/mail'));
            }
        }

        return Response::view('layout.main', [
            'content' => 'account.mail',
            'title' => 'Adresse email',
            'user' => $user,
            'errors' => $errors,
            'success' => $success,
            'error' => $error,
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

        return Response::view('layout.main', [
            'content' => 'account.image',
            'title' => 'Photo de profil',
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

        return Response::view('layout.main', [
            'content' => 'account.portrait',
            'title' => 'Portrait opérateur',
            'user' => $user,
            'personnelProfile' => $personnelProfile,
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

        return Response::view('layout.main', [
            'content' => 'account.password',
            'title' => 'Mot de passe',
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

        return Response::view('layout.main', [
            'content' => 'account.recruitment_presets',
            'title' => 'Profils de candidature',
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
                Session::flash('success', 'Profil enregistré.');
                return Response::redirect(url('account/recruitment-presets'));
            }
        }

        return Response::view('layout.main', [
            'content' => 'account.recruitment_presets_form',
            'title' => 'Nouveau profil de candidature',
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
                Session::flash('success', 'Profil mis à jour.');
                return Response::redirect(url('account/recruitment-presets'));
            }
        }

        return Response::view('layout.main', [
            'content' => 'account.recruitment_presets_form',
            'title' => 'Modifier le profil',
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
