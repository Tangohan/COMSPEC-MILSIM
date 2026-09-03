<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantBrandingRepository;
use App\Repositories\TenantLoginAccueilImageRepository;
use App\Repositories\TenantRepository;
use App\Services\Admin\RolePermissionService;
use App\Services\Auth\AuthService;
use App\Services\Community\TenantCommunityProfileService;
use App\Services\Community\TenantSlugService;
use App\Services\Community\TenantTypeConfig;
use App\Services\Community\TenantTypeSwitchService;
use App\Services\ConfigurationUpdate\ConfigurationUpdateService;
use App\Services\Integrations\DiscordWebhookService;
use App\Services\Personnel\PersonnelLifecycleSettings;
use App\Support\LoginAccueilImageStorage;
use App\Support\OrganizationRoleLabels;

/**
 * Hub de paramétrage de la communauté : identité, images (logo, bannière, favicon,
 * couverture registre, menus), fuseau, langue, accès, modules publics.
 * Inscription : /back-office/community/inscription
 * Routes : /back-office/community et /back-office/organisation/parametres.
 */
final class OrganizationSettingsController
{
    private const MAX_IMAGE_BYTES = 12 * 1024 * 1024;

    /** @var list<string> */
    private const ALLOWED_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /** @var list<string> */
    private const PREFERRED_TIMEZONES = [
        'Europe/Paris',
        'Europe/Brussels',
        'Europe/London',
        'Europe/Berlin',
        'Atlantic/Canary',
        'America/New_York',
        'America/Montreal',
        'America/Toronto',
        'America/Chicago',
        'America/Denver',
        'America/Los_Angeles',
        'UTC',
    ];

    public function __construct(
        private AuthService $authService,
        private TenantRepository $tenantRepository,
        private TenantBrandingRepository $brandingRepository,
        private DiscordWebhookService $discordWebhook,
        private ?TenantTypeSwitchService $tenantTypeSwitchService = null,
        private ?RolePermissionService $rolePermissionService = null,
        private ?TenantLoginAccueilImageRepository $loginAccueilImages = null,
    ) {
        $this->tenantTypeSwitchService ??= new TenantTypeSwitchService($this->tenantRepository);
        $this->rolePermissionService ??= Container::get(RolePermissionService::class);
        $this->loginAccueilImages ??= new TenantLoginAccueilImageRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return Response::redirect(url('dashboard'));
        }
        $settings = $this->tenantRepository->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $integrations = is_array($settings['integrations'] ?? null) ? $settings['integrations'] : [];
        $branding = $this->brandingRepository->findByTenantId($tenantId) ?? [];
        $slug = strtolower(trim((string) ($tenant['slug'] ?? '')));
        $loginAccueilRows = $this->loginAccueilImages->listForTenant($tenantId);
        $loginAccueilImages = [];
        foreach ($loginAccueilRows as $row) {
            $url = LoginAccueilImageStorage::publicUrl(isset($row['storage_path']) ? (string) $row['storage_path'] : null);
            $loginAccueilImages[] = [
                'id' => (int) ($row['id'] ?? 0),
                'url' => $url ?? '',
                'alt' => trim((string) ($row['alt_text'] ?? '')),
            ];
        }
        $slideshowRaw = $community['login_accueil_slideshow'] ?? true;
        $loginAccueilSlideshow = !in_array($slideshowRaw, [false, 0, '0', 'false', 'off'], true);

        return Response::view('layout.main', [
            'title' => 'Paramètres de la communauté',
            'content' => 'admin.organization.settings',
            'tenant' => $tenant,
            'community' => $community,
            'integrations' => $integrations,
            'branding' => $branding,
            'orgSettings' => $settings,
            'orgTimezoneOptions' => $this->timezoneOptions((string) ($settings['timezone'] ?? 'Europe/Paris')),
            'registryCoverUrl' => $this->communityImageUrl($slug, 'cover'),
            'navOpsImageUrl' => $this->communityImageUrl($slug, 'nav-operations'),
            'navResImageUrl' => $this->communityImageUrl($slug, 'nav-resources'),
            'orgSettingsFormAction' => $this->formActionFromRequest($request),
            'tenantTypeOptions' => TenantTypeConfig::availableTypes(),
            'currentTenantType' => TenantTypeConfig::normalizeType((string) ($tenant['tenant_type'] ?? 'full')),
            'tenantTypeFormAction' => url('back-office/organisation/profil'),
            'roleOptions' => $this->loadRoleOptions($tenantId, $tenant, $community),
            'defaultGuestRoleSlug' => trim((string) ($community['default_guest_role_slug'] ?? 'invite')),
            'loginAccueilImages' => $loginAccueilImages,
            'loginAccueilSlideshow' => $loginAccueilSlideshow,
            'loginAccueilMaxImages' => TenantLoginAccueilImageRepository::MAX_IMAGES,
            'loginAccueilDefaultUrl' => LoginAccueilImageStorage::defaultPublicUrl(),
            'loginAccueilHint' => LoginAccueilImageStorage::hintText(),
            'personnelLifecycle' => PersonnelLifecycleSettings::resolve($settings),
        ]);
    }

    public function inscription(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return Response::redirect(url('dashboard'));
        }
        $settings = $this->tenantRepository->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];

        return Response::view('layout.main', [
            'title' => 'Paramètres d’inscription',
            'content' => 'admin.organization.inscription_settings',
            'tenant' => $tenant,
            'community' => $community,
            'roleOptions' => $this->loadRoleOptions($tenantId, $tenant, $community),
            'defaultGuestRoleSlug' => trim((string) ($community['default_guest_role_slug'] ?? 'invite')),
            'inscriptionFormAction' => url('back-office/community/inscription'),
        ]);
    }

    public function inscriptionUpdate(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $redirectTo = url('back-office/community/inscription');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($redirectTo);
        }
        $tenantId = (int) Session::get('tenant_id');
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return Response::redirect(url('dashboard'));
        }

        $warning = $this->updateInscriptionSettings($tenantId, $request);
        if ($warning !== null) {
            Session::flash('success', 'Paramètres d’inscription enregistrés. ' . $warning);
        } else {
            Session::flash('success', 'Paramètres d’inscription enregistrés.');
        }

        return Response::redirect($redirectTo);
    }

    public function updateType(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $redirectTo = url('back-office/organisation/parametres');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($redirectTo);
        }
        $tenantId = (int) Session::get('tenant_id');
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return Response::redirect(url('dashboard'));
        }

        $newType = TenantTypeConfig::normalizeType((string) $request->input('tenant_type', ''));
        $confirm = (string) $request->input('confirm_type_change', '') === '1';
        if (!$confirm) {
            Session::flash('error', 'Veuillez cocher la case de confirmation avant de changer le profil de la communauté.');

            return Response::redirect($redirectTo . '#org-profil');
        }

        try {
            // Toujours réappliquer permissions / rôles du profil (y compris si déjà « ATAK »),
            // pour réparer une communauté créée avant la migration ou mal classée en Complet.
            $result = $this->tenantTypeSwitchService->switchType($tenantId, $newType, true);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if ($e instanceof \RuntimeException && $msg !== '' && !preg_match('/SQLSTATE|Unknown column|PDOException/i', $msg)) {
                Session::flash('error', $msg);
            } else {
                Session::flash('error', 'Impossible de modifier le profil de la communauté. Réessayez ou contactez le support.');
            }

            return Response::redirect($redirectTo . '#org-profil');
        }

        if (!empty($result['changed'])) {
            Session::flash(
                'success',
                'Profil mis à jour : « ' . TenantTypeConfig::label($result['from']) . ' » → « '
                . TenantTypeConfig::label($result['to']) . ' ». Les menus et accès ont été ajustés.'
            );
        } else {
            Session::flash(
                'success',
                'Profil « ' . TenantTypeConfig::label($newType)
                . ' » réappliqué : menus et permissions ont été réalignés.'
            );
        }

        return Response::redirect($redirectTo . '#org-profil');
    }

    public function update(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $redirectTo = $this->formActionFromRequest($request);
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($redirectTo);
        }
        $tenantId = (int) Session::get('tenant_id');
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return Response::redirect(url('dashboard'));
        }

        $notices = [];

        $identityError = $this->updateIdentity($tenantId, $tenant, $request);
        if ($identityError !== null) {
            Session::flash('error', $identityError);

            return Response::redirect($redirectTo);
        }
        $notices[] = 'Identité mise à jour.';

        $discordWarning = $this->updateContactAccessAndOptions($tenantId, $request);
        $notices[] = 'Accès et options enregistrés.';
        if ($discordWarning !== null) {
            $notices[] = $discordWarning;
        }

        $freshTenant = $this->tenantRepository->findById($tenantId) ?? $tenant;
        $imagesOutcome = $this->updateImages($tenantId, $freshTenant, $request);
        if ($imagesOutcome['error'] !== null) {
            Session::flash('error', implode(' ', $notices) . ' ' . $imagesOutcome['error']);

            return Response::redirect($redirectTo);
        }
        foreach ($imagesOutcome['messages'] as $m) {
            $notices[] = $m;
        }

        Session::flash('success', implode(' ', $notices));

        return Response::redirect($redirectTo);
    }

    public function storeLoginAccueilImage(Request $request, array $params = []): Response
    {
        $back = $this->accueilSettingsUrl();
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($back);
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $files = $this->flattenUploadedFiles('login_accueil_images');
        if ($files === []) {
            Session::flash('error', 'Choisissez au moins une image à ajouter.');

            return Response::redirect($back);
        }
        $remaining = TenantLoginAccueilImageRepository::MAX_IMAGES - $this->loginAccueilImages->countForTenant($tenantId);
        if ($remaining < 1) {
            Session::flash('error', 'Nombre maximum d’images d’accueil atteint (' . TenantLoginAccueilImageRepository::MAX_IMAGES . ').');

            return Response::redirect($back);
        }
        $alt = $this->clip((string) $request->input('login_accueil_alt', ''), 200);
        $alt = $alt !== '' ? $alt : null;
        $added = 0;
        $lastError = null;
        foreach ($files as $file) {
            if ($remaining < 1) {
                break;
            }
            $stored = LoginAccueilImageStorage::storeFromUpload($tenantId, $file);
            if (($stored['error'] ?? null) !== null) {
                $lastError = $stored['error'];
                continue;
            }
            $path = trim((string) ($stored['path'] ?? ''));
            if ($path === '') {
                continue;
            }
            $id = $this->loginAccueilImages->create($tenantId, $path, $alt, (int) Session::get('user_id') ?: null);
            if ($id > 0) {
                $added++;
                $remaining--;
            }
        }
        if ($added > 0) {
            $this->markLoginAccueilConfigured($tenantId);
            Session::flash('success', $added === 1 ? 'Image d’accueil ajoutée.' : $added . ' images d’accueil ajoutées.');
            if ($lastError !== null) {
                Session::flash('error', $lastError);
            }
        } else {
            Session::flash('error', $lastError ?? 'Impossible d’enregistrer ces images.');
        }

        return Response::redirect($back);
    }

    public function replaceLoginAccueilImage(Request $request, array $params = []): Response
    {
        $back = $this->accueilSettingsUrl();
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($back);
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->loginAccueilImages->findById($id, $tenantId);
        if ($existing === null) {
            Session::flash('error', 'Cette image d’accueil n’existe plus.');

            return Response::redirect($back);
        }
        $file = $_FILES['login_accueil_replace'] ?? null;
        $hasFile = is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $alt = $this->clip((string) $request->input('login_accueil_alt', ''), 200);
        $alt = $alt !== '' ? $alt : null;
        if ($hasFile) {
            $stored = LoginAccueilImageStorage::storeFromUpload($tenantId, is_array($file) ? $file : []);
            if (($stored['error'] ?? null) !== null) {
                Session::flash('error', (string) $stored['error']);

                return Response::redirect($back);
            }
            $path = trim((string) ($stored['path'] ?? ''));
            if ($path === '') {
                Session::flash('error', 'Impossible d’enregistrer cette image.');

                return Response::redirect($back);
            }
            LoginAccueilImageStorage::delete(isset($existing['storage_path']) ? (string) $existing['storage_path'] : null);
            $this->loginAccueilImages->updatePath($id, $tenantId, $path);
        }
        $this->loginAccueilImages->updateAlt($id, $tenantId, $alt);
        $this->markLoginAccueilConfigured($tenantId);
        Session::flash('success', $hasFile ? 'Image d’accueil remplacée.' : 'Description de l’image enregistrée.');

        return Response::redirect($back);
    }

    public function deleteLoginAccueilImage(Request $request, array $params = []): Response
    {
        $back = $this->accueilSettingsUrl();
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($back);
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $removed = $this->loginAccueilImages->delete($id, $tenantId);
        if ($removed !== null) {
            LoginAccueilImageStorage::delete(isset($removed['storage_path']) ? (string) $removed['storage_path'] : null);
            Session::flash('success', 'Image d’accueil retirée.');
        } else {
            Session::flash('error', 'Impossible de retirer cette image.');
        }

        return Response::redirect($back);
    }

    public function moveLoginAccueilImage(Request $request, array $params = []): Response
    {
        $back = $this->accueilSettingsUrl();
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($back);
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $dir = trim((string) $request->input('direction'));
        $rows = $this->loginAccueilImages->listForTenant($tenantId);
        $orderedIds = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows);
        $idx = array_search($id, $orderedIds, true);
        if ($idx === false) {
            return Response::redirect($back);
        }
        if ($dir === 'up' && $idx > 0) {
            $tmp = $orderedIds[$idx - 1];
            $orderedIds[$idx - 1] = $orderedIds[$idx];
            $orderedIds[$idx] = $tmp;
        } elseif ($dir === 'down' && $idx < count($orderedIds) - 1) {
            $tmp = $orderedIds[$idx + 1];
            $orderedIds[$idx + 1] = $orderedIds[$idx];
            $orderedIds[$idx] = $tmp;
        }
        $this->loginAccueilImages->reorder($tenantId, $orderedIds);
        $this->markLoginAccueilConfigured($tenantId);
        Session::flash('success', 'Ordre des images d’accueil mis à jour.');

        return Response::redirect($back);
    }

    public function saveLoginAccueilSlideshow(Request $request, array $params = []): Response
    {
        $back = $this->accueilSettingsUrl();
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($back);
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $enabled = (string) $request->input('login_accueil_slideshow', '0') === '1';
        $settings = $this->tenantRepository->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $community['login_accueil_slideshow'] = $enabled;
        $this->tenantRepository->mergeSettings($tenantId, [
            'community' => $community,
        ]);
        $this->markLoginAccueilConfigured($tenantId);
        Session::flash('success', $enabled
            ? 'Les images d’accueil défileront automatiquement.'
            : 'La première image d’accueil restera affichée, sans défilement.');

        return Response::redirect($back);
    }

    private function accueilSettingsUrl(): string
    {
        return url('back-office/organisation/parametres') . '#accueil-connexion';
    }

    private function markLoginAccueilConfigured(int $tenantId): void
    {
        try {
            Container::get(ConfigurationUpdateService::class)->markCompleted(
                $tenantId,
                'LOGIN_ACCUEIL_IMAGES_V1',
                (int) Session::get('user_id') ?: null
            );
        } catch (\Throwable) {
            // Moteur de configuration absent : non bloquant
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function flattenUploadedFiles(string $field): array
    {
        $raw = $_FILES[$field] ?? null;
        if (!is_array($raw)) {
            return [];
        }
        if (isset($raw['tmp_name']) && is_string($raw['tmp_name'])) {
            if ((int) ($raw['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                return [];
            }

            return [$raw];
        }
        $names = $raw['name'] ?? [];
        if (!is_array($names)) {
            return [];
        }
        $out = [];
        foreach ($names as $i => $name) {
            $err = (int) ($raw['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name' => (string) $name,
                'type' => (string) ($raw['type'][$i] ?? ''),
                'tmp_name' => (string) ($raw['tmp_name'][$i] ?? ''),
                'error' => $err,
                'size' => (int) ($raw['size'][$i] ?? 0),
            ];
        }

        return array_values(array_filter($out, static fn (array $f): bool => $f !== []));
    }

    /** @param array<string, mixed> $tenant */
    private function updateIdentity(int $tenantId, array $tenant, Request $request): ?string
    {
        $newName = trim((string) $request->input('tenant_name'));
        if ($newName === '') {
            return 'Le nom affiché est obligatoire.';
        }
        $this->tenantRepository->updateName($tenantId, mb_substr($newName, 0, 255));

        $newSlug = strtolower(trim((string) $request->input('tenant_slug')));
        $oldSlug = (string) ($tenant['slug'] ?? '');
        if ($newSlug === '') {
            return 'L’adresse courte de la page publique est obligatoire.';
        }
        if ($newSlug !== $oldSlug) {
            if (!TenantSlugService::isValidFormat($newSlug)) {
                return 'L’adresse courte est invalide (lettres minuscules, chiffres, tirets, max. 50 caractères).';
            }
            if (TenantSlugService::isReserved($newSlug)) {
                return 'Cette adresse courte est réservée.';
            }
            if ($this->tenantRepository->isSlugTakenByOther($tenantId, $newSlug)) {
                return 'Cette adresse courte est déjà utilisée par une autre communauté.';
            }
            $this->tenantRepository->updateSlug($tenantId, $newSlug);
        }

        $raw = trim((string) $request->input('community_code'));
        if ($raw === '') {
            $this->tenantRepository->updateCommunityCode($tenantId, null);

            return null;
        }
        $norm = TenantRepository::normalizeCommunityCode($raw);
        if (strlen($norm) < 3 || strlen($norm) > 64) {
            return 'Le code rejoindre doit faire entre 3 et 64 caractères (lettres, chiffres, tirets).';
        }
        if ($this->isReservedCommunityCode($norm)) {
            return 'Ce code rejoindre est réservé.';
        }
        if ($this->tenantRepository->isCommunityCodeTaken($norm, $tenantId)) {
            return 'Ce code rejoindre est déjà utilisé par une autre communauté.';
        }
        $this->tenantRepository->updateCommunityCode($tenantId, $norm);

        return null;
    }

    private function updateContactAccessAndOptions(int $tenantId, Request $request): ?string
    {
        $settings = $this->tenantRepository->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $integrations = is_array($settings['integrations'] ?? null) ? $settings['integrations'] : [];

        // Les paramètres d’inscription (mode, contact candidats, créneaux, motivation…)
        // sont gérés sur /back-office/community/inscription — on les préserve ici.

        $community['registry_listed'] = (string) $request->input('registry_listed', '1') !== '0';
        $community['forum_members_only'] = (string) $request->input('forum_members_only', '0') === '1';
        $community['welcome_text'] = $this->clip((string) $request->input('welcome_text', ''), 500);
        $community['game_label'] = $this->clip((string) $request->input('game_label', ''), 120);
        $community['public_hero_subtitle'] = $this->clip((string) $request->input('public_hero_subtitle', ''), 600);
        $community['public_about_title'] = $this->clip((string) $request->input('public_about_title', ''), 160);
        $community['public_about_body'] = $this->clip((string) $request->input('public_about_body', ''), 8000);
        $unitAffiliation = TenantCommunityProfileService::normalizeUnitAffiliationFromRequest(
            $request,
            is_array($community['unit_affiliation'] ?? null) ? $community['unit_affiliation'] : null
        );
        if ($unitAffiliation !== null) {
            $community['unit_affiliation'] = $unitAffiliation;
            if (!empty($unitAffiliation['is_real'])) {
                $registryTags = is_array($community['registry_tags'] ?? null) ? $community['registry_tags'] : [];
                if (!in_array('soar', $registryTags, true)) {
                    $registryTags[] = 'soar';
                }
                $community['registry_tags'] = array_values(array_unique($registryTags));
            }
        }

        $community['portal_nav'] = $this->parsePortalNav($request, is_array($community['portal_nav'] ?? null) ? $community['portal_nav'] : []);

        $personnelLifecycle = PersonnelLifecycleSettings::fromInput(
            $request->input('personnel_training_days', 14),
            $request->input('personnel_active_service_days', 0)
        );

        $locale = strtolower(trim((string) $request->input('default_locale', 'fr')));
        $community['default_locale'] = in_array($locale, ['fr', 'en', 'fr-fr', 'en-us'], true)
            ? ($locale === 'fr-fr' ? 'fr' : ($locale === 'en-us' ? 'en' : $locale))
            : 'fr';

        $orbat = strtolower(trim((string) $request->input('orbat_visibility', 'members')));
        $community['orbat_visibility'] = in_array($orbat, ['public', 'members', 'command'], true) ? $orbat : 'members';

        $existingModules = is_array($community['public_modules'] ?? null) ? $community['public_modules'] : [];
        $community['public_modules'] = [
            'forum' => (string) $request->input('public_mod_forum', '0') === '1',
            'documents' => (string) $request->input('public_mod_documents', '0') === '1',
            'events' => (string) $request->input('public_mod_events', '0') === '1',
            'roster' => (string) $request->input('public_mod_roster', '0') === '1',
            'training' => (string) $request->input('public_mod_training', '0') === '1',
            'analytics' => (string) $request->input('public_mod_analytics', '0') === '1',
        ] + $existingModules;

        $timezone = trim((string) $request->input('timezone', 'Europe/Paris'));
        $allowedZones = \DateTimeZone::listIdentifiers();
        if (!in_array($timezone, $allowedZones, true)) {
            $timezone = 'Europe/Paris';
        }

        $warnings = [];
        $discordRaw = trim((string) $request->input('discord_webhook_url', ''));
        if ($discordRaw === '') {
            $integrations['discord_webhook_url'] = null;
        } elseif ($this->discordWebhook->isValidWebhookUrl($discordRaw)) {
            $integrations['discord_webhook_url'] = $discordRaw;
        } else {
            $warnings[] = 'L’URL de webhook Discord n’a pas été enregistrée : elle doit commencer par https://discord.com/api/webhooks/…';
        }

        $this->tenantRepository->mergeSettings($tenantId, [
            'timezone' => $timezone,
            'community' => $community,
            'integrations' => $integrations,
            PersonnelLifecycleSettings::SETTINGS_KEY => $personnelLifecycle,
        ]);

        if (isset($unitAffiliation) && is_array($unitAffiliation)) {
            try {
                $ref = \App\Core\Container::get(\App\Services\Community\MilitaryReferentialService::class);
                if (!empty($unitAffiliation['is_real'])) {
                    $ref->syncTenantAffiliationsFromCodes(
                        $tenantId,
                        is_array($unitAffiliation['unit_ids'] ?? null) ? $unitAffiliation['unit_ids'] : []
                    );
                } else {
                    $ref->syncTenantAffiliationsFromCodes($tenantId, []);
                }
            } catch (\Throwable) {
                // Table absente si migrations non exécutées
            }
        }

        try {
            $cfg = \App\Core\Container::get(\App\Services\ConfigurationUpdate\ConfigurationUpdateService::class);
            $cfg->refreshFromData($tenantId, (int) \App\Core\Session::get('user_id') ?: null);
        } catch (\Throwable) {
            // Moteur mise à niveau absent
        }

        return $warnings === [] ? null : implode(' ', $warnings);
    }

    private function updateInscriptionSettings(int $tenantId, Request $request): ?string
    {
        $settings = $this->tenantRepository->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];

        $community['contact_email'] = $this->sanitizeEmail((string) $request->input('contact_email', ''));
        $community['contact_discord_url'] = $this->sanitizeUrl((string) $request->input('contact_discord_url', ''), 500);
        $community['contact_intro'] = $this->clip((string) $request->input('contact_intro', ''), 500);
        $community['contact_form_enabled'] = (string) $request->input('contact_form_enabled', '0') === '1';

        $community['registration_mode'] = TenantCommunityProfileService::normalizeRegistrationMode(
            $request->input('registration_mode', TenantCommunityProfileService::REGISTRATION_MODE_MILSIM)
        );
        $community['community_locked'] = (string) $request->input('community_locked', '0') === '1';
        $community['require_ai_ack'] = (string) $request->input('require_ai_ack', '0') === '1';
        $community['refuse_other_community_members'] = (string) $request->input('refuse_other_community_members', '0') === '1';
        $community['public_recruitment_badge_open'] = (string) $request->input('public_recruitment_badge_open', '0') === '1';

        $existingEm = is_array($community['enlistment_milsim'] ?? null) ? $community['enlistment_milsim'] : [];
        $community['enlistment_milsim'] = \App\Services\Community\EnlistmentMilsimPackService::mergePartialFromCommunitySettingsRequest(
            $request,
            $existingEm
        );

        $guestSlug = trim((string) $request->input('default_guest_role_slug', ''));
        if ($guestSlug !== '') {
            $validSlugs = [];
            foreach ($this->rolePermissionService->listOrganizationRoles($tenantId) as $role) {
                $s = trim((string) ($role['slug'] ?? ''));
                if ($s !== '') {
                    $validSlugs[$s] = true;
                }
            }
            if (isset($validSlugs[$guestSlug])) {
                $community['default_guest_role_slug'] = $guestSlug;
            }
        }

        $this->tenantRepository->mergeSettings($tenantId, [
            'community' => $community,
        ]);

        if (TenantCommunityProfileService::needsDiscordInviteAlert($community)) {
            return 'Le recrutement via Discord est actif, mais le lien Discord n’est pas renseigné. Ajoutez-le ci-dessous pour que les candidats puissent ouvrir votre serveur.';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $tenant
     * @return array{messages: list<string>, error: ?string}
     */
    private function updateImages(int $tenantId, array $tenant, Request $request): array
    {
        $messages = [];
        $slug = strtolower(trim((string) ($tenant['slug'] ?? '')));
        $brandingPatch = [];

        foreach ([
            ['field' => 'org_logo', 'remove' => 'remove_org_logo', 'slot' => 'logo', 'label' => 'Logo', 'maxWidth' => 512, 'keepAlpha' => true],
            ['field' => 'org_banner', 'remove' => 'remove_org_banner', 'slot' => 'banner', 'label' => 'Bannière', 'maxWidth' => 1800, 'keepAlpha' => false],
            ['field' => 'org_favicon', 'remove' => 'remove_org_favicon', 'slot' => 'favicon', 'label' => 'Icône (favicon)', 'maxWidth' => 512, 'keepAlpha' => true],
        ] as $spec) {
            $outcome = $this->processBrandingImage($slug, $spec, $request);
            if (is_string($outcome['error'] ?? null)) {
                return ['messages' => $messages, 'error' => $outcome['error']];
            }
            if ($outcome['action'] === 'uploaded') {
                $brandingPatch[$spec['slot'] . '_url'] = $outcome['url'];
                $messages[] = $spec['label'] . ' mis à jour.';
                if ($spec['slot'] === 'logo') {
                    $this->tenantRepository->updateLogoUrl($tenantId, (string) $outcome['url']);
                }
            } elseif ($outcome['action'] === 'removed') {
                $brandingPatch[$spec['slot'] . '_url'] = null;
                $messages[] = $spec['label'] . ' retiré.';
                if ($spec['slot'] === 'logo') {
                    $this->tenantRepository->clearLogoUrl($tenantId);
                }
            }
        }

        $primary = $this->sanitizeHexColor((string) $request->input('primary_color', ''));
        $accent = $this->sanitizeHexColor((string) $request->input('accent_color', ''));
        $brandingPatch['primary_color'] = $primary;
        $brandingPatch['accent_color'] = $accent;

        if ($brandingPatch !== []) {
            $this->brandingRepository->upsert($tenantId, $brandingPatch);
        }

        foreach ([
            ['field' => 'registry_cover', 'remove' => 'remove_registry_cover', 'file' => 'cover', 'label' => 'Image de carte du registre'],
            ['field' => 'nav_operations', 'remove' => 'remove_nav_operations', 'file' => 'nav-operations', 'label' => 'Image du menu Opérations'],
            ['field' => 'nav_resources', 'remove' => 'remove_nav_resources', 'file' => 'nav-resources', 'label' => 'Image du menu Ressources'],
        ] as $coverSpec) {
            $coverOutcome = $this->processCommunityJpeg($slug, $coverSpec, $request);
            if (is_string($coverOutcome['error'] ?? null)) {
                return ['messages' => $messages, 'error' => $coverOutcome['error']];
            }
            if ($coverOutcome['action'] === 'uploaded') {
                $messages[] = $coverSpec['label'] . ' mise à jour.';
            } elseif ($coverOutcome['action'] === 'removed') {
                $messages[] = $coverSpec['label'] . ' retirée.';
            }
        }

        return ['messages' => $messages, 'error' => null];
    }

    /**
     * @param array{field: string, remove: string, slot: string, label: string, maxWidth: int, keepAlpha: bool} $spec
     * @return array{action: 'none'|'uploaded'|'removed', url: ?string, error: ?string}
     */
    private function processBrandingImage(string $slug, array $spec, Request $request): array
    {
        $dir = base_path('public/assets/img/communities');
        $ext = $spec['keepAlpha'] ? 'png' : 'jpg';
        $relPath = 'assets/img/communities/' . ($slug !== '' ? $slug : 'tenant') . '-' . $spec['slot'] . '.' . $ext;
        $destFs = base_path('public/' . $relPath);

        $file = $_FILES[$spec['field']] ?? null;
        if (is_array($file)) {
            $fe = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($fe !== UPLOAD_ERR_NO_FILE && $fe !== UPLOAD_ERR_OK) {
                return ['action' => 'none', 'url' => null, 'error' => 'Envoi impossible (' . $spec['label'] . '). Vérifiez la taille (max. 12 Mo) et le format, puis réessayez.'];
            }
        }
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            if ($slug === '' || !preg_match('/^[a-z0-9-]{1,50}$/', $slug)) {
                return ['action' => 'none', 'url' => null, 'error' => 'Définissez d’abord l’adresse courte de votre communauté (section Identité) avant d’ajouter des images.'];
            }
            $tmp = (string) ($file['tmp_name'] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                return ['action' => 'none', 'url' => null, 'error' => 'Fichier reçu invalide (' . $spec['label'] . '). Réessayez.'];
            }
            if ((int) ($file['size'] ?? 0) > self::MAX_IMAGE_BYTES) {
                return ['action' => 'none', 'url' => null, 'error' => $spec['label'] . ' trop volumineux : limite de 12 Mo.'];
            }
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmp) ?: '';
            if (!in_array($mime, self::ALLOWED_IMAGE_MIMES, true)) {
                return ['action' => 'none', 'url' => null, 'error' => 'Format non pris en charge pour ' . $spec['label'] . '. Utilisez une image JPG, PNG ou WebP.'];
            }
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                return ['action' => 'none', 'url' => null, 'error' => 'Le stockage des images n’est pas disponible sur le serveur pour le moment.'];
            }
            if (!$this->writeImage($tmp, $destFs, $spec['maxWidth'], $spec['keepAlpha'])) {
                return ['action' => 'none', 'url' => null, 'error' => 'Impossible de traiter cette image (' . $spec['label'] . '). Essayez avec un autre fichier.'];
            }

            return ['action' => 'uploaded', 'url' => url($relPath) . '?v=' . (int) filemtime($destFs), 'error' => null];
        }

        if ($request->input($spec['remove']) === '1' && $slug !== '' && is_file($destFs)) {
            @unlink($destFs);

            return ['action' => 'removed', 'url' => null, 'error' => null];
        }

        return ['action' => 'none', 'url' => null, 'error' => null];
    }

    /**
     * @param array{field: string, remove: string, file: string, label: string} $spec
     * @return array{action: 'none'|'uploaded'|'removed', error: ?string}
     */
    private function processCommunityJpeg(string $slug, array $spec, Request $request): array
    {
        $dir = base_path('public/assets/img/communities');
        $dest = $dir . '/' . $slug . '-' . $spec['file'] . '.jpg';

        $file = $_FILES[$spec['field']] ?? null;
        if (is_array($file)) {
            $fe = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($fe !== UPLOAD_ERR_NO_FILE && $fe !== UPLOAD_ERR_OK) {
                return ['action' => 'none', 'error' => 'Envoi impossible (' . $spec['label'] . '). Vérifiez la taille (max. 12 Mo) et le format.'];
            }
        }
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            if ($slug === '' || !preg_match('/^[a-z0-9-]{1,50}$/', $slug)) {
                return ['action' => 'none', 'error' => 'Définissez d’abord l’adresse courte avant d’ajouter des images.'];
            }
            $tmp = (string) ($file['tmp_name'] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                return ['action' => 'none', 'error' => 'Fichier reçu invalide (' . $spec['label'] . ').'];
            }
            if ((int) ($file['size'] ?? 0) > self::MAX_IMAGE_BYTES) {
                return ['action' => 'none', 'error' => $spec['label'] . ' trop volumineuse : limite de 12 Mo.'];
            }
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmp) ?: '';
            if (!in_array($mime, self::ALLOWED_IMAGE_MIMES, true)) {
                return ['action' => 'none', 'error' => 'Format non pris en charge pour ' . $spec['label'] . '. Utilisez JPG, PNG ou WebP.'];
            }
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                return ['action' => 'none', 'error' => 'Le stockage des images n’est pas disponible pour le moment.'];
            }
            if (!$this->writeImage($tmp, $dest, 1800, false)) {
                return ['action' => 'none', 'error' => 'Impossible de traiter cette image (' . $spec['label'] . ').'];
            }

            return ['action' => 'uploaded', 'error' => null];
        }

        if ($request->input($spec['remove']) === '1' && $slug !== '' && is_file($dest)) {
            @unlink($dest);

            return ['action' => 'removed', 'error' => null];
        }

        return ['action' => 'none', 'error' => null];
    }

    private function writeImage(string $tmpPath, string $destPath, int $maxWidth, bool $keepAlpha): bool
    {
        if (!function_exists('imagecreatefromstring')) {
            return $keepAlpha ? false : @copy($tmpPath, $destPath);
        }
        $bin = @file_get_contents($tmpPath);
        if ($bin === false) {
            return false;
        }
        $im = @imagecreatefromstring($bin);
        if (!$im) {
            return false;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        if ($w < 1 || $h < 1) {
            imagedestroy($im);

            return false;
        }
        if ($w > $maxWidth) {
            $newH = max(1, (int) round($h * ($maxWidth / $w)));
            $scaled = imagecreatetruecolor($maxWidth, $newH);
            if ($scaled === false) {
                imagedestroy($im);

                return false;
            }
            if ($keepAlpha) {
                imagealphablending($scaled, false);
                imagesavealpha($scaled, true);
            }
            imagecopyresampled($scaled, $im, 0, 0, 0, 0, $maxWidth, $newH, $w, $h);
            imagedestroy($im);
            $im = $scaled;
        }
        $ok = $keepAlpha ? @imagepng($im, $destPath, 6) : @imagejpeg($im, $destPath, 86);
        imagedestroy($im);

        return (bool) $ok;
    }

    /** @return list<string> */
    private function timezoneOptions(string $current): array
    {
        $all = \DateTimeZone::listIdentifiers();
        $preferred = [];
        foreach (self::PREFERRED_TIMEZONES as $z) {
            if (in_array($z, $all, true)) {
                $preferred[] = $z;
            }
        }
        if ($current !== '' && !in_array($current, $preferred, true) && in_array($current, $all, true)) {
            array_unshift($preferred, $current);
        }
        $rest = array_values(array_diff($all, $preferred));
        sort($rest);

        return array_merge($preferred, $rest);
    }

    private function communityImageUrl(string $slug, string $suffix): ?string
    {
        if ($slug === '') {
            return null;
        }
        $fs = base_path('public/assets/img/communities/' . $slug . '-' . $suffix . '.jpg');
        if (!is_file($fs)) {
            return null;
        }

        return url('assets/img/communities/' . $slug . '-' . $suffix . '.jpg') . '?v=' . (int) filemtime($fs);
    }

    private function formActionFromRequest(Request $request): string
    {
        $path = trim((string) ($request->path() ?? ''), '/');
        if (str_starts_with($path, 'back-office/organisation/parametres')) {
            return url('back-office/organisation/parametres');
        }

        return url('back-office/community');
    }

    private function sanitizeHexColor(string $raw): ?string
    {
        $s = trim($raw);
        if ($s === '') {
            return null;
        }

        return preg_match('/^#[0-9a-fA-F]{6}$/', $s) ? strtolower($s) : null;
    }

    private function clip(string $s, int $max): string
    {
        $s = trim($s);

        return mb_strlen($s) <= $max ? $s : mb_substr($s, 0, $max);
    }

    private function sanitizeUrl(string $url, int $maxLen): string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > $maxLen) {
            return '';
        }
        if (stripos($url, 'javascript:') !== false || preg_match('#^\s*data:#i', $url)) {
            return '';
        }

        return preg_match('#^https?://#i', $url) ? $url : '';
    }

    private function sanitizeEmail(string $email): string
    {
        $email = trim($email);
        if ($email === '' || strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '';
        }

        return $email;
    }

    private function isReservedCommunityCode(string $normalized): bool
    {
        $reserved = [
            'JOIN', 'LOGIN', 'REGISTER', 'API', 'ADMIN', 'C', 'DASHBOARD', 'HUB', 'FORUM', 'SYSTEM',
            'DEFAULT', 'WWW', 'ENLISTMENT', 'COMMUNITIES', 'INVITATIONS', 'LOGOUT', 'ACCOUNT', 'ATAK',
        ];

        return in_array($normalized, $reserved, true);
    }

    /**
     * @param array<string, mixed> $tenant
     * @param array<string, mixed> $community
     * @return list<array{slug: string, name: string}>
     */
    private function loadRoleOptions(int $tenantId, array $tenant, array $community): array
    {
        $labelMode = OrganizationRoleLabels::mode($community, $tenant);
        $out = [];
        foreach ($this->rolePermissionService->listOrganizationRoles($tenantId) as $role) {
            $slug = trim((string) ($role['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }
            $out[] = [
                'slug' => $slug,
                'name' => OrganizationRoleLabels::displayName($role, $labelMode),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $existing
     * @return array<string, mixed>
     */
    private function parsePortalNav(Request $request, array $existing): array
    {
        $out = $existing;
        foreach (['operations', 'resources'] as $slot) {
            $acc = strtolower(trim((string) $request->input('nav_' . $slot . '_accent', '')));
            if (!in_array($acc, TenantCommunityProfileService::allowedNavAccents(), true)) {
                $acc = ($slot === 'operations') ? 'sky' : 'amber';
            }

            $style = strtolower(trim((string) $request->input('nav_' . $slot . '_submenu_style', '')));
            if (!in_array($style, TenantCommunityProfileService::allowedNavSubmenuStyles(), true)) {
                $style = ($slot === 'operations') ? 'cards' : 'minimal';
            }

            $out[$slot] = [
                'accent' => $acc,
                'image_enabled' => (string) $request->input('nav_' . $slot . '_image_enabled', '1') === '1',
                'submenu_style' => $style,
            ];
        }

        return $out;
    }
}
