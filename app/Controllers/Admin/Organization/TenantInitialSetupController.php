<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantBrandingRepository;
use App\Repositories\TenantRepository;
use App\Services\Admin\RolePermissionService;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Auth\AuthService;
use App\Services\Community\TenantCommunityProfileService;
use App\Services\Community\TenantInitialSetupService;
use App\Support\OrganizationRoleLabels;

/**
 * Cockpit de configuration initiale (non bloquant) après création de communauté.
 */
final class TenantInitialSetupController
{
    private const MAX_IMAGE_BYTES = 12 * 1024 * 1024;

    /** @var list<string> */
    private const ALLOWED_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private AuthService $authService,
        private TenantRepository $tenantRepository,
        private TenantBrandingRepository $brandingRepository,
        private TenantInitialSetupService $initialSetupService,
        private RolePermissionService $rolePermissionService,
        private ?AuditService $auditService = null,
    ) {
        $this->auditService ??= Container::get(AuditService::class);
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
        $branding = $this->brandingRepository->findByTenantId($tenantId) ?? [];
        $analysis = $this->initialSetupService->analyze($tenantId);
        $roles = $this->rolePermissionService->listOrganizationRoles($tenantId);
        $labelMode = OrganizationRoleLabels::mode($community, $tenant);

        $roleOptions = [];
        foreach ($roles as $role) {
            $slug = trim((string) ($role['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }
            $roleOptions[] = [
                'slug' => $slug,
                'name' => OrganizationRoleLabels::displayName($role, $labelMode),
            ];
        }

        $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
        if ($logoUrl === '') {
            $logoUrl = trim((string) ($tenant['logo_url'] ?? ''));
        }

        return Response::view('layout.main', [
            'title' => 'Configuration initiale',
            'content' => 'admin.organization.initial_setup',
            'tenant' => $tenant,
            'community' => $community,
            'branding' => $branding,
            'logoUrl' => $logoUrl,
            'setupAnalysis' => $analysis,
            'roleOptions' => $roleOptions,
            'defaultGuestRoleSlug' => trim((string) ($community['default_guest_role_slug'] ?? 'invite')),
        ]);
    }

    public function save(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $redirectTo = url('back-office/configuration-initiale');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($redirectTo);
        }

        $tenantId = (int) Session::get('tenant_id');
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return Response::redirect(url('dashboard'));
        }

        $communityError = $this->saveCommunityFromRequest($tenantId, $request);
        if ($communityError !== null) {
            Session::flash('error', $communityError);

            return Response::redirect($redirectTo);
        }

        $logoOutcome = $this->processLogoUpload($tenantId, $tenant, $request);
        if ($logoOutcome['error'] !== null) {
            Session::flash('error', $logoOutcome['error']);

            return Response::redirect($redirectTo);
        }

        $msg = 'Paramètres enregistrés. Vous pouvez poursuivre ou terminer quand vous êtes prêt.';
        $settings = $this->tenantRepository->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        if (TenantCommunityProfileService::needsDiscordInviteAlert($community)) {
            $msg .= ' Attention : le recrutement via Discord est actif sans lien Discord — renseignez-le pour que les candidats puissent rejoindre votre serveur.';
        }
        Session::flash('success', $msg);

        return Response::redirect($redirectTo);
    }

    public function dismiss(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect(url('back-office'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $this->initialSetupService->markDismissed($tenantId);
        Session::flash('success', 'Rappel masqué. Vous pourrez rouvrir l’assistant depuis les paramètres de la communauté.');

        $redirect = trim((string) $request->input('redirect_to', ''));
        if ($redirect === 'setup') {
            return Response::redirect(url('back-office'));
        }

        return Response::redirect(url('back-office'));
    }

    public function complete(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect(url('back-office/configuration-initiale'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return Response::redirect(url('dashboard'));
        }

        // Enregistrer d’abord le formulaire si soumis avec « Terminer ».
        if ((string) $request->input('save_before_complete', '0') === '1') {
            $communityError = $this->saveCommunityFromRequest($tenantId, $request);
            if ($communityError !== null) {
                Session::flash('error', $communityError);

                return Response::redirect(url('back-office/configuration-initiale'));
            }
            $logoOutcome = $this->processLogoUpload($tenantId, $tenant, $request);
            if ($logoOutcome['error'] !== null) {
                Session::flash('error', $logoOutcome['error']);

                return Response::redirect(url('back-office/configuration-initiale'));
            }
        }

        $this->initialSetupService->markCompleted($tenantId);

        $user = $this->authService->user();
        if ($user) {
            $this->auditService->log(
                AuditAction::TENANT_SETUP_COMPLETED,
                $tenantId,
                (int) $user['id'],
                'tenant',
                $tenantId,
                null,
                'initial_setup_v' . TenantInitialSetupService::VERSION
            );
        }

        Session::flash('success', 'Configuration initiale terminée. Bienvenue dans le back-office.');

        $settings = $this->tenantRepository->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        if (TenantCommunityProfileService::needsDiscordInviteAlert($community)) {
            Session::flash(
                'success',
                'Configuration initiale terminée. Attention : le lien Discord n’est pas encore renseigné — ajoutez-le dans les paramètres pour finaliser le recrutement Discord.'
            );
        }

        return Response::redirect(url('back-office'));
    }

    /**
     * @param array<string, mixed> $tenant
     * @return array{error: ?string}
     */
    private function processLogoUpload(int $tenantId, array $tenant, Request $request): array
    {
        $slug = strtolower(trim((string) ($tenant['slug'] ?? '')));
        $dir = base_path('public/assets/img/communities');
        $relPath = 'assets/img/communities/' . ($slug !== '' ? $slug : 'tenant') . '-logo.png';
        $destFs = public_file_path($relPath);

        if ((string) $request->input('remove_org_logo', '0') === '1') {
            if ($slug !== '' && is_file($destFs)) {
                @unlink($destFs);
            }
            $this->brandingRepository->upsert($tenantId, ['logo_url' => null]);
            $this->tenantRepository->clearLogoUrl($tenantId);

            return ['error' => null];
        }

        $file = $_FILES['org_logo'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['error' => null];
        }
        $fe = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($fe !== UPLOAD_ERR_OK) {
            return ['error' => 'Envoi du logo impossible. Vérifiez la taille (max. 12 Mo) et le format, puis réessayez.'];
        }
        if ($slug === '' || !preg_match('/^[a-z0-9-]{1,50}$/', $slug)) {
            return ['error' => 'L’adresse publique de la communauté est requise avant d’ajouter un logo.'];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['error' => 'Fichier logo invalide. Réessayez.'];
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_IMAGE_BYTES) {
            return ['error' => 'Logo trop volumineux : limite de 12 Mo.'];
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        if (!in_array($mime, self::ALLOWED_IMAGE_MIMES, true)) {
            return ['error' => 'Format non pris en charge pour le logo. Utilisez une image JPG, PNG ou WebP.'];
        }
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['error' => 'Le stockage des images n’est pas disponible pour le moment.'];
        }
        if (!$this->writeImage($tmp, $destFs, 512, true)) {
            return ['error' => 'Impossible de traiter ce logo. Essayez avec un autre fichier.'];
        }
        $url = url($relPath) . '?v=' . (int) filemtime($destFs);
        $this->brandingRepository->upsert($tenantId, ['logo_url' => $url]);
        $this->tenantRepository->updateLogoUrl($tenantId, $url);

        return ['error' => null];
    }

    private function saveCommunityFromRequest(int $tenantId, Request $request): ?string
    {
        $settings = $this->tenantRepository->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];

        $rawContactEmail = trim((string) $request->input('contact_email', ''));
        if ($rawContactEmail !== '') {
            $sanitizedContact = $this->sanitizeEmail($rawContactEmail);
            if ($sanitizedContact === '') {
                return 'L’adresse e-mail de contact n’est pas valide.';
            }
            $community['contact_email'] = $sanitizedContact;
        } else {
            $community['contact_email'] = '';
        }
        $community['contact_discord_url'] = $this->sanitizeUrl((string) $request->input('contact_discord_url', ''), 500);
        $community['welcome_text'] = $this->clip((string) $request->input('welcome_text', ''), 500);
        $community['registration_mode'] = TenantCommunityProfileService::normalizeRegistrationMode(
            $request->input('registration_mode', TenantCommunityProfileService::REGISTRATION_MODE_MILSIM)
        );
        $community['community_locked'] = (string) $request->input('community_locked', '0') === '1';
        $community['require_ai_ack'] = (string) $request->input('require_ai_ack', '0') === '1';
        $community['refuse_other_community_members'] = (string) $request->input('refuse_other_community_members', '0') === '1';
        $community['public_recruitment_badge_open'] = (string) $request->input('public_recruitment_badge_open', '0') === '1';

        $existingModules = is_array($community['public_modules'] ?? null) ? $community['public_modules'] : [];
        $community['public_modules'] = [
            'forum' => (string) $request->input('public_mod_forum', '0') === '1',
            'documents' => (string) $request->input('public_mod_documents', '0') === '1',
            'events' => (string) $request->input('public_mod_events', '0') === '1',
            'roster' => (string) $request->input('public_mod_roster', '0') === '1',
            'training' => (string) $request->input('public_mod_training', '0') === '1',
            'analytics' => (string) $request->input('public_mod_analytics', '0') === '1',
        ] + $existingModules;

        $guestSlug = trim((string) $request->input('default_guest_role_slug', ''));
        $validSlugs = [];
        foreach ($this->rolePermissionService->listOrganizationRoles($tenantId) as $role) {
            $s = trim((string) ($role['slug'] ?? ''));
            if ($s !== '') {
                $validSlugs[$s] = true;
            }
        }
        if ($guestSlug !== '' && isset($validSlugs[$guestSlug])) {
            $community['default_guest_role_slug'] = $guestSlug;
        }

        $this->tenantRepository->mergeSettings($tenantId, [
            'community' => $community,
            'initial_setup_version' => TenantInitialSetupService::VERSION,
        ]);

        return null;
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
}
